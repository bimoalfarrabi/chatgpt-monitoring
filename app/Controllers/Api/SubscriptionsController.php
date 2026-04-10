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
            $row = $this->normalizeSubscriptionRow($row);
            $row['usages'] = $this->normalizeUsageRows(
                $this->usages->where('subscription_id', $row['id'])->findAll()
            );
        }

        return $this->ok($rows);
    }

    public function show(int $id): ResponseInterface
    {
        $row = $this->subscriptions->find($id);
        if (! $row) {
            return $this->failMessage('Subscription tidak ditemukan.', 404);
        }

        $row = $this->normalizeSubscriptionRow($row);
        $row['usages'] = $this->normalizeUsageRows(
            $this->usages->where('subscription_id', $id)->findAll()
        );

        return $this->ok($row);
    }

    public function create(): ResponseInterface
    {
        $data = $this->requestData();

        $rules = [
            'account_id'         => 'required|integer',
            'account_type'       => 'required|in_list[free,pro]',
            'pro_account_type'   => 'permit_empty|in_list[personal_invite,seller_account]',
            'workspace_name'     => 'permit_empty|max_length[120]',
            'personal_workspace_name' => 'permit_empty|max_length[120]',
            'is_workspace_deactivated' => 'permit_empty',
            'store_source'       => 'required|max_length[100]',
            'subscription_type'  => 'required|max_length[100]',
            'subscribed_at'      => 'permit_empty|max_length[40]',
            'is_one_month_duration' => 'permit_empty',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->validationErrorResponse($this->validator->getErrors());
        }

        if (! $this->accounts->find((int) $data['account_id'])) {
            return $this->failMessage('Account tidak ditemukan.', 404);
        }

        $subscriptionData = $this->buildSubscriptionPayload($data);
        if ($subscriptionData['error'] !== null) {
            return $this->failMessage($subscriptionData['error'], 422);
        }

        $id = $this->subscriptions->insert(array_merge([
            'account_id'        => (int) $data['account_id'],
            'store_source'      => $data['store_source'],
            'subscription_type' => $data['subscription_type'],
        ], $subscriptionData['payload']), true);

        $this->syncUsagesForSubscription(
            $id,
            $subscriptionData['account_type'],
            $subscriptionData['default_reset_at']
        );

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
            'account_type'       => 'required|in_list[free,pro]',
            'pro_account_type'   => 'permit_empty|in_list[personal_invite,seller_account]',
            'workspace_name'     => 'permit_empty|max_length[120]',
            'personal_workspace_name' => 'permit_empty|max_length[120]',
            'is_workspace_deactivated' => 'permit_empty',
            'store_source'      => 'required|max_length[100]',
            'subscription_type' => 'required|max_length[100]',
            'subscribed_at'      => 'permit_empty|max_length[40]',
            'is_one_month_duration' => 'permit_empty',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->validationErrorResponse($this->validator->getErrors());
        }

        $subscriptionData = $this->buildSubscriptionPayload($data);
        if ($subscriptionData['error'] !== null) {
            return $this->failMessage($subscriptionData['error'], 422);
        }

        $this->subscriptions->update($id, array_merge([
            'store_source'      => $data['store_source'],
            'subscription_type' => $data['subscription_type'],
        ], $subscriptionData['payload']));

        $this->syncUsagesForSubscription(
            $id,
            $subscriptionData['account_type'],
            $subscriptionData['default_reset_at']
        );

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

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalizeSubscriptionRow(array $row): array
    {
        $accountType = SubscriptionStatusService::normalizeAccountType($row['account_type'] ?? null);
        $proAccountType = SubscriptionStatusService::normalizeProAccountType($row['pro_account_type'] ?? null);
        $workspaceName = trim((string) ($row['workspace_name'] ?? ''));
        $workspaceName = $workspaceName === '' ? null : $workspaceName;
        $personalWorkspaceName = trim((string) ($row['personal_workspace_name'] ?? ''));
        $personalWorkspaceName = $personalWorkspaceName === '' ? null : $personalWorkspaceName;
        $isWorkspaceDeactivated = SubscriptionStatusService::parseBoolean($row['is_workspace_deactivated'] ?? null, false);
        $isOneMonthDuration = SubscriptionStatusService::parseBoolean($row['is_one_month_duration'] ?? null, false);
        $subscribedAt = $this->normalizeDateTimeInput($row['subscribed_at'] ?? null);

        if ($accountType !== 'pro') {
            $proAccountType = null;
            $workspaceName = null;
            $personalWorkspaceName = null;
            $isWorkspaceDeactivated = false;
            $subscribedAt = null;
            $isOneMonthDuration = false;
        } elseif ($proAccountType !== 'personal_invite') {
            $personalWorkspaceName = null;
        }

        $expiredAt = $accountType === 'pro'
            ? SubscriptionStatusService::calculateExpiredAt($subscribedAt, $isOneMonthDuration)
            : null;

        $status = SubscriptionStatusService::resolveStatus($expiredAt, $isWorkspaceDeactivated);

        $updateData = [];
        if (($row['account_type'] ?? null) !== $accountType) {
            $updateData['account_type'] = $accountType;
        }
        if (($row['pro_account_type'] ?? null) !== $proAccountType) {
            $updateData['pro_account_type'] = $proAccountType;
        }
        if (($row['workspace_name'] ?? null) !== $workspaceName) {
            $updateData['workspace_name'] = $workspaceName;
        }
        if (($row['personal_workspace_name'] ?? null) !== $personalWorkspaceName) {
            $updateData['personal_workspace_name'] = $personalWorkspaceName;
        }
        if ((int) ($row['is_workspace_deactivated'] ?? 0) !== ($isWorkspaceDeactivated ? 1 : 0)) {
            $updateData['is_workspace_deactivated'] = $isWorkspaceDeactivated ? 1 : 0;
        }
        if (($row['subscribed_at'] ?? null) !== $subscribedAt) {
            $updateData['subscribed_at'] = $subscribedAt;
        }
        if ((int) ($row['is_one_month_duration'] ?? 0) !== ($isOneMonthDuration ? 1 : 0)) {
            $updateData['is_one_month_duration'] = $accountType === 'pro' ? ($isOneMonthDuration ? 1 : 0) : null;
        }
        if (($row['expired_at'] ?? null) !== $expiredAt) {
            $updateData['expired_at'] = $expiredAt;
        }
        if (($row['status'] ?? null) !== $status) {
            $updateData['status'] = $status;
        }

        if ($updateData !== []) {
            $this->subscriptions->update((int) $row['id'], $updateData);
        }

        $row['account_type'] = $accountType;
        $row['pro_account_type'] = $proAccountType;
        $row['workspace_name'] = $workspaceName;
        $row['personal_workspace_name'] = $personalWorkspaceName;
        $row['is_workspace_deactivated'] = $isWorkspaceDeactivated ? 1 : 0;
        $row['subscribed_at'] = $subscribedAt;
        $row['is_one_month_duration'] = $accountType === 'pro' ? ($isOneMonthDuration ? 1 : 0) : null;
        $row['expired_at'] = $expiredAt;
        $row['status'] = $status;
        $row['usage_types'] = SubscriptionStatusService::usageTypes($accountType);

        return $row;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{
     *     payload: array<string, mixed>,
     *     account_type: string,
     *     default_reset_at: string,
     *     error: string|null
     * }
     */
    private function buildSubscriptionPayload(array $data): array
    {
        $accountType = SubscriptionStatusService::normalizeAccountType($data['account_type'] ?? null);
        $proAccountType = SubscriptionStatusService::normalizeProAccountType($data['pro_account_type'] ?? null);
        $workspaceName = trim((string) ($data['workspace_name'] ?? ''));
        $workspaceName = $workspaceName === '' ? null : $workspaceName;
        $personalWorkspaceName = trim((string) ($data['personal_workspace_name'] ?? ''));
        $personalWorkspaceName = $personalWorkspaceName === '' ? null : $personalWorkspaceName;
        $isWorkspaceDeactivated = SubscriptionStatusService::parseBoolean($data['is_workspace_deactivated'] ?? null, false);
        $isOneMonthDuration = SubscriptionStatusService::parseBoolean($data['is_one_month_duration'] ?? null, false);
        $subscribedAt = $this->normalizeDateTimeInput($data['subscribed_at'] ?? null);

        if ($accountType === 'pro') {
            if ($proAccountType === null) {
                return [
                    'payload' => [],
                    'account_type' => $accountType,
                    'default_reset_at' => date('Y-m-d H:i:s'),
                    'error' => 'Jenis akun pro wajib dipilih (invite pribadi atau akun seller).',
                ];
            }

            if ($workspaceName === null) {
                return [
                    'payload' => [],
                    'account_type' => $accountType,
                    'default_reset_at' => date('Y-m-d H:i:s'),
                    'error' => 'Nama workspace wajib diisi untuk akun pro.',
                ];
            }

            if ($subscribedAt === null) {
                return [
                    'payload' => [],
                    'account_type' => $accountType,
                    'default_reset_at' => date('Y-m-d H:i:s'),
                    'error' => 'Tanggal langganan wajib diisi untuk akun pro.',
                ];
            }

            if ($proAccountType === 'personal_invite' && $personalWorkspaceName === null) {
                return [
                    'payload' => [],
                    'account_type' => $accountType,
                    'default_reset_at' => date('Y-m-d H:i:s'),
                    'error' => 'Workspace personal (akun free) wajib diisi untuk tipe invite akun pribadi.',
                ];
            }

            if ($proAccountType !== 'personal_invite') {
                $personalWorkspaceName = null;
            }
        } else {
            $proAccountType = null;
            $workspaceName = null;
            $personalWorkspaceName = null;
            $isWorkspaceDeactivated = false;
            $subscribedAt = null;
            $isOneMonthDuration = false;
        }

        $expiredAt = $accountType === 'pro'
            ? SubscriptionStatusService::calculateExpiredAt($subscribedAt, $isOneMonthDuration)
            : null;

        $status = SubscriptionStatusService::resolveStatus($expiredAt, $isWorkspaceDeactivated);

        return [
            'payload' => [
                'account_type' => $accountType,
                'pro_account_type' => $proAccountType,
                'workspace_name' => $workspaceName,
                'personal_workspace_name' => $personalWorkspaceName,
                'is_workspace_deactivated' => $isWorkspaceDeactivated ? 1 : 0,
                'subscribed_at' => $subscribedAt,
                'is_one_month_duration' => $accountType === 'pro' ? ($isOneMonthDuration ? 1 : 0) : null,
                'expired_at' => $expiredAt,
                'status' => $status,
            ],
            'account_type' => $accountType,
            'default_reset_at' => $subscribedAt ?? date('Y-m-d H:i:s'),
            'error' => null,
        ];
    }

    private function normalizeDateTimeInput(mixed $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function syncUsagesForSubscription(int $subscriptionId, string $accountType, string $defaultResetAt): void
    {
        $requiredTypes = SubscriptionStatusService::usageTypes($accountType);
        $existingRows = $this->usages->where('subscription_id', $subscriptionId)->findAll();
        $existingByType = [];

        foreach ($existingRows as $row) {
            $existingByType[$row['usage_type']] = $row;
        }

        foreach ($requiredTypes as $usageType) {
            if (! isset($existingByType[$usageType])) {
                $this->usages->insert([
                    'subscription_id' => $subscriptionId,
                    'usage_type' => $usageType,
                    'remaining_percent' => 100,
                    'reset_at' => null,
                ]);
            }
        }

        foreach ($existingRows as $row) {
            if (! in_array($row['usage_type'], $requiredTypes, true)) {
                $this->usages->delete($row['id']);
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizeUsageRows(array $rows): array
    {
        foreach ($rows as &$usage) {
            if ((int) ($usage['remaining_percent'] ?? 0) >= 100 && ($usage['reset_at'] ?? null) !== null) {
                $this->usages->update((int) $usage['id'], ['reset_at' => null]);
                $usage['reset_at'] = null;
            }
        }

        return $rows;
    }
}
