<?php

namespace App\Traits;

use App\Constants\Status;
use App\Lib\AiAssistantLib\Gemini;
use App\Lib\AiAssistantLib\OpenAi;
use App\Lib\CurlRequest;
use App\Lib\WhatsApp\WhatsAppLib;
use App\Models\AiAssistant;
use App\Models\ContactNote;
use App\Models\Conversation;
use App\Models\CtaUrl;
use App\Models\Message;
use App\Models\WhatsappAccount;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

trait InboxManager
{
    public function list()
    {
        $user            = getParentUser();
        $contactId       = request()->contact_id;
        $conversationId  = request()->conversation ?? 0;
        $whatsappAccount = getWhatsappAccount($user);
        if ($contactId && $whatsappAccount) {
            $conversation = Conversation::where('user_id', $user->id)->where('contact_id', $contactId)->where('whatsapp_account_id', $whatsappAccount->id)->first();
            if (!$conversation) {
                $conversation                      = new Conversation();
                $conversation->user_id             = $user->id;
                $conversation->contact_id          = $contactId;
                $conversation->whatsapp_account_id = $whatsappAccount->id;
                $conversation->save();
            }
            $conversationId = $conversation->id;
        }

        $pageTitle = "Manage Inbox";
        $view      = 'Template::user.inbox.whatsapp_account_empty';

        if ($whatsappAccount) {
            $view = 'Template::user.inbox.list';
        }

        $ctaUrls = CtaUrl::where('user_id', $user->id)->get();

        return responseManager("inbox", $pageTitle, "success", [
            'view'            => $view,
            'pageTitle'       => $pageTitle,
            'conversationId'  => @$conversationId,
            'conversation'    => @$conversation,
            'whatsappAccount' => @$whatsappAccount,
            'ctaUrls'         => $ctaUrls
        ]);
    }

    public function conversationList(Request $request)
    {
        $user  = getParentUser();
        $query = Conversation::where('user_id', $user->id)
            ->whereHas('contact')
            ->searchable(['contact:firstname,lastname,mobile'])
            ->where('whatsapp_account_id', getWhatsappAccountId($user))
            ->with(['contact', 'lastMessage'])
            ->withCount('unseenMessages as unseen_messages')
            ->orderBy('last_message_at', 'desc');

        if ($request->status && $request->status != 0) {
            $query->where('status', $request->status);
        }

        $conversations    = $query->apiQuery();
        $html             = null;
        $conversationList = null;

        if (isApiRequest()) {
            $conversationList = $conversations;
        } else {
            $activeConversationId = request()->conversation_id ?? 0;
            $html                 = view('Template::user.inbox.conversation_list', compact('conversations', 'activeConversationId'))->render();
            $conversationList     = $conversations;
        }

        $notify[] = "Chat list";
        return apiResponse(
            "chat_list",
            "success",
            $notify,
            [
                'conversations' => $conversationList,
                'html'          => $html,
                'profilePath'   => getFilePath('contactProfile'),
                'more'          => $conversations->hasMorePages()
            ]
        );
    }

    public function conversationMessages($conversationId)
    {
        $user         = getParentUser();
        $conversation = Conversation::where('user_id', $user->id)->with('contact')->find($conversationId);

        if (!$conversation) {
            $notify[] = 'The conversation is not found';
            return apiResponse("not_found", "error", $notify);
        }

        $messageQuery = Message::where('conversation_id', $conversationId)
            ->searchable(['message']);

        $messages         = $messageQuery->orderBy('ordering', 'desc')->paginate();
        $html             = null;
        $conversationList = null;

        if (!isApiRequest()) {
            $html = view('Template::user.inbox.messages', compact('messages'))->render();
        }

        $notify[] = "Chat messages";

        return apiResponse(
            "chat_messages",
            "success",
            $notify,
            [
                'messages'            => $messages,
                'contact'             => $conversation->contact,
                'profilePath'         => getFilePath('contactProfile'),
                'mediaBasePath'       => getFilePath('conversation'),
                'html'                => $html,
                'more'                => $messages->hasMorePages(),
                'whatsapp_account_id' => @$user->currentWhatsapp()?->id
            ]
        );
    }

