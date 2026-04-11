<?php

namespace App\Controllers;

use App\Models\AccountModel;
use App\Models\AccountUsageHistoryModel;
use App\Models\AccountUsageModel;
use App\Models\SubscriptionModel;
use App\Models\SubscriptionRenewalHistoryModel;
use App\Models\TelegramSettingModel;
use App\Models\UserModel;
use App\Services\SubscriptionStatusService;
use App\Services\TelegramService;
use CodeIgniter\HTTP\RedirectResponse;

class WebController extends BaseController
{
    private const HISTORY_PER_PAGE = 5;
    private const USAGE_CHART_PALETTE = [
        '#1f8a65',
        '#cf2d56',
        '#2f6db5',
        '#9d6bcf',
        '#c08532',
        '#2f7b7b',
        '#bf4e30',
        '#6b6fda',
        '#7f9152',
        '#5f4a83',
        '#b35f8a',
        '#4d8f76',
    ];

    private AccountModel $accounts;
    private SubscriptionModel $subscriptions;
    private SubscriptionRenewalHistoryModel $renewalHistories;
    private AccountUsageModel $usages;
    private AccountUsageHistoryModel $histories;
    private TelegramSettingModel $telegramSettings;
    private UserModel $users;

    public function __construct()
    {
        $this->accounts = new AccountModel();
        $this->subscriptions = new SubscriptionModel();
        $this->renewalHistories = new SubscriptionRenewalHistoryModel();
        $this->usages = new AccountUsageModel();
        $this->histories = new AccountUsageHistoryModel();
        $this->telegramSettings = new TelegramSettingModel();
        $this->users = new UserModel();
    }

    public function dashboard(): string
    {
        $accounts = $this->accounts->findAll();
        $accountMap = [];
        foreach ($accounts as $account) {
            $accountMap[$account['id']] = $account;
        }

        $subscriptions = $this->enrichedSubscriptions(
            $this->subscriptions->where('account_type', 'pro')->orderBy('expired_at', 'ASC')->findAll()
        );
        $freeSubscriptions = $this->enrichedSubscriptions(
            $this->subscriptions->where('account_type', 'free')->orderBy('updated_at', 'DESC')->findAll()
        );

        $summary = [
            'total_accounts' => count($accounts),
            'active'         => 0,
            'expiring_soon'  => 0,
            'expired'        => 0,
            'deactivated'    => 0,
        ];

        foreach ($subscriptions as $subscription) {
            if (isset($summary[$subscription['status']])) {
                $summary[$subscription['status']]++;
            }
        }

        return view('dashboard', [
            'summary'           => $summary,
            'subscriptions'     => $subscriptions,
            'freeSubscriptions' => $freeSubscriptions,
            'accountMap'        => $accountMap,
            'pageTitle'         => 'Dashboard',
        ]);
    }

