<?php

namespace App\Controllers;

use App\Models\AccountModel;
use App\Models\AccountUsageHistoryModel;
use App\Models\AccountUsageModel;
use App\Models\SubscriptionModel;
use App\Models\TelegramSettingModel;
use App\Services\SubscriptionStatusService;
use App\Services\TelegramService;
use CodeIgniter\HTTP\RedirectResponse;

class WebController extends BaseController
{
    private AccountModel $accounts;
    private SubscriptionModel $subscriptions;
    private AccountUsageModel $usages;
    private AccountUsageHistoryModel $histories;
    private TelegramSettingModel $telegramSettings;

    public function __construct()
    {
        $this->accounts = new AccountModel();
        $this->subscriptions = new SubscriptionModel();
        $this->usages = new AccountUsageModel();
        $this->histories = new AccountUsageHistoryModel();
        $this->telegramSettings = new TelegramSettingModel();
    }

    public function dashboard(): string
    {
        $accounts = $this->accounts->findAll();
        $accountMap = [];
        foreach ($accounts as $account) {
            $accountMap[$account['id']] = $account;
        }

        $subscriptions = $this->enrichedSubscriptions($this->subscriptions->orderBy('expired_at', 'ASC')->findAll());

        $summary = [
            'total_accounts' => count($accounts),
            'active'         => 0,
            'expiring_soon'  => 0,
            'expired'        => 0,
        ];

        foreach ($subscriptions as $subscription) {
            if (isset($summary[$subscription['status']])) {
                $summary[$subscription['status']]++;
            }
        }

        return view('dashboard', [
            'summary'       => $summary,
            'subscriptions' => $subscriptions,
            'accountMap'    => $accountMap,
            'pageTitle'     => 'Dashboard',
        ]);
    }

    public function accountsIndex(): string
    {
        $accounts = $this->accounts->orderBy('id', 'DESC')->findAll();
        foreach ($accounts as &$account) {
            $account['subscriptions'] = $this->enrichedSubscriptions(
                $this->subscriptions->where('account_id', $account['id'])->orderBy('expired_at', 'ASC')->findAll()
            );
        }

        return view('accounts/index', [
            'accounts'   => $accounts,
            'pageTitle'  => 'Account List',
        ]);
    }

    public function accountDetail(int $id): string
    {
        $account = $this->accounts->find($id);
        if (! $account) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Account tidak ditemukan');
        }

        $subscriptions = $this->enrichedSubscriptions(
            $this->subscriptions->where('account_id', $id)->orderBy('expired_at', 'ASC')->findAll()
        );

        $history = $this->histories
            ->select('account_usage_histories.*, account_usages.usage_type, account_usages.subscription_id')
            ->join('account_usages', 'account_usages.id = account_usage_histories.account_usage_id')
            ->join('subscriptions', 'subscriptions.id = account_usages.subscription_id')
            ->where('subscriptions.account_id', $id)
            ->orderBy('account_usage_histories.created_at', 'DESC')
            ->findAll();

