<?php

namespace App\Controllers\Api;

use App\Models\AccountModel;
use App\Models\SubscriptionModel;
use App\Services\SubscriptionStatusService;
use CodeIgniter\HTTP\ResponseInterface;

class AccountsController extends BaseApiController
{
    private AccountModel $accounts;
    private SubscriptionModel $subscriptions;

    public function __construct()
    {
        $this->accounts = new AccountModel();
        $this->subscriptions = new SubscriptionModel();
    }

    public function index(): ResponseInterface
    {
        $accounts = $this->accounts->orderBy('id', 'DESC')->findAll();

        foreach ($accounts as &$account) {
            $account['subscriptions'] = $this->subscriptions
                ->where('account_id', $account['id'])
                ->orderBy('expired_at', 'ASC')
                ->findAll();

            foreach ($account['subscriptions'] as &$subscription) {
                $subscription = $this->normalizeSubscription($subscription);
            }
        }

        return $this->ok($accounts);
    }

    public function show(int $id): ResponseInterface
    {
        $account = $this->accounts->find($id);
        if (! $account) {
            return $this->failMessage('Account tidak ditemukan.', 404);
        }

        $account['subscriptions'] = $this->subscriptions
            ->where('account_id', $id)
            ->orderBy('expired_at', 'ASC')
            ->findAll();

        foreach ($account['subscriptions'] as &$subscription) {
            $subscription = $this->normalizeSubscription($subscription);
        }

        return $this->ok($account);
    }

    public function create(): ResponseInterface
    {
        $data = $this->requestData();

        $rules = [
            'account_name' => 'required|min_length[2]|max_length[120]',
            'email'        => 'required|valid_email|is_unique[accounts.email]',
            'password_hint'=> 'permit_empty|max_length[255]',
            'notes'        => 'permit_empty',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->validationErrorResponse($this->validator->getErrors());
        }

        $id = $this->accounts->insert([
            'account_name'  => $data['account_name'],
            'email'         => $data['email'],
            'password_hint' => $data['password_hint'] ?? null,
            'notes'         => $data['notes'] ?? null,
        ], true);

        return $this->ok($this->accounts->find($id), 201);
    }

    public function update(int $id): ResponseInterface
    {
        $account = $this->accounts->find($id);
        if (! $account) {
            return $this->failMessage('Account tidak ditemukan.', 404);
        }

        $data = $this->requestData();

        $rules = [
            'account_name' => 'required|min_length[2]|max_length[120]',
            'email'        => 'required|valid_email|max_length[160]',
            'password_hint'=> 'permit_empty|max_length[255]',
            'notes'        => 'permit_empty',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->validationErrorResponse($this->validator->getErrors());
        }

        $emailExists = $this->accounts
            ->where('email', $data['email'])
            ->where('id !=', $id)
            ->first();

        if ($emailExists) {
            return $this->failMessage('Email sudah dipakai account lain.', 422);
        }

        $this->accounts->update($id, [
            'account_name'  => $data['account_name'],
            'email'         => $data['email'],
            'password_hint' => $data['password_hint'] ?? null,
            'notes'         => $data['notes'] ?? null,
        ]);

        return $this->ok($this->accounts->find($id));
    }

    public function delete(int $id): ResponseInterface
    {
        $account = $this->accounts->find($id);
        if (! $account) {
            return $this->failMessage('Account tidak ditemukan.', 404);
        }

        $this->accounts->delete($id);

        return $this->ok(['deleted' => true]);
    }

    /**
     * @param array<string, mixed> $subscription
     *
     * @return array<string, mixed>
     */
    private function normalizeSubscription(array $subscription): array
    {
        $accountType = SubscriptionStatusService::normalizeAccountType($subscription['account_type'] ?? null);
        $proAccountType = SubscriptionStatusService::normalizeProAccountType($subscription['pro_account_type'] ?? null);
        $personalWorkspaceName = trim((string) ($subscription['personal_workspace_name'] ?? ''));
        $personalWorkspaceName = $personalWorkspaceName === '' ? null : $personalWorkspaceName;
        $isOneMonthDuration = SubscriptionStatusService::parseBoolean($subscription['is_one_month_duration'] ?? null);
        $isWorkspaceDeactivated = SubscriptionStatusService::parseBoolean($subscription['is_workspace_deactivated'] ?? null);
        $subscribedAt = $subscription['subscribed_at'] ?? null;

        if ($accountType !== 'pro') {
            $subscribedAt = null;
            $isOneMonthDuration = false;
            $isWorkspaceDeactivated = false;
            $proAccountType = null;
            $personalWorkspaceName = null;
        } elseif ($proAccountType !== 'personal_invite') {
            $personalWorkspaceName = null;
        }

        $expiredAt = $accountType === 'pro'
            ? SubscriptionStatusService::calculateExpiredAt($subscribedAt, $isOneMonthDuration)
            : null;

        $status = SubscriptionStatusService::resolveStatus($expiredAt, $isWorkspaceDeactivated);

        $updateData = [];
        if (($subscription['expired_at'] ?? null) !== $expiredAt) {
            $updateData['expired_at'] = $expiredAt;
        }
        if (($subscription['status'] ?? null) !== $status) {
            $updateData['status'] = $status;
        }

        if ($updateData !== []) {
            $this->subscriptions->update((int) $subscription['id'], $updateData);
        }

        $subscription['expired_at'] = $expiredAt;
        $subscription['status'] = $status;
        $subscription['pro_account_type'] = $proAccountType;
        $subscription['personal_workspace_name'] = $personalWorkspaceName;
        $subscription['usage_types'] = SubscriptionStatusService::usageTypes($accountType);

        return $subscription;
    }
}