    public function updateMessageStatus($id)
    {
        $user          = getParentUser();
        $conversation  = Conversation::where('user_id', $user->id)->find($id);

        if (!$conversation) {
            return;
        }
        $whatsapp      = WhatsappAccount::where('user_id', $user->id)->where('id', $conversation->whatsapp_account_id)->first();

        if (!$whatsapp) {
            return;
        }

        $messages = Message::where('conversation_id', $conversation->id)
            ->where('type', Status::MESSAGE_RECEIVED)
            ->whereIn('status', [Status::SENT, Status::DELIVERED])
            ->get();

        foreach ($messages ?? [] as $message) {

            $url = "https://graph.facebook.com/v22.0/{$whatsapp->phone_number_id}/messages";

            $requestData = [
                'messaging_product' => 'whatsapp',
                'status'            => 'read',
                'message_id'        => $message->whatsapp_message_id
            ];

            $header = [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $whatsapp->access_token
            ];

            $data = CurlRequest::curlPostContent($url, $requestData, $header);

            $data = json_decode($data, true);

            if (isset($data['error'])) {
                continue;
            }

            if (isset($data['success'])) {
                $message->status = Status::READ;
                $message->save();
            }
        }

        return apiResponse("status_updated", "success", ["Status updated successfully"], [
            'unseenMessageCount' => $conversation->unseenMessages()->count()
        ]);
    }

    public function changeConversationStatus(Request $request, $conversationId)
    {
        $request->validate([
            'status' => ['nullable', "integer", Rule::in([Status::DONE_CONVERSATION, Status::PENDING_CONVERSATION, Status::IMPORTANT_CONVERSATION, 0])]
        ]);

        $user         = getParentUser();
        $conversation = Conversation::where('user_id', $user->id)->find($conversationId);

        if (!$conversation) {
            return apiResponse("not_found", "error", ["Conversation not found"]);
        };

        $conversation->status = $request->status ?? 0;
        $conversation->save();

        return apiResponse("status_updated", "success", ["Status updated successfully"]);
    }


    public function contactDetails($conversationId)
    {
        $user         = getParentUser();
        $conversation = Conversation::where('user_id', $user->id)->with(['contact', 'notes', 'contact.tags', 'contact.lists'])->find($conversationId);

        if (!$conversation) {
            $notify[] = 'Conversation not found';
            return apiResponse("conversation_details", "error", $notify);
        };

        $notify[] = 'Conversation details';
        $html     = null;

        if (!isApiRequest()) {
            $html = view('Template::user.inbox.contact_details', compact('conversation'))->render();
        }

        return apiResponse("conversation_details", "success", $notify, [
            'conversation' => $conversation,
            'profilePath'  => getFilePath('contactProfile'),
            'html'         => $html
        ]);
    }

