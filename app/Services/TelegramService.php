<?php

namespace App\Services;

use App\Models\TelegramSettingModel;

class TelegramService
{
    public function sendMessage(string $message): array
    {
        $settingsModel = new TelegramSettingModel();
        $settings = $settingsModel->first();

        if (! $settings || (int) $settings['is_active'] !== 1) {
            return [
                'success' => false,
                'message' => 'Telegram tidak aktif.',
            ];
        }

        if (empty($settings['bot_token']) || empty($settings['chat_id'])) {
            return [
                'success' => false,
                'message' => 'Bot token/chat id belum diisi.',
            ];
        }

        $url = 'https://api.telegram.org/bot' . $settings['bot_token'] . '/sendMessage';
        $payload = [
            'chat_id' => $settings['chat_id'],
            'text'    => $message,
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            return [
                'success' => false,
                'message' => $error !== '' ? $error : 'Telegram request gagal.',
            ];
        }

        return [
            'success' => true,
            'message' => 'Pesan terkirim.',
            'raw'     => $response,
        ];
    }
}
