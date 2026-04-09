<?php

namespace App\Controllers\Api;

use App\Models\AccountModel;
use App\Models\AccountUsageModel;
use App\Models\SubscriptionModel;
use App\Services\SubscriptionStatusService;
use CodeIgniter\HTTP\ResponseInterface;

class SubscriptionsController extends BaseApiController
{
    private SubscriptionModel $subscriptions;
    private AccountModel $accounts;
    private AccountUsageModel $usages;

    public function __construct()
    {
        $this->subscriptions = new SubscriptionModel();
        $this->accounts = new AccountModel();
        $this->usages = new AccountUsageModel();
    }

    public function index(): ResponseInterface
    {
        $rows = $this->subscriptions
            ->select('subscriptions.*, accounts.account_name, accounts.email')
            ->join('accounts', 'accounts.id = subscriptions.account_id')
            ->orderBy('subscriptions.expired_at', 'ASC')
            ->findAll();

        foreach ($rows as &$row) {
            $status = SubscriptionStatusService::resolveStatus($row['expired_at']);
            if ($row['status'] !== $status) {
                $this->subscriptions->update($row['id'], ['status' => $status]);
                $row['status'] = $status;
            }

            $row['usages'] = $this->usages->where('subscription_id', $row['id'])->findAll();
        }

        return $this->ok($rows);
    }

    public function show(int $id): ResponseInterface
    {
        $row = $this->subscriptions->find($id);
        if (! $row) {
            return $this->failMessage('Subscription tidak ditemukan.', 404);
        }

        $status = SubscriptionStatusService::resolveStatus($row['expired_at']);
        if ($row['status'] !== $status) {
            $this->subscriptions->update($row['id'], ['status' => $status]);
            $row['status'] = $status;
        }

        $row['usages'] = $this->usages->where('subscription_id', $id)->findAll();

        return $this->ok($row);
    }

    public function create(): ResponseInterface
    {
        $data = $this->requestData();

        $rules = [
            'account_id'         => 'required|integer',
            'store_source'       => 'required|max_length[100]',
            'subscription_type'  => 'required|max_length[100]',
            'expired_at'         => 'required|valid_date[Y-m-d H:i:s]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->validationErrorResponse($this->validator->getErrors());
        }

        if (! $this->accounts->find((int) $data['account_id'])) {
            return $this->failMessage('Account tidak ditemukan.', 404);
        }

        $status = SubscriptionStatusService::resolveStatus($data['expired_at']);

        $id = $this->subscriptions->insert([
            'account_id'        => (int) $data['account_id'],
            'store_source'      => $data['store_source'],
            'subscription_type' => $data['subscription_type'],
            'expired_at'        => $data['expired_at'],
            'status'            => $status,
        ], true);

        $this->usages->insert([
            'subscription_id'   => $id,
            'usage_type'        => '5h',
            'remaining_percent' => 100,
            'reset_at'          => $data['expired_at'],
        ]);

        $this->usages->insert([
            'subscription_id'   => $id,
            'usage_type'        => 'weekly',
            'remaining_percent' => 100,
            'reset_at'          => $data['expired_at'],
        ]);

        return $this->ok($this->subscriptions->find($id), 201);
    }

    public function update(int $id): ResponseInterface
    {
        $row = $this->subscriptions->find($id);
        if (! $row) {
            return $this->failMessage('Subscription tidak ditemukan.', 404);
        }

        $data = $this->requestData();

        $rules = [
            'store_source'      => 'required|max_length[100]',
            'subscription_type' => 'required|max_length[100]',
            'expired_at'        => 'required|valid_date[Y-m-d H:i:s]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->validationErrorResponse($this->validator->getErrors());
        }

        $status = SubscriptionStatusService::resolveStatus($data['expired_at']);

        $this->subscriptions->update($id, [
            'store_source'      => $data['store_source'],
            'subscription_type' => $data['subscription_type'],
            'expired_at'        => $data['expired_at'],
            'status'            => $status,
        ]);

        return $this->ok($this->subscriptions->find($id));
    }

    public function delete(int $id): ResponseInterface
    {
        $row = $this->subscriptions->find($id);
        if (! $row) {
            return $this->failMessage('Subscription tidak ditemukan.', 404);
        }

        $this->subscriptions->delete($id);

        return $this->ok(['deleted' => true]);
    }
}
