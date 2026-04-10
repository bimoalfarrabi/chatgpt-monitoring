<?php

namespace App\Controllers\Api;

use App\Models\AccountUsageHistoryModel;
use App\Models\AccountUsageModel;
use CodeIgniter\HTTP\ResponseInterface;

class AccountUsagesController extends BaseApiController
{
    private AccountUsageModel $usages;
    private AccountUsageHistoryModel $histories;

    public function __construct()
    {
        $this->usages = new AccountUsageModel();
        $this->histories = new AccountUsageHistoryModel();
    }

    public function update(int $id): ResponseInterface
    {
        $usage = $this->usages->find($id);
        if (! $usage) {
            return $this->failMessage('Usage tidak ditemukan.', 404);
        }

        $data = $this->requestData();

        $rules = [
            'remaining_percent' => 'required|integer|greater_than_equal_to[0]|less_than_equal_to[100]',
            'reset_at'          => 'permit_empty|valid_date[Y-m-d H:i:s]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->validationErrorResponse($this->validator->getErrors());
        }

        $newPercent = (int) $data['remaining_percent'];
        $rawResetAt = trim((string) ($data['reset_at'] ?? ''));
        $normalizedResetAt = null;

        if ($newPercent < 100) {
            if ($rawResetAt === '') {
                return $this->failMessage('Waktu reset wajib diisi jika usage di bawah 100%.', 422);
            }

            if (date('Y-m-d', strtotime($rawResetAt)) < date('Y-m-d')) {
                return $this->failMessage('Waktu reset tidak boleh lebih tua dari tanggal hari ini.', 422);
            }

            $normalizedResetAt = $rawResetAt;
        }

        $this->histories->insert([
            'account_usage_id' => $id,
            'old_percent'      => (int) $usage['remaining_percent'],
            'new_percent'      => $newPercent,
            'created_at'       => date('Y-m-d H:i:s'),
        ]);

        $this->usages->update($id, [
            'remaining_percent' => $newPercent,
            'reset_at'          => $normalizedResetAt,
        ]);

        return $this->ok($this->usages->find($id));
    }
}