    public function accountsIndex(): string
    {
        $accounts = $this->accounts->orderBy('id', 'DESC')->findAll();
        foreach ($accounts as &$account) {
            $account['subscriptions'] = $this->enrichedSubscriptions(
                $this->subscriptions
                    ->where('account_id', $account['id'])
                    ->orderBy('expired_at', 'ASC')
                    ->findAll()
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
            $this->subscriptions
                ->where('account_id', $id)
                ->orderBy('expired_at', 'ASC')
                ->findAll()
        );

        $workspaceHistoryPage = $this->workspaceHistoryPage(
            $subscriptions,
            $this->normalizePage($this->request->getGet('workspace_page')),
            self::HISTORY_PER_PAGE
        );
        $renewalHistoryPage = $this->renewalHistoryPage(
            $id,
            $this->normalizePage($this->request->getGet('renewal_page')),
            self::HISTORY_PER_PAGE
        );
        $usageHistoryPage = $this->usageHistoryPage(
            $id,
            $this->normalizePage($this->request->getGet('usage_page')),
            self::HISTORY_PER_PAGE
        );

        return view('accounts/detail', [
            'account'              => $account,
            'subscriptions'        => $subscriptions,
            'workspaceHistoryPage' => $workspaceHistoryPage,
            'renewalHistoryPage'   => $renewalHistoryPage,
            'usageHistoryPage'     => $usageHistoryPage,
            'pageTitle'            => 'Account Detail',
        ]);
    }

    public function accountHistory(int $id, string $section)
    {
        $account = $this->accounts->find($id);
        if (! $account) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Account tidak ditemukan.',
            ]);
        }

        $page = $this->normalizePage($this->request->getGet('page'));
        $section = strtolower(trim($section));

        $view = null;
        $viewData = [];
        $pagination = [];

        if ($section === 'workspace') {
            $subscriptions = $this->enrichedSubscriptions(
                $this->subscriptions
                    ->where('account_id', $id)
                    ->orderBy('expired_at', 'ASC')
                    ->findAll()
            );

            $pageData = $this->workspaceHistoryPage($subscriptions, $page, self::HISTORY_PER_PAGE);
            $view = 'accounts/partials/history_workspace';
            $viewData = ['workspaceHistory' => $pageData['rows'], 'pagination' => $pageData['pagination']];
            $pagination = $pageData['pagination'];
        } elseif ($section === 'renewal') {
            $pageData = $this->renewalHistoryPage($id, $page, self::HISTORY_PER_PAGE);
            $view = 'accounts/partials/history_renewal';
            $viewData = ['renewalHistory' => $pageData['rows'], 'pagination' => $pageData['pagination']];
            $pagination = $pageData['pagination'];
        } elseif ($section === 'usage') {
            $pageData = $this->usageHistoryPage($id, $page, self::HISTORY_PER_PAGE);
            $view = 'accounts/partials/history_usage';
            $viewData = ['history' => $pageData['rows'], 'pagination' => $pageData['pagination']];
            $pagination = $pageData['pagination'];
        } else {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Section history tidak valid.',
            ]);
        }

        return $this->response->setJSON([
            'success' => true,
            'section' => $section,
            'pagination' => $pagination,
            'html' => view($view, $viewData),
        ]);
    }

    public function dashboardUsageChart()
    {
        $date = $this->normalizeChartDate($this->request->getGet('date'));

        return $this->response->setJSON([
            'success' => true,
            'date' => $date,
            'data' => $this->buildDashboardUsageChartDataByDate($date),
        ]);
    }

    public function accountUsageChart(int $id)
    {
        $account = $this->accounts->find($id);
        if (! $account) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Account tidak ditemukan.',
            ]);
        }

        $date = $this->normalizeChartDate($this->request->getGet('date'));

        return $this->response->setJSON([
            'success' => true,
            'date' => $date,
            'data' => $this->buildAccountUsageChartDataByDate($id, $date),
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
            'account_type'      => 'required|in_list[free,pro]',
            'pro_account_type'  => 'permit_empty|in_list[personal_invite,seller_account]',
            'workspace_name'    => 'permit_empty|max_length[120]',
            'personal_workspace_name' => 'permit_empty|max_length[120]',
            'is_workspace_deactivated' => 'permit_empty',
            'store_source'      => 'permit_empty|max_length[100]',
            'subscription_type' => 'permit_empty|max_length[100]',
            'subscribed_at'     => 'permit_empty|valid_date[Y-m-d\\TH:i]',
            'is_one_month_duration' => 'permit_empty',
        ];

        if (! $this->validateData($data, $rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $accountType = SubscriptionStatusService::normalizeAccountType($data['account_type'] ?? null);
        $subscriptionData = null;
        if ($accountType === 'pro') {
            if (trim((string) ($data['store_source'] ?? '')) === '') {
                return redirect()->back()->withInput()->with('error', 'Sumber store wajib diisi untuk akun pro.');
            }

            if (trim((string) ($data['subscription_type'] ?? '')) === '') {
                return redirect()->back()->withInput()->with('error', 'Tipe subscription wajib diisi untuk akun pro.');
            }

            $subscriptionData = $this->buildSubscriptionPayload($data);
            if ($subscriptionData['error'] !== null) {
                return redirect()->back()->withInput()->with('error', $subscriptionData['error']);
            }
        }

        $accountId = $this->accounts->insert([
            'account_name'  => $data['account_name'],
            'email'         => $data['email'],
            'password_hint' => $data['password_hint'] ?? null,
            'notes'         => $data['notes'] ?? null,
        ], true);

        if ($accountType === 'pro' && $subscriptionData !== null) {
            $subscriptionId = $this->subscriptions->insert(array_merge([
                'account_id'        => $accountId,
                'store_source'      => $data['store_source'],
                'subscription_type' => $data['subscription_type'],
            ], $subscriptionData['payload']), true);

            $this->syncUsagesForSubscription(
                $subscriptionId,
                $subscriptionData['account_type'],
                $subscriptionData['pro_account_type'],
                $subscriptionData['default_reset_at']
            );
        } else {
            $freeTrackingId = $this->subscriptions->insert([
                'account_id' => $accountId,
                'account_type' => 'free',
                'pro_account_type' => null,
                'workspace_name' => null,
                'personal_workspace_name' => null,
                'is_workspace_deactivated' => 0,
                'store_source' => 'free_account',
                'subscription_type' => 'Free Weekly',
                'subscribed_at' => null,
                'is_one_month_duration' => 0,
                'expired_at' => null,
                'status' => 'active',
            ], true);

            $this->syncUsagesForSubscription(
                (int) $freeTrackingId,
                'free',
                null,
                date('Y-m-d H:i:s')
            );
        }

        $successMessage = $accountType === 'pro'
            ? 'Account & subscription berhasil dibuat.'
            : 'Account free berhasil dibuat dengan tracking weekly.';

        return redirect()->to('/accounts/' . $accountId)->with('success', $successMessage);
    }

    public function updateSubscription(int $id): RedirectResponse
    {
        $subscription = $this->subscriptions->find($id);
        if (! $subscription) {
            return redirect()->back()->with('error', 'Subscription tidak ditemukan.');
        }

        $data = $this->request->getPost();

        $rules = [
            'account_type'      => 'required|in_list[pro]',
            'pro_account_type'  => 'permit_empty|in_list[personal_invite,seller_account]',
            'workspace_name'    => 'permit_empty|max_length[120]',
            'personal_workspace_name' => 'permit_empty|max_length[120]',
            'is_workspace_deactivated' => 'permit_empty',
            'store_source'      => 'required|max_length[100]',
            'subscription_type' => 'required|max_length[100]',
            'subscribed_at'     => 'permit_empty|valid_date[Y-m-d\\TH:i]',
            'is_one_month_duration' => 'permit_empty',
        ];

        if (! $this->validateData($data, $rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $subscriptionData = $this->buildSubscriptionPayload($data);
        if ($subscriptionData['error'] !== null) {
            return redirect()->back()->withInput()->with('error', $subscriptionData['error']);
        }

        $this->subscriptions->update($id, array_merge([
            'store_source'      => $data['store_source'],
            'subscription_type' => $data['subscription_type'],
        ], $subscriptionData['payload']));

        $this->syncUsagesForSubscription(
            $id,
            $subscriptionData['account_type'],
            $subscriptionData['pro_account_type'],
            $subscriptionData['default_reset_at']
        );

        return redirect()->back()->with('success', 'Subscription berhasil diupdate.');
    }

    public function renewSubscription(int $id): RedirectResponse
    {
        $subscription = $this->subscriptions->find($id);
        if (! $subscription) {
            return redirect()->back()->with('error', 'Subscription tidak ditemukan.');
        }

        $accountType = SubscriptionStatusService::normalizeAccountType($subscription['account_type'] ?? null);
        $isWorkspaceDeactivated = SubscriptionStatusService::parseBoolean($subscription['is_workspace_deactivated'] ?? null);
        if ($accountType !== 'pro') {
            return redirect()->back()->with('error', 'Auto perpanjang hanya berlaku untuk akun pro.');
        }

        if ($isWorkspaceDeactivated) {
            return redirect()->back()->with('error', 'Workspace ini deactivated. Buat workspace baru, bukan perpanjang subscription lama.');
        }

        $nowTs = time();
        $oldExpiredAt = $subscription['expired_at'] ?? null;
        $oldExpiredTs = $oldExpiredAt ? strtotime((string) $oldExpiredAt) : false;

        $baseTs = ($oldExpiredTs !== false && $oldExpiredTs > $nowTs) ? $oldExpiredTs : $nowTs;
        $newExpiredTs = strtotime('+1 month', $baseTs);
        if ($newExpiredTs === false) {
            return redirect()->back()->with('error', 'Gagal menghitung tanggal perpanjangan subscription.');
        }

        $newExpiredAt = date('Y-m-d H:i:s', $newExpiredTs);
        $newSubscribedAt = date('Y-m-d H:i:s', strtotime('-1 month', $newExpiredTs));
        $newStatus = SubscriptionStatusService::resolveStatus($newExpiredAt, false);

        $this->subscriptions->update($id, [
            'subscribed_at' => $newSubscribedAt,
            'is_one_month_duration' => 1,
            'expired_at' => $newExpiredAt,
            'status' => $newStatus,
        ]);

        $this->renewalHistories->insert([
            'subscription_id' => $id,
            'old_expired_at' => $oldExpiredAt,
            'new_expired_at' => $newExpiredAt,
            'renewed_at' => date('Y-m-d H:i:s'),
        ]);

        return redirect()->back()->with('success', 'Subscription berhasil diperpanjang otomatis +1 bulan.');
    }

    public function createWorkspaceFromDeactivated(int $id): RedirectResponse
    {
        $sourceSubscription = $this->subscriptions->find($id);
        if (! $sourceSubscription) {
            return redirect()->back()->with('error', 'Subscription sumber tidak ditemukan.');
        }

        $accountType = SubscriptionStatusService::normalizeAccountType($sourceSubscription['account_type'] ?? null);
        $isWorkspaceDeactivated = SubscriptionStatusService::parseBoolean($sourceSubscription['is_workspace_deactivated'] ?? null);
        if ($accountType !== 'pro' || ! $isWorkspaceDeactivated) {
            return redirect()->back()->with('error', 'Workspace baru hanya bisa dibuat dari subscription pro yang status workspace-nya deactivated.');
        }

        $data = $this->request->getPost();
        $rules = [
            'store_source'      => 'required|max_length[100]',
            'subscription_type' => 'required|max_length[100]',
            'pro_account_type'  => 'required|in_list[personal_invite,seller_account]',
            'workspace_name'    => 'required|max_length[120]',
            'personal_workspace_name' => 'permit_empty|max_length[120]',
            'subscribed_at'     => 'required|valid_date[Y-m-d\\TH:i]',
            'is_one_month_duration' => 'permit_empty|in_list[0,1]',
        ];

        if (! $this->validateData($data, $rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $data['account_type'] = 'pro';
        $data['is_workspace_deactivated'] = 0;
        $subscriptionData = $this->buildSubscriptionPayload($data);
        if ($subscriptionData['error'] !== null) {
            return redirect()->back()->withInput()->with('error', $subscriptionData['error']);
        }

        $newSubscriptionId = $this->subscriptions->insert(array_merge([
            'account_id'        => (int) $sourceSubscription['account_id'],
            'store_source'      => $data['store_source'],
            'subscription_type' => $data['subscription_type'],
        ], $subscriptionData['payload']), true);

        $this->syncUsagesForSubscription(
            (int) $newSubscriptionId,
            'pro',
            $subscriptionData['pro_account_type'],
            $subscriptionData['default_reset_at']
        );

        return redirect()
            ->to('/accounts/' . (int) $sourceSubscription['account_id'])
            ->with('success', 'Workspace baru berhasil dibuat dari workspace yang deactivated.');
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
            'reset_at'          => 'permit_empty|valid_date[Y-m-d\\TH:i]',
        ];

        if (! $this->validateData($data, $rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $newPercent = (int) $data['remaining_percent'];
        $resetAt = null;
        $rawResetAt = trim((string) ($data['reset_at'] ?? ''));

        if ($newPercent < 100) {
            if ($rawResetAt === '') {
                return redirect()->back()->withInput()->with('error', 'Waktu reset wajib diisi jika usage di bawah 100%.');
            }

            $resetAt = date('Y-m-d H:i:s', strtotime($rawResetAt));
            if ($this->isPastDate($resetAt)) {
                return redirect()->back()->withInput()->with('error', 'Waktu reset tidak boleh lebih tua dari tanggal hari ini.');
            }
        }

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
        $userId = $this->currentUserId();
        $settings = $this->telegramSettings->where('user_id', $userId)->first();
        if (! $settings) {
            $settings = [
                'user_id'   => $userId,
                'bot_token' => null,
                'chat_id'   => null,
                'is_active' => 0,
            ];
        }

        return view('telegram/settings', [
            'settings'  => $settings,
            'pageTitle' => 'Telegram Settings',
        ]);
    }

    public function saveTelegramSettings(): RedirectResponse
    {
        $userId = $this->currentUserId();
        $data = $this->request->getPost();

        $rules = [
            'bot_token' => 'permit_empty|max_length[255]',
            'chat_id'   => 'permit_empty|max_length[100]',
        ];

        if (! $this->validateData($data, $rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $payload = [
            'user_id'   => $userId,
            'bot_token' => $data['bot_token'] ?? null,
            'chat_id'   => $data['chat_id'] ?? null,
            'is_active' => isset($data['is_active']) ? 1 : 0,
        ];

        $existing = $this->telegramSettings->where('user_id', $userId)->first();
        if ($existing) {
            $this->telegramSettings->update($existing['id'], $payload);
        } else {
            $this->telegramSettings->insert($payload);
        }

        return redirect()->back()->with('success', 'Telegram settings tersimpan.');
    }

    public function telegramTest(): RedirectResponse
    {
        $userId = $this->currentUserId();
        $telegram = new TelegramService();
        $result = $telegram->sendMessage('Test notification dari halaman Telegram Settings', $userId);

        if (! $result['success']) {
            return redirect()->back()->with('error', $result['message']);
        }

        return redirect()->back()->with('success', 'Test message terkirim.');
    }

    public function profile(): RedirectResponse|string
    {
        $user = $this->users->find($this->currentUserId());
        if (! $user) {
            return redirect()->to('/login')->with('error', 'User tidak ditemukan. Silakan login ulang.');
        }

        return view('profile/index', [
            'user'      => $user,
            'pageTitle' => 'Profile',
        ]);
    }

    public function updateProfile(): RedirectResponse
    {
        $userId = $this->currentUserId();
        $user = $this->users->find($userId);
        if (! $user) {
            return redirect()->to('/login')->with('error', 'User tidak ditemukan. Silakan login ulang.');
        }

        $data = $this->request->getPost();
        $data['name'] = trim((string) ($data['name'] ?? ''));
        $data['username'] = strtolower(trim((string) ($data['username'] ?? '')));
        $data['email'] = strtolower(trim((string) ($data['email'] ?? '')));
        $data['new_password'] = (string) ($data['new_password'] ?? '');
        $data['new_password_confirmation'] = (string) ($data['new_password_confirmation'] ?? '');

        $rules = [
            'name'                  => 'required|min_length[3]|max_length[120]',
            'username'              => 'required|min_length[3]|max_length[50]|alpha_dash',
            'email'                 => 'required|valid_email|max_length[160]',
            'new_password'          => 'permit_empty|min_length[8]|max_length[255]',
            'new_password_confirmation' => 'permit_empty|max_length[255]',
        ];

        if (! $this->validateData($data, $rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        if ($data['new_password'] !== '' && $data['new_password_confirmation'] !== $data['new_password']) {
            return redirect()->back()->withInput()->with('error', 'Konfirmasi password baru tidak sama.');
        }

        $usernameExists = $this->users
            ->where('username', $data['username'])
            ->where('id !=', $userId)
            ->first();
        if ($usernameExists) {
            return redirect()->back()->withInput()->with('error', 'Username sudah digunakan user lain.');
        }

        $emailExists = $this->users
            ->where('email', $data['email'])
            ->where('id !=', $userId)
            ->first();
        if ($emailExists) {
            return redirect()->back()->withInput()->with('error', 'Email sudah digunakan user lain.');
        }

        $payload = [
            'name'     => $data['name'],
            'username' => $data['username'],
            'email'    => $data['email'],
        ];

        if ($data['new_password'] !== '') {
            $payload['password_hash'] = password_hash($data['new_password'], PASSWORD_DEFAULT);
        }

        $this->users->update($userId, $payload);

        session()->set([
            'user_name'  => $payload['name'],
            'username'   => $payload['username'],
            'user_email' => $payload['email'],
        ]);

        return redirect()->to('/profile')->with('success', 'Profile berhasil diperbarui.');
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
            $usage = $this->normalizeUsageRow($usage);

            $usageMap[$usage['subscription_id']][$usage['usage_type']] = $usage;
        }

        foreach ($subscriptions as &$subscription) {
            $accountType = SubscriptionStatusService::normalizeAccountType($subscription['account_type'] ?? null);
            $isOneMonthDuration = SubscriptionStatusService::parseBoolean($subscription['is_one_month_duration'] ?? null);
            $isWorkspaceDeactivated = SubscriptionStatusService::parseBoolean($subscription['is_workspace_deactivated'] ?? null);
            $proAccountType = SubscriptionStatusService::normalizeProAccountType($subscription['pro_account_type'] ?? null);
            $workspaceName = trim((string) ($subscription['workspace_name'] ?? ''));
            $workspaceName = $workspaceName === '' ? null : $workspaceName;
            $personalWorkspaceName = trim((string) ($subscription['personal_workspace_name'] ?? ''));
            $personalWorkspaceName = $personalWorkspaceName === '' ? null : $personalWorkspaceName;

            if ($accountType !== 'pro') {
                $proAccountType = null;
                $workspaceName = null;
                $personalWorkspaceName = null;
                $subscription['subscribed_at'] = null;
                $isOneMonthDuration = false;
            } elseif ($proAccountType !== 'personal_invite') {
                $personalWorkspaceName = null;
            }

            $expiredAt = $accountType === 'pro'
                ? SubscriptionStatusService::calculateExpiredAt($subscription['subscribed_at'] ?? null, $isOneMonthDuration)
                : null;

            $status = SubscriptionStatusService::resolveStatus($expiredAt, $isWorkspaceDeactivated);

            $updateData = [];
            if (($subscription['status'] ?? null) !== $status) {
                $updateData['status'] = $status;
            }
            if (($subscription['expired_at'] ?? null) !== $expiredAt) {
                $updateData['expired_at'] = $expiredAt;
            }
            if (($subscription['account_type'] ?? null) !== $accountType) {
                $updateData['account_type'] = $accountType;
            }
            if (($subscription['pro_account_type'] ?? null) !== $proAccountType) {
                $updateData['pro_account_type'] = $proAccountType;
            }
            if (($subscription['workspace_name'] ?? null) !== $workspaceName) {
                $updateData['workspace_name'] = $workspaceName;
            }
            if (($subscription['personal_workspace_name'] ?? null) !== $personalWorkspaceName) {
                $updateData['personal_workspace_name'] = $personalWorkspaceName;
            }
            if ((int) ($subscription['is_workspace_deactivated'] ?? 0) !== ($isWorkspaceDeactivated ? 1 : 0)) {
                $updateData['is_workspace_deactivated'] = $isWorkspaceDeactivated ? 1 : 0;
            }
            if ((int) ($subscription['is_one_month_duration'] ?? 0) !== ($isOneMonthDuration ? 1 : 0)) {
                $updateData['is_one_month_duration'] = $isOneMonthDuration ? 1 : 0;
            }

            if ($updateData !== []) {
                $this->subscriptions->update($subscription['id'], $updateData);
            }

            $subscription['account_type'] = $accountType;
            $subscription['pro_account_type'] = $proAccountType;
            $subscription['workspace_name'] = $workspaceName;
            $subscription['personal_workspace_name'] = $personalWorkspaceName;
            $subscription['is_workspace_deactivated'] = $isWorkspaceDeactivated ? 1 : 0;
            $subscription['is_one_month_duration'] = $isOneMonthDuration ? 1 : 0;
            $subscription['expired_at'] = $expiredAt;
            $subscription['status'] = $status;
            $subscription['usage_types'] = SubscriptionStatusService::usageTypes($accountType, $proAccountType);
            $subscription['usages'] = [
                '5h'              => $usageMap[$subscription['id']]['5h'] ?? null,
                'weekly'          => $usageMap[$subscription['id']]['weekly'] ?? null,
                'weekly_personal' => $usageMap[$subscription['id']]['weekly_personal'] ?? null,
            ];
        }

        return $subscriptions;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{
     *     payload: array<string, mixed>,
     *     account_type: string,
     *     pro_account_type: string|null,
     *     default_reset_at: string,
     *     error: string|null
     * }
     */
    private function buildSubscriptionPayload(array $data): array
    {
        $accountType = SubscriptionStatusService::normalizeAccountType($data['account_type'] ?? null);
        if ($accountType !== 'pro') {
            return [
                'payload' => [],
                'account_type' => $accountType,
                'pro_account_type' => null,
                'default_reset_at' => date('Y-m-d H:i:s'),
                'error' => 'Akun free tidak termasuk subscription.',
            ];
        }

        $proAccountType = SubscriptionStatusService::normalizeProAccountType($data['pro_account_type'] ?? null);
        $workspaceName = trim((string) ($data['workspace_name'] ?? ''));
        $workspaceName = $workspaceName === '' ? null : $workspaceName;
        $personalWorkspaceName = trim((string) ($data['personal_workspace_name'] ?? ''));
        $personalWorkspaceName = $personalWorkspaceName === '' ? null : $personalWorkspaceName;

        $isWorkspaceDeactivated = SubscriptionStatusService::parseBoolean($data['is_workspace_deactivated'] ?? null, false);
        $isOneMonthDuration = SubscriptionStatusService::parseBoolean($data['is_one_month_duration'] ?? null, false);
        $subscribedAt = $this->normalizeDateTimeInput($data['subscribed_at'] ?? null);

        if ($proAccountType === null) {
            return [
                'payload' => [],
                'account_type' => $accountType,
                'pro_account_type' => null,
                'default_reset_at' => date('Y-m-d H:i:s'),
                'error' => 'Jenis akun pro wajib dipilih (invite pribadi atau akun seller).',
            ];
        }

        if ($workspaceName === null) {
            return [
                'payload' => [],
                'account_type' => $accountType,
                'pro_account_type' => $proAccountType,
                'default_reset_at' => date('Y-m-d H:i:s'),
                'error' => 'Nama workspace wajib diisi untuk akun pro.',
            ];
        }

        if ($subscribedAt === null) {
            return [
                'payload' => [],
                'account_type' => $accountType,
                'pro_account_type' => $proAccountType,
                'default_reset_at' => date('Y-m-d H:i:s'),
                'error' => 'Tanggal langganan wajib diisi untuk akun pro.',
            ];
        }

        if ($proAccountType === 'personal_invite' && $personalWorkspaceName === null) {
            return [
                'payload' => [],
                'account_type' => $accountType,
                'pro_account_type' => $proAccountType,
                'default_reset_at' => date('Y-m-d H:i:s'),
                'error' => 'Workspace personal (akun free) wajib diisi untuk tipe invite akun pribadi.',
            ];
        }

        if ($proAccountType !== 'personal_invite') {
            $personalWorkspaceName = null;
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
            'pro_account_type' => $proAccountType,
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

    private function syncUsagesForSubscription(int $subscriptionId, string $accountType, ?string $proAccountType, string $defaultResetAt): void
    {
        $requiredTypes = SubscriptionStatusService::usageTypes($accountType, $proAccountType);
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

    private function isPastDate(string $dateTime): bool
    {
        return date('Y-m-d', strtotime($dateTime)) < date('Y-m-d');
    }

    /**
     * @param array<string, mixed> $usage
     *
     * @return array<string, mixed>
     */
    private function normalizeUsageRow(array $usage): array
    {
        $remainingPercent = (int) ($usage['remaining_percent'] ?? 0);
        $resetAt = $usage['reset_at'] ?? null;

        if ($resetAt !== null) {
            $resetTimestamp = strtotime((string) $resetAt);

            if ($resetTimestamp !== false && $resetTimestamp <= time()) {
                $this->usages->update((int) $usage['id'], [
                    'remaining_percent' => 100,
                    'reset_at' => null,
                ]);
                $usage['remaining_percent'] = 100;
                $usage['reset_at'] = null;

                return $usage;
            }

            if ($remainingPercent >= 100) {
                $this->usages->update((int) $usage['id'], ['reset_at' => null]);
                $usage['reset_at'] = null;
            }
        }

        return $usage;
    }

    /**
     * @param array<int, array<string, mixed>> $subscriptions
     *
     * @return array<int, array<string, mixed>>
     */
    private function workspaceHistory(array $subscriptions): array
    {
        $rows = array_values(array_filter($subscriptions, static function (array $subscription): bool {
            return SubscriptionStatusService::normalizeAccountType($subscription['account_type'] ?? null) === 'pro';
        }));

        usort($rows, static function (array $a, array $b): int {
            $aTime = strtotime((string) ($a['created_at'] ?? $a['subscribed_at'] ?? '1970-01-01 00:00:00')) ?: 0;
            $bTime = strtotime((string) ($b['created_at'] ?? $b['subscribed_at'] ?? '1970-01-01 00:00:00')) ?: 0;
            return $bTime <=> $aTime;
        });

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $subscriptions
     *
     * @return array{
     *     rows: array<int, array<string, mixed>>,
     *     pagination: array<string, int|bool>
     * }
     */
    private function workspaceHistoryPage(array $subscriptions, int $page, int $perPage): array
    {
        $rows = $this->workspaceHistory($subscriptions);
        $pagination = $this->buildPaginationMeta(count($rows), $page, $perPage);
        $slice = array_slice($rows, $pagination['offset'], $perPage);
        unset($pagination['offset']);

        return [
            'rows' => $slice,
            'pagination' => $pagination,
        ];
    }

    /**
     * @return array{
     *     rows: array<int, array<string, mixed>>,
     *     pagination: array<string, int|bool>
     * }
     */
    private function renewalHistoryPage(int $accountId, int $page, int $perPage): array
    {
        $builder = db_connect()
            ->table('subscription_renewal_histories')
            ->select('subscription_renewal_histories.*, subscriptions.workspace_name, subscriptions.personal_workspace_name, subscriptions.subscription_type, subscriptions.pro_account_type')
            ->join('subscriptions', 'subscriptions.id = subscription_renewal_histories.subscription_id')
            ->where('subscriptions.account_id', $accountId)
            ->where('subscriptions.account_type', 'pro');

        $totalItems = (int) (clone $builder)->countAllResults();
        $pagination = $this->buildPaginationMeta($totalItems, $page, $perPage);

        $rows = $builder
            ->orderBy('subscription_renewal_histories.renewed_at', 'DESC')
            ->limit($perPage, $pagination['offset'])
            ->get()
            ->getResultArray();
        unset($pagination['offset']);

        return [
            'rows' => $rows,
            'pagination' => $pagination,
        ];
    }

    /**
     * @return array{
     *     rows: array<int, array<string, mixed>>,
     *     pagination: array<string, int|bool>
     * }
     */
    private function usageHistoryPage(int $accountId, int $page, int $perPage): array
    {
        $builder = db_connect()
            ->table('account_usage_histories')
            ->select('account_usage_histories.*, account_usages.usage_type, account_usages.subscription_id')
            ->join('account_usages', 'account_usages.id = account_usage_histories.account_usage_id')
            ->join('subscriptions', 'subscriptions.id = account_usages.subscription_id')
            ->where('subscriptions.account_id', $accountId);

        $totalItems = (int) (clone $builder)->countAllResults();
        $pagination = $this->buildPaginationMeta($totalItems, $page, $perPage);

        $rows = $builder
            ->orderBy('account_usage_histories.created_at', 'DESC')
            ->limit($perPage, $pagination['offset'])
            ->get()
            ->getResultArray();
        unset($pagination['offset']);

        return [
            'rows' => $rows,
            'pagination' => $pagination,
        ];
    }

    /**
     * @return array{
     *     current_page: int,
     *     per_page: int,
     *     total_items: int,
     *     total_pages: int,
     *     has_prev: bool,
     *     has_next: bool,
     *     offset: int
     * }
     */
    private function buildPaginationMeta(int $totalItems, int $page, int $perPage): array
    {
        $totalPages = max(1, (int) ceil($totalItems / $perPage));
        $currentPage = min(max($page, 1), $totalPages);

        return [
            'current_page' => $currentPage,
            'per_page' => $perPage,
            'total_items' => $totalItems,
            'total_pages' => $totalPages,
            'has_prev' => $currentPage > 1,
            'has_next' => $currentPage < $totalPages,
            'offset' => ($currentPage - 1) * $perPage,
        ];
    }

    private function normalizePage(mixed $value): int
    {
        $page = (int) $value;

        return $page > 0 ? $page : 1;
    }

    private function normalizeChartDate(mixed $value): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return date('Y-m-d');
        }

        $date = \DateTime::createFromFormat('Y-m-d', $raw);
        if (! $date instanceof \DateTime || $date->format('Y-m-d') !== $raw) {
            return date('Y-m-d');
        }

        return $raw;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function chartDateRange(string $date): array
    {
        return [$date . ' 00:00:00', $date . ' 23:59:59'];
    }

    private function normalizeUsageTypeForChart(mixed $value): ?string
    {
        $usageType = strtolower(trim((string) $value));

        return in_array($usageType, ['5h', 'weekly'], true) ? $usageType : null;
    }

    /**
     * @return array{
     *     weekly: array<int, array{
     *         label: string,
     *         accountType: string,
     *         color: string,
     *         points: array<int, array{time: string, minute: int, percent: int, at: string}>
     *     }>,
     *     five_hour: array<int, array{
     *         label: string,
     *         accountType: string,
     *         color: string,
     *         points: array<int, array{time: string, minute: int, percent: int, at: string}>
     *     }>
     * }
     */
    private function buildDashboardUsageChartDataByDate(string $date): array
    {
        [$start, $end] = $this->chartDateRange($date);

        $rows = db_connect()
            ->table('account_usage_histories')
            ->select('account_usage_histories.new_percent, account_usage_histories.created_at, account_usages.usage_type, subscriptions.account_id, subscriptions.account_type, accounts.account_name')
            ->join('account_usages', 'account_usages.id = account_usage_histories.account_usage_id')
            ->join('subscriptions', 'subscriptions.id = account_usages.subscription_id')
            ->join('accounts', 'accounts.id = subscriptions.account_id')
            ->where('account_usage_histories.created_at >=', $start)
            ->where('account_usage_histories.created_at <=', $end)
            ->orderBy('account_usage_histories.created_at', 'ASC')
            ->get()
            ->getResultArray();

        $weeklySeries = [];
        $fiveHourSeries = [];

        foreach ($rows as $row) {
            $usageType = $this->normalizeUsageTypeForChart($row['usage_type'] ?? null);
            if ($usageType === null) {
                continue;
            }

            $accountId = (int) ($row['account_id'] ?? 0);
            if ($accountId <= 0) {
                continue;
            }

            $accountType = SubscriptionStatusService::normalizeAccountType((string) ($row['account_type'] ?? 'free'));
            $accountName = trim((string) ($row['account_name'] ?? ''));
            $label = $accountName !== '' ? $accountName : ('Akun #' . $accountId);
            $createdAt = (string) ($row['created_at'] ?? '');
            $minute = $this->minuteOfDay($createdAt);
            $point = [
                'time' => $this->timeLabel($createdAt),
                'minute' => $minute,
                'percent' => (int) ($row['new_percent'] ?? 0),
                'at' => $createdAt,
            ];

            if ($usageType === 'weekly') {
                if (! isset($weeklySeries[$accountId])) {
                    $weeklySeries[$accountId] = [
                        'label' => $label,
                        'accountType' => $accountType,
                        'points' => [],
                    ];
                }

                $weeklySeries[$accountId]['points'][] = $point;
                continue;
            }

            if ($usageType === '5h' && $accountType === 'pro') {
                if (! isset($fiveHourSeries[$accountId])) {
                    $fiveHourSeries[$accountId] = [
                        'label' => $label,
                        'accountType' => $accountType,
                        'points' => [],
                    ];
                }

                $fiveHourSeries[$accountId]['points'][] = $point;
            }
        }

        return [
            'weekly' => $this->finalizeTimeSeries(array_values($weeklySeries)),
            'five_hour' => $this->finalizeTimeSeries(array_values($fiveHourSeries)),
        ];
    }

    /**
     * @return array{
     *     weekly: array<int, array{
     *         label: string,
     *         accountType: string,
     *         color: string,
     *         points: array<int, array{time: string, minute: int, percent: int, at: string}>
     *     }>,
     *     five_hour: array<int, array{
     *         label: string,
     *         accountType: string,
     *         color: string,
     *         points: array<int, array{time: string, minute: int, percent: int, at: string}>
     *     }>
     * }
     */
    private function buildAccountUsageChartDataByDate(int $accountId, string $date): array
    {
        [$start, $end] = $this->chartDateRange($date);

        $rows = db_connect()
            ->table('account_usage_histories')
            ->select('account_usage_histories.new_percent, account_usage_histories.created_at, account_usages.usage_type, subscriptions.id AS subscription_id, subscriptions.account_type, subscriptions.workspace_name, subscriptions.subscription_type')
            ->join('account_usages', 'account_usages.id = account_usage_histories.account_usage_id')
            ->join('subscriptions', 'subscriptions.id = account_usages.subscription_id')
            ->where('subscriptions.account_id', $accountId)
            ->where('account_usage_histories.created_at >=', $start)
            ->where('account_usage_histories.created_at <=', $end)
            ->orderBy('account_usage_histories.created_at', 'ASC')
            ->get()
            ->getResultArray();

        $weeklySeries = [];
        $fiveHourSeries = [];

        foreach ($rows as $row) {
            $usageType = $this->normalizeUsageTypeForChart($row['usage_type'] ?? null);
            if ($usageType === null) {
                continue;
            }

            $subscriptionId = (int) ($row['subscription_id'] ?? 0);
            if ($subscriptionId <= 0) {
                continue;
            }

            $accountType = SubscriptionStatusService::normalizeAccountType((string) ($row['account_type'] ?? 'free'));
            $workspaceName = trim((string) ($row['workspace_name'] ?? ''));
            $subscriptionType = trim((string) ($row['subscription_type'] ?? 'Subscription'));
            $label = $workspaceName !== '' ? ($subscriptionType . ' · ' . $workspaceName) : $subscriptionType;
            if ($accountType !== 'pro') {
                $label .= ' · Free';
            }

            $createdAt = (string) ($row['created_at'] ?? '');
            $point = [
                'time' => $this->timeLabel($createdAt),
                'minute' => $this->minuteOfDay($createdAt),
                'percent' => (int) ($row['new_percent'] ?? 0),
                'at' => $createdAt,
            ];

            if ($usageType === 'weekly') {
                if (! isset($weeklySeries[$subscriptionId])) {
                    $weeklySeries[$subscriptionId] = [
                        'label' => $label,
                        'accountType' => $accountType,
                        'points' => [],
                    ];
                }

                $weeklySeries[$subscriptionId]['points'][] = $point;
                continue;
            }

            if ($usageType === '5h' && $accountType === 'pro') {
                if (! isset($fiveHourSeries[$subscriptionId])) {
                    $fiveHourSeries[$subscriptionId] = [
                        'label' => $label,
                        'accountType' => $accountType,
                        'points' => [],
                    ];
                }

                $fiveHourSeries[$subscriptionId]['points'][] = $point;
            }
        }

        return [
            'weekly' => $this->finalizeTimeSeries(array_values($weeklySeries)),
            'five_hour' => $this->finalizeTimeSeries(array_values($fiveHourSeries)),
        ];
    }

    /**
     * @param array<int, array{
     *     label: string,
     *     accountType: string,
     *     points: array<int, array{time: string, minute: int, percent: int, at: string}>
     * }> $series
     *
     * @return array<int, array{
     *     label: string,
     *     accountType: string,
     *     color: string,
     *     points: array<int, array{time: string, minute: int, percent: int, at: string}>
     * }>
     */
    private function finalizeTimeSeries(array $series): array
    {
        $series = array_values(array_filter($series, static function (array $item): bool {
            return ($item['points'] ?? []) !== [];
        }));

        usort($series, static function (array $a, array $b): int {
            return strcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
        });

        foreach ($series as $index => &$item) {
            usort($item['points'], static function (array $a, array $b): int {
                $aMinute = (int) ($a['minute'] ?? 0);
                $bMinute = (int) ($b['minute'] ?? 0);
                if ($aMinute === $bMinute) {
                    return strcmp((string) ($a['at'] ?? ''), (string) ($b['at'] ?? ''));
                }

                return $aMinute <=> $bMinute;
            });

            $item['color'] = self::USAGE_CHART_PALETTE[$index % count(self::USAGE_CHART_PALETTE)];
        }
        unset($item);

        return $series;
    }

    private function minuteOfDay(string $dateTime): int
    {
        $timestamp = strtotime($dateTime);
        if ($timestamp === false) {
            return 0;
        }

        return ((int) date('G', $timestamp) * 60) + (int) date('i', $timestamp);
    }

    private function timeLabel(string $dateTime): string
    {
        $timestamp = strtotime($dateTime);
        if ($timestamp === false) {
            return '--:--';
        }

        return date('H:i', $timestamp);
    }

    private function currentUserId(): int
    {
        return (int) (session('user_id') ?? 0);
    }
}