    public function storeNote(Request $request)
    {
        $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'note'            => 'required|string|max:255',
        ]);

        $contactNote                  = new ContactNote();
        $contactNote->conversation_id = $request->conversation_id;
        $contactNote->note            = $request->note;
        $contactNote->user_id         = getParentUser()->id;
        $contactNote->save();

        $message = "Note saved successfully";
        return responseManager("note_saved", $message, "success", ['note' => $contactNote]);
    }

    public function deleteNote($id)
    {
        $user = getParentUser();
        $note = ContactNote::where('user_id', $user->id)->find($id);

        if (!$note) {
            return apiResponse("note_not_found", "error", ["The note is not found"]);
        }

        $note->delete();

        $notify[] = "Note deleted successfully";
        return apiResponse("note_deleted", "success", $notify);
    }

    public function sendMessage(Request $request)
    {
        $request->validate([
            'message'         => 'required_without_all:image,document,video,audio,cta_url_id',
            'conversation_id' => 'required',
            'image'           => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:25600'],
            'document'        => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,zip,rar', 'max:102400'],
            'video'           => ['nullable', 'file', 'mimes:mp4,mov,avi', 'max:51200'],
            'audio'           => 'nullable|file|max:20480',
            'cta_url_id'      => 'nullable|int',
        ], [
            'conversation_id.required'     => 'Please select a conversation to send message.',
            'message.required_without_all' => 'The message field is required',
        ]);

        $ctaUrl       = null;
        $user         = getParentUser();
        $conversation = Conversation::where('user_id', $user->id)->find($request->conversation_id);

        if (!$conversation) {
            return apiResponse("not_found", "error", ["The conversation is not found"]);
        }

        if ($request->cta_url_id) {
            $ctaUrl = CtaUrl::where('user_id', $user->id)->find($request->cta_url_id);
            if (!$ctaUrl) {
                return apiResponse("not_found", "error", ["The cta url is not found"]);
            }
        }

        try {
            $whatsappAccount  = getWhatsappAccount($user);
            if (!$whatsappAccount) {
                return apiResponse("not_found", "error", ["The whatsapp account is not found"]);
            }

            if ($ctaUrl) {
                if (!featureAccessLimitCheck($user->cta_url_message)) {
                    return apiResponse("not_found", "error", ["Your current plan does not support CTA URL messages. Please upgrade your plan."]);
                }
                $messageSend = (new WhatsAppLib())->sendCtaUrlMessage($conversation->contact->mobileNumber, $whatsappAccount, $ctaUrl);
            } else {
                $messageSend = (new WhatsAppLib())->messageSend($request, $conversation->contact->mobileNumber, $whatsappAccount);
            }

            extract($messageSend);
            
            $jobIdValue = $messageSend['jobId'] ?? null;

            $agentId = 0;
            if (auth()->user()->is_agent) $agentId = auth()->id();

            $message                      = new Message();
            $message->user_id             = $user->id;
            $message->whatsapp_account_id = $whatsappAccount->id;
            
            if ($jobIdValue) {
                $message->job_id              = $jobIdValue;
                $message->whatsapp_message_id = null;
                $message->status              = Status::SCHEDULED;
            } else {
                $message->job_id              = null;
                $message->whatsapp_message_id = $whatsAppMessage[0]['id'];
                $message->status              = Status::SENT;
            }
            
            $message->conversation_id     = $conversation->id;
            $message->cta_url_id          = $ctaUrlId ?? 0;
            $message->type                = Status::MESSAGE_SENT;
            $message->message             = $request->message;
            $message->media_id            = $mediaId;
            $message->message_type        = getIntMessageType($messageType);;
            $message->media_caption       = $mediaCaption;
            $message->media_filename      = $mediaFileName;
            $message->media_url           = $mediaUrl;
            $message->media_path          = $mediaPath;
            $message->mime_type           = $mimeType;
            $message->media_type          = $mediaType;
            $message->agent_id            = $agentId;
            $message->ordering            = Carbon::now()->format('Y-m-d H:i:s.u');
            $message->save();

            $conversation->last_message_at = Carbon::now();
            $conversation->save();
            
            // Reativar IA automaticamente após mensagem manual (se configurado)
            if ($conversation->needs_human_reply == Status::YES && $user->aiSetting) {
                $aiSetting = $user->aiSetting;
                
                // Se a reativação automática está habilitada
                if ($aiSetting->auto_reactivate_ai) {
                    // Se não há delay ou delay é 0, reativa imediatamente
                    if (!$aiSetting->reactivation_delay_minutes || $aiSetting->reactivation_delay_minutes == 0) {
                        $conversation->needs_human_reply = Status::NO;
                        $conversation->save();
                        \Log::info('AI reativada imediatamente após resposta manual', [
                            'conversation_id' => $conversation->id,
                            'user_id' => $user->id
                        ]);
                    }
                    // Se há delay configurado, verificamos se já passou o tempo
                    else {
                        // Busca a última mensagem de fallback (ai_reply = 1)
                        $lastAiMessage = Message::where('conversation_id', $conversation->id)
                            ->where('ai_reply', Status::YES)
                            ->where('type', Status::MESSAGE_SENT)
                            ->latest('id')
                            ->first();
                        
                        if ($lastAiMessage) {
                            $minutesSinceFallback = $lastAiMessage->created_at->diffInMinutes(Carbon::now());
                            
                            if ($minutesSinceFallback >= $aiSetting->reactivation_delay_minutes) {
                                $conversation->needs_human_reply = Status::NO;
                                $conversation->save();
                                \Log::info('AI reativada após tempo de delay', [
                                    'conversation_id' => $conversation->id,
                                    'user_id' => $user->id,
                                    'delay_minutes' => $aiSetting->reactivation_delay_minutes,
                                    'minutes_passed' => $minutesSinceFallback
                                ]);
                            }
                        }
                    }
                }
            }

            $notify[] =  __("Message sent successfully");

            if (!isApiRequest()) {
                $lastMessageHtml = view("Template::user.inbox.conversation_last_message", compact('message'))->render();
                return apiResponse("success", "success", $notify, [
                    'conversationId' => $conversation->id,
                    'html' => view('Template::user.inbox.single_message', compact('message'))->render(),
                    'lastMessageHtml' => $lastMessageHtml
                ]);
            }
            return apiResponse("success", "success", $notify, [
                'message' => $message
            ]);
        } catch (Exception $ex) {
            $notify[] =  $ex->getMessage() ?? "Something went to wrong";
            return apiResponse("exception", "error", $notify);
        }
    }

    public function resendMessage(Request $request)
    {
        $request->validate([
            'message_id' => 'required',
        ]);

        $user    = getParentUser();

        $message = Message::where('user_id', $user->id)->where('type', Status::SENT)->where('status', Status::FAILED)->find($request->message_id);
        if (!$message) {
            return apiResponse("message_not_found", "error", ["Message not found"]);
        }

        $conversation  = $message->conversation;
        $contact       = $conversation->contact;

        if (!$conversation || !$contact) {
            return apiResponse("not_found", "error", ["The receiver does not exist"]);
        }

        $agentId = 0;
        if (auth()->user()->is_agent) $agentId = auth()->id();

        try {
            $whatsappAccount  = $user->currentWhatsapp();
            $messageResend = (new WhatsAppLib())->messageResend($message, $conversation->contact->mobileNumber, $whatsappAccount);

            $message->whatsapp_message_id = $messageResend['whatsAppMessage'][0]['id'];
            $message->status              = Status::MESSAGE_SENT;
            $message->ordering            = Carbon::now()->format('Y-m-d H:i:s.u');
            $message->agent_id            = $agentId;
            $message->save();

            if (isApiRequest()) {
                return apiResponse("success", "success", ["Message resend successfully"]);
            }
            return apiResponse("success", "success", ["Message resend successfully"], [
                'html' => view('Template::user.inbox.single_message', compact('message'))->render()
            ]);
        } catch (Exception $ex) {
            $notify[] =  $ex->getMessage() ?? "Something went to wrong";
            return apiResponse("exception", "error", $notify);
        }
    }

    public function downloadMedia($mediaId)
    {
        $user = getParentUser();

        $message = Message::where('media_id', $mediaId)
            ->where('user_id', $user->id)
            ->first();

        if (!$message) {
            return apiResponse("message_not_found", "error", ["Message not found"]);
        }

        $accessToken = $user->currentWhatsapp()->access_token;

        try {
            if ($message->message_type == Status::IMAGE_TYPE_MESSAGE) {
                $filePath = getFilePath('conversation') . "/" . $message->media_path;

                if ($message->media_path && File::exists($filePath)) {
                    return response()->download($filePath);
                } else {
                    return responseManager('exception', "Failed to load the media");
                }
            }

            $mediaUrl = (new WhatsAppLib())
                ->getMediaUrl($mediaId, $accessToken)['url'];

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->get($mediaUrl);

            if ($response->failed()) {
                return responseManager('exception', "Failed to load the media");
            }

            $fileContent = $response->body();
            $mimeType = $response->header('Content-Type');
            $extension = explode('/', $mimeType)[1];
            $fileName = "{$mediaId}.{$extension}";

            return response($fileContent, 200)
                ->header('Content-Type', $mimeType)
                ->header('Content-Disposition', "attachment; filename={$fileName}");
        } catch (Exception $ex) {
            return responseManager('exception', $ex->getMessage());
        }
    }

    public function generateAiMessage(Request $request)
    {
        $request->validate([
            'message'         => 'required|string',
        ], [
            'message.required' => 'Unable to generate response. Please try again.',
            'message.string'   => 'The only type of message allowed is text.',
        ]);

        $user = getParentUser();

        if (!featureAccessLimitCheck($user->ai_assistance)) {
            return responseManager('not_available', 'Your current plan does not support AI Assistant. Please upgrade your plan.');
        }

        $activeProvider = AiAssistant::active()->first();
        if (!$activeProvider) {
            return responseManager('not_available', 'AI Assistant is currently disabled. Please try again.');
        }

        $userAiSetting  = $user->aiSetting;
        if (!$userAiSetting || !$userAiSetting->status) {
            return responseManager('not_available', 'AI Assistant is disabled');
        }

        $provider = [
            'openai' => OpenAi::class,
            'gemini' => Gemini::class
        ];

        $aiAssistantClass = $provider[$activeProvider->provider];

        $aiAssistant = new $aiAssistantClass();
        $systemPrompt    = $userAiSetting->system_prompt;
        $aiResponse      = $aiAssistant->getAiReply($systemPrompt, $request->message);

        if ($aiResponse['success'] == true) {
            if ($aiResponse['response'] == null) {
                return responseManager('null_response', 'AI Assistant is unable to generate response for this message.');
            } else {
                return responseManager('ai_response', 'Response generated successfully', 'success', [
                    'ai_response' => $aiResponse['response']
                ]);
            }
        } else {
            return responseManager('error', 'Unable to generate AI response. Please try again.');
        }
    }
}
