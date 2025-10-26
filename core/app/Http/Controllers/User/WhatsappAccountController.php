<?php
namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\WhatsappAccount;
use App\Services\BaileysService;
use App\Traits\WhatsappAccountManager;
use Illuminate\Http\Request;

class WhatsappAccountController extends Controller
{
    use WhatsappAccountManager;

    protected BaileysService $baileysService;

    public function __construct(BaileysService $baileysService)
    {
        $this->baileysService = $baileysService;
    }

    public function addWhatsappAccount()
    {
        $pageTitle = "Add WhatsApp Account";
        return view('Template::user.whatsapp.add_waba_account', compact('pageTitle'));
    }

    public function whatsappAccountSetting($accountId)
    {
        $whatsappAccount = WhatsappAccount::where('user_id', auth()->id())->findOrFail($accountId);
        $pageTitle       = "WhatsApp Account Setting - " . $whatsappAccount->business_name;
        return view('Template::user.whatsapp.setting_waba_account', compact('pageTitle', 'whatsappAccount'));
    }

    /**
     * Start Baileys session and get QR code
     */
    public function baileysStartSession($accountId)
    {
        $whatsappAccount = WhatsappAccount::where('user_id', auth()->id())->findOrFail($accountId);

        // Create session ID if not exists
        if (!$whatsappAccount->baileys_session_id) {
            $whatsappAccount->baileys_session_id = 'session_' . $whatsappAccount->id . '_' . time();
        }

        // Reset connection status when starting new session
        $whatsappAccount->baileys_connected = false;
        $whatsappAccount->baileys_connected_at = null;
        $whatsappAccount->baileys_phone_number = null;
        $whatsappAccount->save();

        // Start session with userId for file organization
        $result = $this->baileysService->startSession($whatsappAccount->baileys_session_id, $whatsappAccount->user_id);

        if (!$result['success']) {
            return apiResponse('error', 'error', [$result['message']]);
        }

        // Configure webhook
        $webhookUrl = route('webhook.baileys');
        $this->baileysService->setWebhook($whatsappAccount->baileys_session_id, $webhookUrl);

        return apiResponse('success', 'success', [__('Session started successfully')]);
    }

    /**
     * Get QR code for Baileys session
     */
    public function baileysGetQR($accountId)
    {
        $whatsappAccount = WhatsappAccount::where('user_id', auth()->id())->findOrFail($accountId);

        if (!$whatsappAccount->baileys_session_id) {
            return response()->json([
                'success' => false,
                'message' => __('Session not started'),
            ]);
        }

        $result = $this->baileysService->getQRCode($whatsappAccount->baileys_session_id);

        return response()->json($result);
    }

    /**
     * Check Baileys session status
     */
    public function baileysCheckStatus($accountId)
    {
        $whatsappAccount = WhatsappAccount::where('user_id', auth()->id())->findOrFail($accountId);

        if (!$whatsappAccount->baileys_session_id) {
            return response()->json([
                'success' => false,
                'connected' => false,
                'message' => __('Session not started'),
            ]);
        }

        $result = $this->baileysService->getSessionStatus($whatsappAccount->baileys_session_id);

        if ($result['success']) {
            if ($result['connected']) {
                // Update database when connected
                $whatsappAccount->baileys_connected = true;
                $whatsappAccount->baileys_connected_at = now();
                if (isset($result['user']['id'])) {
                    $whatsappAccount->baileys_phone_number = $result['user']['id'];
                }
            } else {
                // Clear database when disconnected
                $whatsappAccount->baileys_connected = false;
                $whatsappAccount->baileys_connected_at = null;
                $whatsappAccount->baileys_phone_number = null;
            }
            $whatsappAccount->save();
        }

        return response()->json($result);
    }

    /**
     * Disconnect Baileys session
     */
    public function baileysDisconnect($accountId)
    {
        $whatsappAccount = WhatsappAccount::where('user_id', auth()->id())->findOrFail($accountId);

        if (!$whatsappAccount->baileys_session_id) {
            return apiResponse('error', 'error', [__('Session not found')]);
        }

        $result = $this->baileysService->deleteSession($whatsappAccount->baileys_session_id);

        if ($result['success']) {
            $whatsappAccount->baileys_connected = false;
            $whatsappAccount->baileys_connected_at = null;
            $whatsappAccount->baileys_phone_number = null;
            $whatsappAccount->save();

            return apiResponse('success', 'success', [__('Disconnected successfully')]);
        }

        return apiResponse('error', 'error', [$result['message']]);
    }

    /**
     * Update connection type (Meta API = 1, Baileys = 2)
     */
    public function updateConnectionType(Request $request, $accountId)
    {
        $whatsappAccount = WhatsappAccount::where('user_id', auth()->id())->findOrFail($accountId);

        $request->validate([
            'connection_type' => 'required|in:1,2'
        ]);

        $whatsappAccount->connection_type = $request->connection_type;
        $whatsappAccount->save();

        return response()->json([
            'success' => true,
            'message' => __('Connection type updated successfully'),
            'connection_type' => $whatsappAccount->connection_type
        ]);
    }
    
}
