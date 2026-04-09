<?php

namespace App\Controllers\Api;

use App\Models\TelegramSettingModel;
use App\Services\TelegramService;
use CodeIgniter\HTTP\ResponseInterface;

class TelegramController extends BaseApiController
{
    private TelegramSettingModel $settingsModel;
    private TelegramService $telegram;

    public function __construct()
    {
        $this->settingsModel = new TelegramSettingModel();
        $this->telegram = new TelegramService();
    }

    public function settings(): ResponseInterface
    {
        $settings = $this->settingsModel->first();
        if (! $settings) {
            $settings = [
                'bot_token' => null,
                'chat_id'   => null,
                'is_active' => 0,
            ];
        }

        return $this->ok($settings);
    }

    public function updateSettings(): ResponseInterface
    {
        $data = $this->requestData();

        $rules = [
            'bot_token' => 'permit_empty|max_length[255]',
            'chat_id'   => 'permit_empty|max_length[100]',
            'is_active' => 'required|in_list[0,1]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->validationErrorResponse($this->validator->getErrors());
        }

        $payload = [
            'bot_token' => $data['bot_token'] ?? null,
            'chat_id'   => $data['chat_id'] ?? null,
            'is_active' => (int) ($data['is_active'] ?? 0),
        ];

        $existing = $this->settingsModel->first();
        if ($existing) {
            $this->settingsModel->update($existing['id'], $payload);
            $updated = $this->settingsModel->find($existing['id']);
        } else {
            $id = $this->settingsModel->insert($payload, true);
            $updated = $this->settingsModel->find($id);
        }

        return $this->ok($updated);
    }

    public function test(): ResponseInterface
    {
        $data = $this->requestData();
        $message = $data['message'] ?? 'Test notification dari ChatGPT Monitoring';

        $result = $this->telegram->sendMessage($message);
        if (! $result['success']) {
            return $this->failMessage($result['message'], 400);
        }

        return $this->ok($result);
    }
}
