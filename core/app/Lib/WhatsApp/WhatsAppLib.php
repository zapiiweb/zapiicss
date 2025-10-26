<?php

namespace App\Lib\WhatsApp;

use App\Constants\Status;
use App\Events\ReceiveMessage;
use App\Lib\AiAssistantLib\Gemini;
use App\Lib\AiAssistantLib\OpenAi;
use App\Lib\CurlRequest;
use App\Services\BaileysService;
use App\Models\AiAssistant;
use App\Models\Message;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WhatsAppLib
{
    public function sendCtaUrlMessage($toNumber, $whatsappAccount, $ctaUrl)
    {

        $phoneNumberId    = $whatsappAccount->phone_number_id;
        $accessToken      = $whatsappAccount->access_token;

        $url       = $this->getWhatsAppBaseUrl() . "{$phoneNumberId}/messages";

        $data = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $toNumber,
            'type'              => 'interactive'
        ];

        $interactive = [
            'type'  => 'cta_url',
            'header' => $ctaUrl->header,
            'body'  => $ctaUrl->body,
            'action' => $ctaUrl->action
        ];

        if (!empty($ctaUrl->footer) && count($ctaUrl->footer) > 0) {
            $interactive['footer'] = $ctaUrl->footer;
        }

        $data['interactive'] = $interactive;

        try {

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}"
            ])->post($url, $data);
            $responseData = $response->json();

            if (!is_array($responseData) || !count($responseData)) {
                throw new Exception(__("Error sending message. Invalid response from WhatsApp API."));
            }

            if (isset($responseData['error']) || !isset($responseData['messages'])) {
                throw new Exception(@$responseData['error']['error_data']['details'] ?? @$responseData['error']['message'] ?? __("Error sending message via WhatsApp."));
            }

            if ($response->failed()) {
                throw new Exception(__("Message sending failed"));
            }

            return [
                'whatsAppMessage' => $responseData['messages'],
                'ctaUrlId'        => $ctaUrl->id,
                'mediaId'         => null,
                'mediaUrl'        => null,
                'mediaPath'       => null,
                'mediaCaption'    => null,
                'mediaFileName'   => null,
                'messageType'     => 'url',
                'mimeType'        => null,
                'mediaType'       => null
            ];
        } catch (Exception $ex) {
            throw new Exception($ex->getMessage());
        }
    }

    public function messageSend($request, $toNumber, $whatsappAccount)
    {
        // Check connection type preference (1 = Meta API, 2 = Baileys)
        $connectionType = $whatsappAccount->connection_type ?? 1;

        if ($connectionType == 2) {
            // User prefers Baileys - check if it's connected
            if (!$whatsappAccount->baileys_connected || !$whatsappAccount->baileys_session_id) {
                throw new Exception(__("WhatsApp is disconnected. Please reconnect your account by scanning the QR code in the settings page."));
            }
            return $this->messageSendViaBaileys($request, $toNumber, $whatsappAccount);
        }

        // User prefers Meta API - validate access token exists
        if (empty($whatsappAccount->access_token)) {
            throw new Exception(__("Meta API access token is empty or expired. Please update your access token in the settings page."));
        }

        $phoneNumberId    = $whatsappAccount->phone_number_id;
        $accessToken      = $whatsappAccount->access_token;

        $url       = $this->getWhatsAppBaseUrl() . "{$phoneNumberId}/messages";
        $mediaLink = $this->getWhatsAppBaseUrl() . "{$phoneNumberId}/media";

        $mediaId       = null;
        $mediaUrl      = null;
        $mediaPath     = null;
        $mediaCaption  = null;
        $mediaFileName = null;
        $mimeType      = null;
        $mediaType     = null;

        $data = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $toNumber,
        ];

        if ($request->hasFile('image')) {
            $file          = $request->file('image');
            $mimeType      = mime_content_type($file->getPathname());
            
            // Upload to Meta API first
            $mediaUpload   = $this->uploadMedia($mediaLink, $file, $accessToken);
            $mediaId       = $mediaUpload['id'];
            
            // Store media locally after uploading
            $mediaPath     = $this->storeMediaFile($file, $whatsappAccount->user_id);
            $mediaCaption  = $request->message;
            $data['type']  = 'image';
            $data['image'] = [
                'id'      => $mediaId,
                'caption' => $mediaCaption
            ];
            $mediaType     = 'image';
        } else if ($request->hasFile('document')) {
            $file             = $request->file('document');
            $mimeType         = mime_content_type($file->getPathname());
            $mediaFileName    = $file->getClientOriginalName();
            
            // Upload to Meta API first
            $mediaUpload      = $this->uploadMedia($mediaLink, $file, $accessToken);
            $mediaId          = $mediaUpload['id'];
            
            // Store media locally after uploading
            $mediaPath        = $this->storeMediaFile($file, $whatsappAccount->user_id);
            $mediaCaption     = $request->message;
            $data['type']     = 'document';
            $data['document'] = [
                'id'       => $mediaId,
                'caption'  => $mediaCaption,
                'filename' => $mediaFileName
            ];
            $mediaType        = 'document';
        } else if ($request->hasFile('video')) {
            $file          = $request->file('video');
            $mimeType      = mime_content_type($file->getPathname());
            
            // Upload to Meta API first
            $mediaUpload   = $this->uploadMedia($mediaLink, $file, $accessToken);
            $mediaId       = $mediaUpload['id'];
            
            // Store media locally after uploading
            $mediaPath     = $this->storeMediaFile($file, $whatsappAccount->user_id);
            $mediaCaption  = $request->message;
            $data['type']  = 'video';
            $data['video'] = [
                'id'      => $mediaId,
                'caption' => $mediaCaption
            ];
            $mediaType     = 'video';
        } else if ($request->hasFile('audio')) {
            $file          = $request->file('audio');
            $mimeType      = mime_content_type($file->getPathname());
            
            // Upload to Meta API first
            $mediaUpload   = $this->uploadMedia($mediaLink, $file, $accessToken);
            $mediaId       = $mediaUpload['id'];
            
            // Store media locally after uploading
            $mediaPath     = $this->storeMediaFile($file, $whatsappAccount->user_id);
            $mediaCaption  = $request->message;
            $data['type']  = 'audio';
            $data['audio'] = [
                'id'      => $mediaId
            ];
            $mediaType     = 'audio';
        } else {
            $data['type'] = 'text';
            $data['text'] = [
                'body' => $request->message
            ];
        }

        try {

            if ($mediaId) {
                $mediaUrl = $this->getMediaUrl($mediaId, $accessToken)['url'];
            }

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}"
            ])->post($url, $data);
            
            $responseData = $response->json();

            if (!is_array($responseData) || !count($responseData)) {
                throw new Exception(__("Error sending message. Invalid response from WhatsApp API."));
            }

            if (isset($responseData['error']) || !isset($responseData['messages'])) {
                $errorMessage = @$responseData['error']['message'] ?? __("Error sending message via WhatsApp.");
                
                // Check for token-related errors
                if (stripos($errorMessage, 'token') !== false || 
                    stripos($errorMessage, 'expired') !== false ||
                    stripos($errorMessage, 'invalid') !== false ||
                    stripos($errorMessage, 'authentication') !== false) {
                    throw new Exception(__("Meta API access token is expired or invalid. Please update your access token in the settings page."));
                }
                
                throw new Exception($errorMessage);
            }

            if ($response->failed()) {
                throw new Exception(__("Failed to resend message."));
            }

            return [
                'whatsAppMessage' => $responseData['messages'],
                'ctaUrlId'        => 0,
                'mediaId'         => $mediaId,
                'mediaUrl'        => $mediaUrl,
                'mediaPath'       => $mediaPath,
                'mediaCaption'    => $mediaCaption,
                'mediaFileName'   => $mediaFileName,
                'messageType'     => $data['type'],
                'mimeType'        => $mimeType ?? null,
                'mediaType'       => $mediaType ?? null
            ];
        } catch (Exception $ex) {
            throw new Exception($ex->getMessage());
        }
    }

    public function messageResend(object $message, $toNumber, $whatsappAccount)
    {
        $phoneNumberId    = $whatsappAccount->phone_number_id;
        $accessToken      = $whatsappAccount->access_token;

        $url       = $this->getWhatsAppBaseUrl() . "{$phoneNumberId}/messages";

        $mediaId = $message->media_id ?? null;
        $mediaCaption = $message->media_caption ?? null;
        $mediaFileName = $message->media_filename ?? null;

        $data = [
            'messaging_product' => 'whatsapp',
            'to'                => $toNumber,
            'type'              => 'text'
        ];

        if ($message->media_id && $message->message_type == Status::IMAGE_TYPE_MESSAGE) {
            $data['type']  = 'image';
            $data['image'] = [
                'id'      => $mediaId,
                'caption' => $mediaCaption
            ];
        } else if ($message->media_id && $message->message_type == Status::DOCUMENT_TYPE_MESSAGE) {
            $data['type']     = 'document';
            $data['document'] = [
                'id'       => $mediaId,
                'caption'  => $mediaCaption,
                'filename' => $mediaFileName
            ];
        } else if ($message->media_id && $message->message_type == Status::VIDEO_TYPE_MESSAGE) {
            $data['type']  = 'video';
            $data['video'] = [
                'id'      => $mediaId,
                'caption' => $mediaCaption
            ];
        } else {
            $data['type'] = 'text';
            $data['text'] = [
                'body' => $message->message
            ];
        }

        try {

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}"
            ])->post($url, $data);

            $responseData = $response->json();

            if (!is_array($responseData) || !count($responseData)) {
                throw new Exception(__("Error sending message. Invalid response from WhatsApp API."));
            }

            if (isset($responseData['error']) || !isset($responseData['messages'])) {
                throw new Exception(@$responseData['error']['message'] ?? __("Error resending message via WhatsApp."));
            }

            if ($response->failed()) {
                throw new Exception(__("Failed to resend message."));
            }

            return [
                'whatsAppMessage' => $responseData['messages']
            ];
        } catch (Exception $ex) {
            throw new Exception($ex->getMessage());
        }
    }

    public function uploadMedia($mediaUrl, $file, $accessToken)
    {
        $filePath = $file->getRealPath();
        $fileName = $file->getClientOriginalName();

        $postData = [
            'messaging_product' => 'whatsapp',
        ];

        $headers = [
            "Authorization: Bearer {$accessToken}",
        ];

        $response = CurlRequest::curlFileUpload(
            $mediaUrl,
            $postData,
            'file',
            $filePath,
            $fileName,
            $headers
        );

        $data = json_decode($response, true);

        if (!is_array($data) || isset($data['error']) || !isset($data['id'])) {
            $errorMessage = __("Failed to upload media");
            if (isset($data['error']['error_user_msg'])) {
                $errorMessage = $data['error']['error_user_msg'];
            }
            if ($data['error']['message']) {
                $errorMessage = $data['error']['message'];
            }
            throw new Exception($errorMessage);
        }

        return $data;
    }

    function getSessionId($appId, array $fileData, $accessToken)
    {
        try {

            $url      = "https://graph.facebook.com/v23.0/{$appId}/uploads";
            $response = Http::post($url, [
                'file_name'    => $fileData['name'],
                'file_length'  => $fileData['size'],
                'file_type'    => $fileData['type'],
                'access_token' => $accessToken
            ]);
            $data = $response->json();
            if ($response->failed() || !is_array($data) || !isset($data['id'])) {
                throw new Exception(@$data['error']['message'] ?? __("Could not upload your header image! Please try again later."));
            }
            return $data;
        } catch (Exception $ex) {
            throw new Exception($ex->getMessage() ?? __("Could not upload your header image! Please try again later."));
        }
    }

    function getMediaHandle($sessionId, $accessToken, $filePath, $mimeType)
    {
        try {

            $cleanSessionId = str_replace('upload:', '', $sessionId);
            $url            = "https://graph.facebook.com/v23.0/upload:$cleanSessionId";
            $fileContents   = file_get_contents($filePath);

            $response = Http::withHeaders([
                'Authorization' => "OAuth $accessToken",
                'file_offset'   => '0',
            ])->withBody($fileContents, $mimeType)
                ->post($url);

            $data = $response->json();
            if ($response->failed() || !is_array($data) || !isset($data['h'])) {
                throw new Exception(@$data['error']['error_user_msg'] ?? @$data['error']['message']);
            }
            return $data['h'];
        } catch (Exception $ex) {
            throw new Exception($ex->getMessage() ?? __("Failed to get the media handle! Please try again later."));
        }
    }

    public function getMediaUrl($mediaId, $accessToken)
    {
        $url = $this->getWhatsAppBaseUrl() . "{$mediaId}";

        $response = CurlRequest::curlContent($url, [
            "Authorization: Bearer {$accessToken}"
        ]);

        $data = json_decode($response, true);

        if (!is_array($data) || isset($data['error']) || !isset($data['url'])) {
            throw new Exception(@$data['error']['error_user_msg'] ?? @$data['error']['message'] ?? __("Failed to load the media URL. Please try again later."));
        }

        return $data;
    }

    public function getWhatsAppBaseUrl()
    {
        return "https://graph.facebook.com/v22.0/";
    }

    public function storedMediaToLocal($mediaUrl, $mediaId, $accessToken, $userId)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->get($mediaUrl);

            if ($response->failed()) {
                throw new Exception(__("Message sending fail for the download media"));
            }

            $fileContent = $response->body();
            $mimeType    = $response->header('Content-Type');

            $fileExtension = explode('/', $mimeType)[1];
            $fileName      = "{$mediaId}.{$fileExtension}";

            $parentFolder = getFilePath('conversation');
            $subFolder    = "{$userId}/" . date('Y/m/d');
            $folderPath   = $parentFolder . "/" . $subFolder;
            $filePath     = $folderPath . "/" . $fileName;

            if (!file_exists($folderPath)) {
                mkdir($folderPath, 0755, true);
            }

            file_put_contents($filePath, $fileContent);

            return $subFolder . "/" . $fileName;
        } catch (Exception $ex) {
            throw new Exception($ex->getMessage());
        }
    }
