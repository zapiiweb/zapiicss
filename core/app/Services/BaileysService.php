<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BaileysService
{
    protected string $baseUrl;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = env('BAILEYS_API_URL', 'http://127.0.0.1:3001');
        $this->timeout = 30;
    }

    /**
     * Start a new WhatsApp session
     */
    public function startSession(string $sessionId, ?int $userId = null): array
    {
        try {
            $payload = [
                'sessionId' => $sessionId,
            ];
            
            // Include userId for file organization if provided
            if ($userId) {
                $payload['userId'] = $userId;
            }
            
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/session/start", $payload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'message' => $response->json()['error'] ?? 'Failed to start session',
            ];
        } catch (\Exception $e) {
            Log::error('Baileys startSession error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to connect to WhatsApp service: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get QR code for session
     */
    public function getQRCode(string $sessionId): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->get("{$this->baseUrl}/session/qr/{$sessionId}");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'qr' => $response->json()['qr'] ?? null,
                ];
            }

            return [
                'success' => false,
                'message' => $response->json()['error'] ?? 'QR code not available',
            ];
        } catch (\Exception $e) {
            Log::error('Baileys getQRCode error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to get QR code: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get session status
     */
    public function getSessionStatus(string $sessionId): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->get("{$this->baseUrl}/session/status/{$sessionId}");

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'connected' => $data['connected'] ?? false,
                    'hasQR' => $data['hasQR'] ?? false,
                    'user' => $data['user'] ?? null,
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to get session status',
            ];
        } catch (\Exception $e) {
            Log::error('Baileys getSessionStatus error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to get session status: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Delete session
     */
    public function deleteSession(string $sessionId): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->delete("{$this->baseUrl}/session/{$sessionId}");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Session deleted successfully',
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to delete session',
            ];
        } catch (\Exception $e) {
            Log::error('Baileys deleteSession error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to delete session: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Send message (ASYNC - returns immediately with job_id)
     */
    public function sendMessage(string $sessionId, string $to, string $message, array $options = []): array
    {
        try {
            // Build callback URL for Baileys to notify when sending completes
            $callbackUrl = route('webhook.baileys');
            
            $payload = [
                'sessionId' => $sessionId,
                'to' => $to,
                'message' => $message,
                'callbackUrl' => $callbackUrl,
            ];

            // Add media options if present
            if (isset($options['mediaType'])) {
                $payload['mediaType'] = $options['mediaType'];
                $payload['mediaUrl'] = $options['mediaUrl'] ?? null;
                $payload['mimeType'] = $options['mimeType'] ?? null;
                $payload['fileName'] = $options['fileName'] ?? null;
                $payload['caption'] = $options['caption'] ?? null;
            }

            // Short timeout (10s) - Baileys responds immediately with job_id
            // Actual sending happens in background
            $response = Http::timeout(10)
                ->post("{$this->baseUrl}/message/send", $payload);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'message' => 'Message is being sent in background',
                    'jobId' => $data['jobId'] ?? null,
                    'status' => $data['status'] ?? 'processing',
                ];
            }

            return [
                'success' => false,
                'message' => $response->json()['error'] ?? 'Failed to send message',
            ];
        } catch (\Exception $e) {
            Log::error('Baileys sendMessage error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send message: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Set webhook URL for session
     */
    public function setWebhook(string $sessionId, string $webhookUrl): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/webhook/set", [
                    'sessionId' => $sessionId,
                    'webhookUrl' => $webhookUrl,
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Webhook configured successfully',
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to set webhook',
            ];
        } catch (\Exception $e) {
            Log::error('Baileys setWebhook error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to set webhook: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check if Baileys service is healthy
     */
    public function isHealthy(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/health");
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
}