        return view('accounts/detail', [
            'account'       => $account,
            'subscriptions' => $subscriptions,
            'history'       => $history,
            'pageTitle'     => 'Account Detail',
        ]);
    }

    public function createAccount(): RedirectResponse
    {
        $data = $this->request->getPost();

        $rules = [
            'account_name'      => 'required|min_length[2]|max_length[120]',
            'email'             => 'required|valid_email|is_unique[accounts.email]',
            'password_hint'     => 'permit_empty|max_length[255]',
            'notes'             => 'permit_empty',
            'store_source'      => 'required|max_length[100]',
            'subscription_type' => 'required|max_length[100]',
            'expired_at'        => 'required|valid_date[Y-m-d\\TH:i]',
        ];

        if (! $this->validateData($data, $rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $accountId = $this->accounts->insert([
            'account_name'  => $data['account_name'],
            'email'         => $data['email'],
            'password_hint' => $data['password_hint'] ?? null,
            'notes'         => $data['notes'] ?? null,
        ], true);

        $expiredAt = date('Y-m-d H:i:s', strtotime($data['expired_at']));
        $status = SubscriptionStatusService::resolveStatus($expiredAt);

        $subscriptionId = $this->subscriptions->insert([
            'account_id'        => $accountId,
            'store_source'      => $data['store_source'],
            'subscription_type' => $data['subscription_type'],
            'expired_at'        => $expiredAt,
            'status'            => $status,
        ], true);

        $this->usages->insert([
            'subscription_id'   => $subscriptionId,
            'usage_type'        => '5h',
            'remaining_percent' => 100,
            'reset_at'          => $expiredAt,
        ]);

        $this->usages->insert([
            'subscription_id'   => $subscriptionId,
            'usage_type'        => 'weekly',
            'remaining_percent' => 100,
            'reset_at'          => $expiredAt,
        ]);

        return redirect()->to('/accounts/' . $accountId)->with('success', 'Account & subscription berhasil dibuat.');
    }

    public function updateSubscription(int $id): RedirectResponse
    {
        $subscription = $this->subscriptions->find($id);
        if (! $subscription) {
            return redirect()->back()->with('error', 'Subscription tidak ditemukan.');
        }

        $data = $this->request->getPost();

        $rules = [
            'store_source'      => 'required|max_length[100]',
            'subscription_type' => 'required|max_length[100]',
            'expired_at'        => 'required|valid_date[Y-m-d\\TH:i]',
        ];

        if (! $this->validateData($data, $rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $expiredAt = date('Y-m-d H:i:s', strtotime($data['expired_at']));
        $status = SubscriptionStatusService::resolveStatus($expiredAt);

        $this->subscriptions->update($id, [
            'store_source'      => $data['store_source'],
            'subscription_type' => $data['subscription_type'],
            'expired_at'        => $expiredAt,
            'status'            => $status,
        ]);

        return redirect()->back()->with('success', 'Subscription berhasil diupdate.');
    }

    public function deleteAccount(int $id): RedirectResponse
    {
        if (! $this->accounts->find($id)) {
            return redirect()->back()->with('error', 'Account tidak ditemukan.');
        }

        $this->accounts->delete($id);

        return redirect()->to('/accounts')->with('success', 'Account berhasil dihapus.');
    }

    public function updateUsage(int $id): RedirectResponse
    {
        $usage = $this->usages->find($id);
        if (! $usage) {
            return redirect()->back()->with('error', 'Usage tidak ditemukan.');
        }

        $data = $this->request->getPost();

        $rules = [
            'remaining_percent' => 'required|integer|greater_than_equal_to[0]|less_than_equal_to[100]',
            'reset_at'          => 'required|valid_date[Y-m-d\\TH:i]',
        ];

        if (! $this->validateData($data, $rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $newPercent = (int) $data['remaining_percent'];
        $resetAt = date('Y-m-d H:i:s', strtotime($data['reset_at']));

        $this->histories->insert([
            'account_usage_id' => $usage['id'],
            'old_percent'      => (int) $usage['remaining_percent'],
            'new_percent'      => $newPercent,
            'created_at'       => date('Y-m-d H:i:s'),
        ]);

        $this->usages->update($id, [
            'remaining_percent' => $newPercent,
            'reset_at'          => $resetAt,
        ]);

        return redirect()->back()->with('success', 'Usage berhasil diupdate.');
    }

    public function telegramSettings(): string
    {
        $settings = $this->telegramSettings->first();

        return view('telegram/settings', [
            'settings'  => $settings,
            'pageTitle' => 'Telegram Settings',
        ]);
    }

    public function saveTelegramSettings(): RedirectResponse
    {
        $data = $this->request->getPost();

        $rules = [
            'bot_token' => 'permit_empty|max_length[255]',
            'chat_id'   => 'permit_empty|max_length[100]',
        ];

        if (! $this->validateData($data, $rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $payload = [
            'bot_token' => $data['bot_token'] ?? null,
            'chat_id'   => $data['chat_id'] ?? null,
            'is_active' => isset($data['is_active']) ? 1 : 0,
        ];

        $existing = $this->telegramSettings->first();
        if ($existing) {
            $this->telegramSettings->update($existing['id'], $payload);
        } else {
            $this->telegramSettings->insert($payload);
        }

        return redirect()->back()->with('success', 'Telegram settings tersimpan.');
    }

    public function telegramTest(): RedirectResponse
    {
        $telegram = new TelegramService();
        $result = $telegram->sendMessage('Test notification dari halaman Telegram Settings');

        if (! $result['success']) {
            return redirect()->back()->with('error', $result['message']);
        }

        return redirect()->back()->with('success', 'Test message terkirim.');
    }

    /**
     * @param array<int, array<string, mixed>> $subscriptions
     *
     * @return array<int, array<string, mixed>>
     */
    private function enrichedSubscriptions(array $subscriptions): array
    {
        if ($subscriptions === []) {
            return [];
        }

        $subscriptionIds = array_column($subscriptions, 'id');

        $usageRows = $this->usages->whereIn('subscription_id', $subscriptionIds)->findAll();
        $usageMap = [];

        foreach ($usageRows as $usage) {
            $usageMap[$usage['subscription_id']][$usage['usage_type']] = $usage;
        }

        foreach ($subscriptions as &$subscription) {
            $status = SubscriptionStatusService::resolveStatus($subscription['expired_at']);
            if ($subscription['status'] !== $status) {
                $this->subscriptions->update($subscription['id'], ['status' => $status]);
                $subscription['status'] = $status;
            }

            $subscription['usages'] = [
                '5h'     => $usageMap[$subscription['id']]['5h'] ?? null,
                'weekly' => $usageMap[$subscription['id']]['weekly'] ?? null,
            ];
        }

        return $subscriptions;
    }
}