/**
     * Send message via Baileys
     */
    private function messageSendViaBaileys($request, $toNumber, $whatsappAccount)
    {
        $baileysService = new BaileysService();
        
        $message = $request->message ?? '';
        $options = [];
        
        // Handle media uploads
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $mimeType = $file->getMimeType();
            $mediaPath = $this->storeMediaFile($file, $whatsappAccount->user_id);
            $mediaUrl = url('assets/media/conversation/' . $mediaPath);
            
            $options['mediaType'] = 'image';
            $options['mediaUrl'] = $mediaUrl;
            $options['caption'] = $message;
            $options['mimeType'] = $mimeType;
        } elseif ($request->hasFile('document')) {
            $file = $request->file('document');
            $mimeType = $file->getMimeType();
            $fileName = $file->getClientOriginalName();
            $mediaPath = $this->storeMediaFile($file, $whatsappAccount->user_id);
            $mediaUrl = url('assets/media/conversation/' . $mediaPath);
            
            $options['mediaType'] = 'document';
            $options['mediaUrl'] = $mediaUrl;
            $options['caption'] = $message;
            $options['mimeType'] = $mimeType;
            $options['fileName'] = $fileName;
        } elseif ($request->hasFile('video')) {
            $file = $request->file('video');
            $mimeType = $file->getMimeType();
            $mediaPath = $this->storeMediaFile($file, $whatsappAccount->user_id);
            $mediaUrl = url('assets/media/conversation/' . $mediaPath);
            
            $options['mediaType'] = 'video';
            $options['mediaUrl'] = $mediaUrl;
            $options['caption'] = $message;
            $options['mimeType'] = $mimeType;
        } elseif ($request->hasFile('audio')) {
            $file = $request->file('audio');
            $mimeType = $file->getMimeType();
            $mediaPath = $this->storeMediaFile($file, $whatsappAccount->user_id);
            $mediaUrl = url('assets/media/conversation/' . $mediaPath);
            
            $options['mediaType'] = 'audio';
            $options['mediaUrl'] = $mediaUrl;
            $options['mimeType'] = $mimeType;
        }
        
        // Send message via Baileys
        \Log::info('BAILEYS REQUEST', [
            'session' => $whatsappAccount->baileys_session_id,
            'to' => $toNumber,
            'options' => $options
        ]);
        
        $result = $baileysService->sendMessage(
            $whatsappAccount->baileys_session_id,
            $toNumber,
            $message,
            $options
        );
        
        \Log::info('BAILEYS RESPONSE', ['result' => $result]);
        
        if (!$result['success']) {
            \Log::error('BAILEYS SEND FAILED', ['error' => $result['message']]);
            throw new Exception($result['message']);
        }
        
        // Return format compatible with Meta API response
        // For async mode, we get jobId instead of messageId
        return [
            'whatsAppMessage' => [[
                'id' => $result['jobId'] ?? 'baileys_' . time(),  // Will be updated via webhook
            ]],
            'jobId'           => $result['jobId'] ?? null,  // For async tracking
            'mediaId'         => null,
            'mediaUrl'        => $options['mediaUrl'] ?? null,
            'mediaPath'       => $mediaPath ?? null,
            'mediaCaption'    => $options['caption'] ?? null,
            'mediaFileName'   => $options['fileName'] ?? null,
            'messageType'     => $options['mediaType'] ?? 'text',
            'mimeType'        => $options['mimeType'] ?? null,
            'mediaType'       => $options['mediaType'] ?? null
        ];
    }
    
    /**
     * Store media file locally
     */
    private function storeMediaFile($file, $userId)
    {
        $fileExtension = $file->getClientOriginalExtension();
        $fileName = uniqid() . '.' . $fileExtension;
        
        $parentFolder = getFilePath('conversation');
        $subFolder = "{$userId}/" . date('Y/m/d');
        $folderPath = $parentFolder . "/" . $subFolder;
        
        if (!file_exists($folderPath)) {
            mkdir($folderPath, 0755, true);
        }
        
        $file->move($folderPath, $fileName);
        
        return $subFolder . "/" . $fileName;
    }


    public function  submitTemplate($businessAccountId, $accessToken, $templateData = [])
    {
        $header = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken
        ];

        try {

            $response = CurlRequest::curlPostContent($this->getWhatsAppBaseUrl() . "{$businessAccountId}/message_templates", $templateData, $header);
            $data     = json_decode($response, true);

            if (!is_array($data) || isset($data['error'])) {
                throw new Exception(@$data['error']['error_user_msg'] ?? @$data['error']['message'] ?? __("Error processing WhatsApp media."));
            }
            return $data;
        } catch (Exception $ex) {
            throw new Exception($ex->getMessage() ?? __("Error processing WhatsApp media."));
        }
    }

    public function sendAutoReply($user, $conversation,$message)
    {
        \Log::info('=== sendAutoReply INICIADO ===', ['user_id' => $user->id, 'conversation_id' => $conversation->id]);
        
        $this->checkConversationLastMessage($conversation);

        $contact  = $conversation->contact;
        $userAiSetting = $user->aiSetting;

        \Log::info('sendAutoReply - Check 1: userAiSetting', ['exists' => !is_null($userAiSetting)]);
        if (!$userAiSetting) return;

        \Log::info('sendAutoReply - Check 2: Status/Contact/NeedsHuman', [
            'aiSetting_status' => $userAiSetting->status,
            'has_contact' => !is_null($contact),
            'needs_human_reply' => $conversation->needs_human_reply
        ]);
        
        // Verificar se deve reativar IA automaticamente após delay
        if ($conversation->needs_human_reply == Status::YES && $userAiSetting->auto_reactivate_ai) {
            // Se não há delay ou delay é 0, reativa imediatamente
            if (!$userAiSetting->reactivation_delay_minutes || $userAiSetting->reactivation_delay_minutes == 0) {
                $conversation->needs_human_reply = Status::NO;
                $conversation->save();
                \Log::info('sendAutoReply - IA reativada automaticamente (sem delay)', [
                    'conversation_id' => $conversation->id
                ]);
            }
            // Se há delay configurado e > 0, verifica se já passou o tempo
            else if ($userAiSetting->reactivation_delay_minutes > 0) {
                // Busca a última mensagem de fallback (ai_reply = 1)
                $lastAiMessage = Message::where('conversation_id', $conversation->id)
                    ->where('ai_reply', Status::YES)
                    ->where('type', Status::MESSAGE_SENT)
                    ->latest('id')
                    ->first();
                
                if ($lastAiMessage) {
                    $minutesSinceFallback = $lastAiMessage->created_at->diffInMinutes(Carbon::now());
                    
                    // Se já passou o tempo configurado, reativa a IA
                    if ($minutesSinceFallback >= $userAiSetting->reactivation_delay_minutes) {
                        $conversation->needs_human_reply = Status::NO;
                        $conversation->save();
                        \Log::info('sendAutoReply - IA reativada automaticamente após delay', [
                            'conversation_id' => $conversation->id,
                            'delay_minutes' => $userAiSetting->reactivation_delay_minutes,
                            'minutes_passed' => $minutesSinceFallback
                        ]);
                    }
                }
            }
        }
        
        if($userAiSetting->status == Status::DISABLE || !$contact || $conversation->needs_human_reply == Status::YES) return;

        $provider = [
            'openai' => OpenAi::class,
            'gemini' => Gemini::class
        ];

        $activeProvider   = AiAssistant::active()->first();
        
        \Log::info('sendAutoReply - Check 3: activeProvider', ['exists' => !is_null($activeProvider), 'provider' => $activeProvider?->provider ?? 'null']);
        if(!$activeProvider) return;

        \Log::info('sendAutoReply - Check 4: user.ai_assistance', ['ai_assistance' => $user->ai_assistance]);
        if($user->ai_assistance == 0) return;

        $aiAssistantClass = $provider[$activeProvider->provider];

        $aiAssistant = new $aiAssistantClass();

        \Log::info('sendAutoReply - Vai chamar IA', ['class' => $aiAssistantClass]);
        if($userAiSetting->status == Status::ENABLE){
            $systemPrompt    = $userAiSetting->system_prompt;
            
            // Adiciona instrução para usar palavra-chave FALLBACK_RESPONSE
            $enhancedPrompt = $systemPrompt . "\n\nIMPORTANTE: Se você não souber a resposta ou não tiver informações suficientes na base de conhecimento fornecida, responda EXATAMENTE com a palavra: FALLBACK_RESPONSE";
            
            $aiResponse      = $aiAssistant->getAiReply($enhancedPrompt, $message);
            \Log::info('sendAutoReply - Resposta da IA', ['aiResponse' => $aiResponse]);
            
            // Usar a mesma conta WhatsApp que recebeu a mensagem (via conversa)
            $whatsappAccount = $conversation->whatsappAccount;
            
            // Se a conversa não tem conta associada (conversas antigas), usar a conta padrão do usuário
            if (!$whatsappAccount) {
                \Log::warning('sendAutoReply - Conversa sem whatsappAccount associado, usando conta padrão do usuário');
                $whatsappAccount = $user->currentWhatsapp();
                
                // Se mesmo assim não tem conta, sair
                if (!$whatsappAccount) {
                    \Log::error('sendAutoReply - Usuário não tem conta WhatsApp disponível');
                    return;
                }
            }
            
            \Log::info('sendAutoReply - Conta WhatsApp selecionada', [
                'whatsapp_account_id' => $whatsappAccount->id,
                'connection_type' => $whatsappAccount->connection_type,
                'number' => $whatsappAccount->number
            ]);
            
            // Verifica se a IA não sabe a resposta
            $shouldUseFallback = false;
            
            // Caso 1: Erro técnico ou resposta vazia
            if($aiResponse['success'] == false || $aiResponse['response'] == null || trim($aiResponse['response']) == '') {
                \Log::info('sendAutoReply - IA falhou ou retornou null/vazio');
                $shouldUseFallback = true;
            }
            // Caso 2: IA retornou a palavra-chave FALLBACK_RESPONSE
            else if(stripos($aiResponse['response'], 'FALLBACK_RESPONSE') !== false) {
                \Log::info('sendAutoReply - IA retornou palavra-chave FALLBACK_RESPONSE');
                $shouldUseFallback = true;
            }
            // Caso 3: IA indica que não sabe a resposta (em português ou inglês)
            else if($this->aiResponseIndicatesNoKnowledge($aiResponse['response'])) {
                \Log::info('sendAutoReply - IA indicou que não sabe a resposta');
                $shouldUseFallback = true;
            }
            
            if($shouldUseFallback) {
                \Log::info('sendAutoReply - Usando fallback response');
                if($userAiSetting->fallback_response != null) {
                    $request = new Request([
                        'message' => $userAiSetting->fallback_response,
                    ]);
                    $conversation->needs_human_reply = Status::YES;
                    $conversation->save();
                } else {
                    \Log::info('sendAutoReply - Sem fallback configurado, saindo');
                    return; // Não tem fallback, então não faz nada
                }
            } else {
                // IA funcionou e retornou resposta válida
                \Log::info('sendAutoReply - IA retornou resposta válida');
                $request = new Request([
                    'message' => $aiResponse['response'],
                ]);
            }
            
            if($aiResponse['success'] == true || $userAiSetting->fallback_response != null)
            {
                $messageSend = $this->messageSend($request,$contact->mobileNumber, $whatsappAccount);
                extract($messageSend);
                
                $jobIdValue = $messageSend['jobId'] ?? null;

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
                $message->ordering            = Carbon::now()->format('Y-m-d H:i:s.u');
                $message->ai_reply            = Status::YES;
                $message->save();

                $conversation->last_message_at = Carbon::now();
                $conversation->save();

                $html                        = view('Template::user.inbox.single_message', compact('message'))->render();
                $lastConversationMessageHtml = view("Template::user.inbox.conversation_last_message", compact('message'))->render();

                event(new ReceiveMessage($whatsappAccount->id, [
                    'html'            => $html,
                    'message'         => $message,
                    'newMessage'      => true,
                    'newContact'      => false,
                    'lastMessageHtml' => $lastConversationMessageHtml,
                    'unseenMessage'   => $conversation->unseenMessages()->count() < 10 ? $conversation->unseenMessages()->count() : '9+',
                    'lastMessageAt'   => showDateTime(Carbon::now()),
                    'conversationId'  => $conversation->id,
                    'mediaPath'       => getFilePath('conversation')
                ]));
            }
        }
    }

    private function checkConversationLastMessage($conversation)
    {
        $lastMessage = Message::latest('id')->where('conversation_id', $conversation->id)->skip(1)->first();
        if($lastMessage && $lastMessage->created_at->diffInHours(Carbon::now()) > 24) {
            $conversation->needs_human_reply = Status::NO;
            $conversation->save();
        }
    }

    /**
     * Verifica se a resposta da IA indica que ela não tem conhecimento sobre o assunto
     * @param string $response Resposta da IA
     * @return bool True se a IA não sabe a resposta
     */
    private function aiResponseIndicatesNoKnowledge($response)
    {
        $response = strtolower(trim($response));
        
        // Frases em português que indicam falta de conhecimento
        $noKnowledgePhrasesPt = [
            'não tenho',
            'não possuo',
            'não sei',
            'não consigo',
            'não encontrei',
            'não há informações',
            'não há dados',
            'não disponho',
            'informação não disponível',
            'sem informações',
            'desculpe, não',
            'lamento, não',
            'infelizmente não',
            'não estou apto',
            'base de conhecimento não',
            'base de dados não'
        ];
        
        // Frases em inglês que indicam falta de conhecimento
        $noKnowledgePhrasesEn = [
            "i don't have",
            "i don't know",
            "i cannot",
            "i can't",
            "no information",
            "not available",
            "unable to",
            "sorry, i",
            "unfortunately",
            "i do not have",
            "information not found",
            "no data",
            "knowledge base does not"
        ];
        
        $allPhrases = array_merge($noKnowledgePhrasesPt, $noKnowledgePhrasesEn);
        
        // Verifica se a resposta contém alguma das frases de "não sei"
        foreach ($allPhrases as $phrase) {
            if (stripos($response, $phrase) !== false) {
                return true;
            }
        }
        
        // Se a resposta for muito curta (menos de 15 caracteres) e começar com não/no
        if (strlen($response) < 15 && (
            strpos($response, 'não') === 0 || 
            strpos($response, 'no ') === 0 ||
            $response === 'no' ||
            $response === 'não'
        )) {
            return true;
        }
        
        return false;
    }
}
