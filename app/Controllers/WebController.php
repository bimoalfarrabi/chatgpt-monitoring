<?php

namespace App\Controllers;

use App\Models\AccountModel;
use App\Models\AccountUsageHistoryModel;
use App\Models\AccountUsageModel;
use App\Models\SubscriptionModel;
use App\Models\SubscriptionRenewalHistoryModel;
use App\Models\TelegramSettingModel;
use App\Models\UserModel;
use App\Services\RouterUsageCollectorService;
use App\Services\SubscriptionStatusService;
use App\Services\TelegramService;
use CodeIgniter\HTTP\RedirectResponse;

class WebController extends BaseController
{
    private const HISTORY_PER_PAGE = 5;

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
        $routerUsageByEmail = $this->routerUsageByEmails(array_column($accounts, 'email'));

        $subscriptions = $this->enrichedSubscriptions(
            $this->subscriptions->whereIn('account_type', ['pro', 'plus'])->orderBy('expired_at', 'ASC')->findAll()
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
            'routerUsageByEmail'=> $routerUsageByEmail,
            'pageTitle'         => 'Dashboard',
        ]);
    }

    public function digestRouterDataFromDashboard(): RedirectResponse
    {
        $sourceFile = trim((string) env('router.logPath', ''));
        $provider = trim((string) env('router.provider', '9router'));
        $resetCursor = (string) ($this->request->getPost('reset_cursor') ?? '') === '1';

        if ($sourceFile === '') {
            return redirect()->to('/')->with(
                'error',
                'router.logPath belum diisi di .env. Endpoint ingest hanya diperlukan untuk mode shipper dari mesin lain.'
            );
        }

        try {
            $collector = new RouterUsageCollectorService();
            $collectResult = $collector->collectFromLogFile($sourceFile, $provider, $resetCursor);
            $syncResult = $this->syncLocalAccountsIncrementalFromRouter();
        } catch (\Throwable $e) {
            return redirect()->to('/')->with('error', 'Digest 9router gagal: ' . $e->getMessage());
        }

        $message = sprintf(
            'Digest selesai. Inserted: %d, Duplicate: %d, Parsed: %d, Sync akun baru: %d.',
            (int) ($collectResult['inserted'] ?? 0),
            (int) ($collectResult['duplicates'] ?? 0),
            (int) ($collectResult['parsed'] ?? 0),
            (int) ($syncResult['accounts_created'] ?? 0)
        );

        return redirect()->to('/')->with('success', $message);
    }

    public function accountsIndex(): string
    {
        $search = trim((string) $this->request->getGet('q'));
        $sortByInput = strtolower(trim((string) $this->request->getGet('sort_by')));
        $sortDirInput = strtolower(trim((string) $this->request->getGet('sort_dir')));

        $sortByMap = [
            'newest' => 'id',
            'oldest' => 'id',
            'name' => 'account_name',
            'email' => 'email',
        ];

        $sortBy = array_key_exists($sortByInput, $sortByMap) ? $sortByInput : 'newest';
        $sortDir = in_array($sortDirInput, ['asc', 'desc'], true)
            ? $sortDirInput
            : (($sortBy === 'oldest' || $sortBy === 'name' || $sortBy === 'email') ? 'asc' : 'desc');

        if ($sortBy === 'oldest') {
            $sortDir = 'asc';
        } elseif ($sortBy === 'newest') {
            $sortDir = 'desc';
        }

        $query = $this->accounts;
        if ($search !== '') {
            $query = $query
                ->groupStart()
                ->like('account_name', $search)
                ->orLike('email', $search)
                ->groupEnd();
        }

        $accounts = $query
            ->orderBy($sortByMap[$sortBy], strtoupper($sortDir))
            ->findAll();

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
            'filters'   => [
                'q' => $search,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
            ],
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
        $routerUsage = $this->routerUsageForEmail((string) ($account['email'] ?? ''));

        return view('accounts/detail', [
            'account'              => $account,
            'subscriptions'        => $subscriptions,
            'workspaceHistoryPage' => $workspaceHistoryPage,
            'renewalHistoryPage'   => $renewalHistoryPage,
            'routerUsage'          => $routerUsage,
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

    public function createAccount(): RedirectResponse
    {
        $data = $this->request->getPost();

        $rules = [
            'account_name'      => 'required|min_length[2]|max_length[120]',
            'email'             => 'required|valid_email|is_unique[accounts.email]',
            'password_hint'     => 'permit_empty|max_length[255]',
            'notes'             => 'permit_empty',
            'account_type'      => 'required|in_list[free,pro,plus]',
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
        if (SubscriptionStatusService::isWorkspaceAccountType($accountType)) {
            if (trim((string) ($data['store_source'] ?? '')) === '') {
                return redirect()->back()->withInput()->with('error', 'Sumber store wajib diisi untuk akun workspace (pro/plus).');
            }

            if (trim((string) ($data['subscription_type'] ?? '')) === '') {
                return redirect()->back()->withInput()->with('error', 'Tipe subscription wajib diisi untuk akun workspace (pro/plus).');
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

        if (SubscriptionStatusService::isWorkspaceAccountType($accountType) && $subscriptionData !== null) {
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
                'personal_workspace_name' => trim((string) ($data['account_name'] ?? '')) !== ''
                    ? trim((string) ($data['account_name'] ?? ''))
                    : 'Personal Workspace',
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

        $successMessage = SubscriptionStatusService::isWorkspaceAccountType($accountType)
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
            'account_type'      => 'required|in_list[free,pro,plus]',
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

        $subscriptionData = $this->buildSubscriptionPayload($data);
        if ($subscriptionData['error'] !== null) {
            return redirect()->back()->withInput()->with('error', $subscriptionData['error']);
        }

        $accountType = $subscriptionData['account_type'];
        if (SubscriptionStatusService::isWorkspaceAccountType($accountType)) {
            if (trim((string) ($data['store_source'] ?? '')) === '') {
                return redirect()->back()->withInput()->with('error', 'Sumber store wajib diisi untuk akun workspace (pro/plus).');
            }

            if (trim((string) ($data['subscription_type'] ?? '')) === '') {
                return redirect()->back()->withInput()->with('error', 'Tipe subscription wajib diisi untuk akun workspace (pro/plus).');
            }
        }

        $storeSource = SubscriptionStatusService::isWorkspaceAccountType($accountType)
            ? trim((string) ($data['store_source'] ?? ''))
            : 'free_account';
        $subscriptionType = SubscriptionStatusService::isWorkspaceAccountType($accountType)
            ? trim((string) ($data['subscription_type'] ?? ''))
            : 'Free Weekly';

        $this->subscriptions->update($id, array_merge([
            'store_source'      => $storeSource,
            'subscription_type' => $subscriptionType,
        ], $subscriptionData['payload']));

        $this->syncUsagesForSubscription(
            $id,
            $subscriptionData['account_type'],
            $subscriptionData['pro_account_type'],
            $subscriptionData['default_reset_at']
        );

        if ($accountType === 'free') {
            $this->renewalHistories->where('subscription_id', $id)->delete();
        }

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
        if (! SubscriptionStatusService::isWorkspaceAccountType($accountType)) {
            return redirect()->back()->with('error', 'Auto perpanjang hanya berlaku untuk akun workspace (pro/plus).');
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

    public function deactivateSubscriptionWorkspace(int $id): RedirectResponse
    {
        $subscription = $this->subscriptions->find($id);
        if (! $subscription) {
            return redirect()->back()->with('error', 'Subscription tidak ditemukan.');
        }

        $accountType = SubscriptionStatusService::normalizeAccountType($subscription['account_type'] ?? null);
        if (! SubscriptionStatusService::isWorkspaceAccountType($accountType)) {
            return redirect()->back()->with('error', 'Aksi deactivated hanya berlaku untuk akun workspace (pro/plus).');
        }

        $isWorkspaceDeactivated = SubscriptionStatusService::parseBoolean($subscription['is_workspace_deactivated'] ?? null);
        if ($isWorkspaceDeactivated) {
            return redirect()->back()->with('error', 'Workspace ini sudah berstatus deactivated.');
        }

        $this->subscriptions->update($id, [
            'is_workspace_deactivated' => 1,
            'status' => 'deactivated',
        ]);

        return redirect()->back()->with('success', 'Workspace berhasil diubah ke status deactivated.');
    }

    public function createWorkspaceFromDeactivated(int $id): RedirectResponse
    {
        $sourceSubscription = $this->subscriptions->find($id);
        if (! $sourceSubscription) {
            return redirect()->back()->with('error', 'Subscription sumber tidak ditemukan.');
        }

        $accountType = SubscriptionStatusService::normalizeAccountType($sourceSubscription['account_type'] ?? null);
        $isWorkspaceDeactivated = SubscriptionStatusService::parseBoolean($sourceSubscription['is_workspace_deactivated'] ?? null);
        if (! SubscriptionStatusService::isWorkspaceAccountType($accountType) || ! $isWorkspaceDeactivated) {
            return redirect()->back()->with('error', 'Workspace baru hanya bisa dibuat dari subscription workspace (pro/plus) yang status workspace-nya deactivated.');
        }

        $data = $this->request->getPost();
        $rules = [
            'store_source'      => 'required|max_length[100]',
            'subscription_type' => 'required|max_length[100]',
            'pro_account_type'  => 'permit_empty|in_list[personal_invite,seller_account]',
            'workspace_name'    => 'permit_empty|max_length[120]',
            'personal_workspace_name' => 'permit_empty|max_length[120]',
            'subscribed_at'     => 'required|valid_date[Y-m-d\\TH:i]',
            'is_one_month_duration' => 'permit_empty|in_list[0,1]',
        ];

        if ($accountType === 'pro') {
            $rules['workspace_name'] = 'required|max_length[120]';
        } elseif ($accountType === 'plus') {
            $rules['personal_workspace_name'] = 'required|max_length[120]';
        }

        if (! $this->validateData($data, $rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $data['account_type'] = $accountType;
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
            $accountType,
            $subscriptionData['pro_account_type'],
            $subscriptionData['default_reset_at']
        );

        return redirect()
            ->to('/accounts/' . (int) $sourceSubscription['account_id'])
            ->with('success', 'Workspace baru berhasil dibuat dari workspace yang deactivated.');
    }

    public function updatePlusAccountFromDeactivated(int $id): RedirectResponse
    {
        $sourceSubscription = $this->subscriptions->find($id);
        if (! $sourceSubscription) {
            return redirect()->back()->with('error', 'Subscription sumber tidak ditemukan.');
        }

        $accountType = SubscriptionStatusService::normalizeAccountType($sourceSubscription['account_type'] ?? null);
        $isWorkspaceDeactivated = SubscriptionStatusService::parseBoolean($sourceSubscription['is_workspace_deactivated'] ?? null);
        if ($accountType !== 'plus' || ! $isWorkspaceDeactivated) {
            return redirect()->back()->with('error', 'Fitur ini hanya berlaku untuk akun plus yang deactivated.');
        }

        $accountId = (int) ($sourceSubscription['account_id'] ?? 0);
        $account = $this->accounts->find($accountId);
        if (! $account) {
            return redirect()->back()->with('error', 'Account sumber tidak ditemukan.');
        }

        $data = $this->request->getPost();
        $data['plus_account_name'] = trim((string) ($data['plus_account_name'] ?? ''));
        $data['plus_email'] = strtolower(trim((string) ($data['plus_email'] ?? '')));
        $data['plus_password_hint'] = (string) ($data['plus_password_hint'] ?? '');
        $data['plus_notes'] = trim((string) ($data['plus_notes'] ?? ''));

        $rules = [
            'plus_account_name' => 'required|min_length[2]|max_length[120]',
            'plus_email' => 'required|valid_email|max_length[160]',
            'plus_password_hint' => 'permit_empty|max_length[255]',
            'plus_notes' => 'permit_empty',
            'plus_subscribed_at' => 'required|valid_date[Y-m-d\\TH:i]',
        ];

        if (! $this->validateData($data, $rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $emailExists = $this->accounts
            ->where('email', $data['plus_email'])
            ->where('id !=', $accountId)
            ->first();
        if ($emailExists) {
            return redirect()->back()->withInput()->with('error', 'Email akun plus sudah dipakai akun lain.');
        }

        $resolvedPersonalWorkspaceName = trim((string) ($sourceSubscription['personal_workspace_name'] ?? ''));
        if ($resolvedPersonalWorkspaceName === '') {
            $resolvedPersonalWorkspaceName = trim((string) ($sourceSubscription['workspace_name'] ?? ''));
        }
        if ($resolvedPersonalWorkspaceName === '') {
            $resolvedPersonalWorkspaceName = $data['plus_account_name'] !== ''
                ? $data['plus_account_name']
                : trim((string) ($account['account_name'] ?? ''));
        }
        if ($resolvedPersonalWorkspaceName === '') {
            $resolvedPersonalWorkspaceName = 'Personal Workspace';
        }

        $resolvedStoreSource = trim((string) ($sourceSubscription['store_source'] ?? ''));
        if ($resolvedStoreSource === '') {
            $resolvedStoreSource = 'seller_account';
        }

        $resolvedSubscriptionType = trim((string) ($sourceSubscription['subscription_type'] ?? ''));
        if ($resolvedSubscriptionType === '') {
            $resolvedSubscriptionType = 'Plus Monthly';
        }

        $subscriptionPayloadData = [
            'account_type' => 'plus',
            'pro_account_type' => 'seller_account',
            'workspace_name' => $resolvedPersonalWorkspaceName,
            'personal_workspace_name' => $resolvedPersonalWorkspaceName,
            'is_workspace_deactivated' => 0,
            'subscribed_at' => $data['plus_subscribed_at'],
            'is_one_month_duration' => 1,
        ];
        $subscriptionData = $this->buildSubscriptionPayload($subscriptionPayloadData);
        if ($subscriptionData['error'] !== null) {
            return redirect()->back()->withInput()->with('error', $subscriptionData['error']);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        $this->accounts->update($accountId, [
            'account_name'  => $data['plus_account_name'],
            'email'         => $data['plus_email'],
            'password_hint' => trim($data['plus_password_hint']) !== '' ? trim($data['plus_password_hint']) : null,
            'notes'         => $data['plus_notes'] !== '' ? $data['plus_notes'] : null,
        ]);

        $newSubscriptionId = $this->subscriptions->insert(array_merge([
            'account_id' => $accountId,
            'store_source' => $resolvedStoreSource,
            'subscription_type' => $resolvedSubscriptionType,
        ], $subscriptionData['payload']), true);

        $this->syncUsagesForSubscription(
            (int) $newSubscriptionId,
            'plus',
            'seller_account',
            $subscriptionData['default_reset_at']
        );

        $db->transComplete();
        if (! $db->transStatus()) {
            return redirect()->back()->withInput()->with('error', 'Gagal mengupdate akun plus deactivated. Silakan coba lagi.');
        }

        return redirect()
            ->to('/accounts/' . $accountId)
            ->with('success', 'Akun plus deactivated berhasil diperbarui tanpa hapus & create ulang account.');
    }

    public function deleteAccount(int $id): RedirectResponse
    {
        if (! $this->accounts->find($id)) {
            return redirect()->back()->with('error', 'Account tidak ditemukan.');
        }

        $this->accounts->delete($id);

        return redirect()->to('/accounts')->with('success', 'Account berhasil dihapus.');
    }

    public function updateAccountName(int $id): RedirectResponse
    {
        $account = $this->accounts->find($id);
        if (! $account) {
            return redirect()->back()->with('error', 'Account tidak ditemukan.');
        }

        $data = $this->request->getPost();
        $rules = [
            'account_name' => 'required|min_length[2]|max_length[120]',
        ];

        if (! $this->validateData($data, $rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $accountName = trim((string) ($data['account_name'] ?? ''));
        $this->accounts->update($id, [
            'account_name' => $accountName,
        ]);

        return redirect()->to('/accounts/' . $id)->with('success', 'Nama account berhasil diperbarui.');
    }

    public function updateAccountPassword(int $id): RedirectResponse
    {
        $account = $this->accounts->find($id);
        if (! $account) {
            return redirect()->back()->with('error', 'Account tidak ditemukan.');
        }

        $data = $this->request->getPost();
        $data['password_hint'] = (string) ($data['password_hint'] ?? '');

        $rules = [
            'password_hint' => 'permit_empty|max_length[255]',
        ];

        if (! $this->validateData($data, $rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $passwordHint = trim($data['password_hint']);
        $this->accounts->update($id, [
            'password_hint' => $passwordHint !== '' ? $passwordHint : null,
        ]);

        $successMessage = $passwordHint !== ''
            ? 'Password akun berhasil diperbarui.'
            : 'Password akun berhasil dikosongkan.';

        return redirect()->to('/accounts/' . $id)->with('success', $successMessage);
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

        $hardResetSeed = $this->routerHardResetSeed();
        $envEditor = $this->envEditorState();

        return view('telegram/settings', [
            'settings'      => $settings,
            'hardResetSeed' => $hardResetSeed,
            'envEditor'     => $envEditor,
            'pageTitle'     => 'Settings',
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
        $result = $telegram->sendMessage('Test notification dari halaman Settings', $userId);

        if (! $result['success']) {
            return redirect()->back()->with('error', $result['message']);
        }

        return redirect()->back()->with('success', 'Test message terkirim.');
    }

    public function saveDotEnvFromSettings(): RedirectResponse
    {
        $content = (string) ($this->request->getPost('env_content') ?? '');
        $normalizedContent = str_replace(["\r\n", "\r"], "\n", $content);

        if (str_contains($normalizedContent, "\0")) {
            return redirect()->back()->withInput()->with('error', '.env mengandung karakter null byte yang tidak valid.');
        }

        $maxBytes = 300000;
        if (strlen($normalizedContent) > $maxBytes) {
            return redirect()->back()->withInput()->with('error', '.env terlalu besar. Batas maksimum 300KB.');
        }

        $lineError = $this->firstInvalidEnvLine($normalizedContent);
        if ($lineError !== null) {
            return redirect()->back()->withInput()->with('error', sprintf('Format .env tidak valid di baris %d.', $lineError));
        }

        $path = $this->envFilePath();
        if (! is_file($path)) {
            return redirect()->back()->with('error', 'File .env tidak ditemukan di root project.');
        }

        if (! is_writable($path)) {
            return redirect()->back()->with('error', 'File .env tidak writable oleh web server.');
        }

        $backupPath = $path . '.bak.' . date('Ymd_His');
        if (@copy($path, $backupPath) === false) {
            return redirect()->back()->with('error', 'Gagal membuat backup .env sebelum menyimpan.');
        }

        $bytes = @file_put_contents($path, $normalizedContent, LOCK_EX);
        if ($bytes === false) {
            return redirect()->back()->with('error', 'Gagal menyimpan file .env. Backup tersimpan di: ' . basename($backupPath));
        }

        return redirect()->to('/settings')->with('success', '.env berhasil disimpan. Backup: ' . basename($backupPath));
    }

    public function restoreLatestDotEnvBackup(): RedirectResponse
    {
        $path = $this->envFilePath();
        if (! is_file($path)) {
            return redirect()->back()->with('error', 'File .env tidak ditemukan di root project.');
        }

        if (! is_writable($path)) {
            return redirect()->back()->with('error', 'File .env tidak writable oleh web server.');
        }

        $latestBackup = $this->latestEnvBackupPath();
        if ($latestBackup === null) {
            return redirect()->back()->with('error', 'Backup .env tidak ditemukan.');
        }

        if (! is_readable($latestBackup)) {
            return redirect()->back()->with('error', 'Backup .env terbaru tidak dapat dibaca.');
        }

        $beforeRestoreBackup = $path . '.bak.' . date('Ymd_His');
        if (@copy($path, $beforeRestoreBackup) === false) {
            return redirect()->back()->with('error', 'Gagal membuat backup .env saat ini sebelum restore.');
        }

        if (@copy($latestBackup, $path) === false) {
            return redirect()->back()->with(
                'error',
                'Restore gagal. Backup current state ada di: ' . basename($beforeRestoreBackup)
            );
        }

        return redirect()->to('/settings')->with(
            'success',
            sprintf('Restore .env berhasil dari %s. Backup state sebelumnya: %s', basename($latestBackup), basename($beforeRestoreBackup))
        );
    }

    public function hardResetLocalAccountsFromRouter(): RedirectResponse
    {
        $acknowledged = (string) ($this->request->getPost('ack_irreversible') ?? '');
        if ($acknowledged !== '1') {
            return redirect()->back()->with('error', 'Centang konfirmasi bahwa hard reset bersifat irreversibel.');
        }

        $confirmation = strtoupper(trim((string) $this->request->getPost('confirm_phrase')));
        if ($confirmation !== 'HARD RESET') {
            return redirect()->back()->with('error', 'Konfirmasi tidak valid. Ketik tepat: HARD RESET');
        }

        $seed = $this->routerHardResetSeed();
        $emails = $seed['emails'] ?? [];
        if (! is_array($emails) || $emails === []) {
            return redirect()->back()->with('error', 'Tidak ada email valid dari data 9router. Hard reset dibatalkan.');
        }

        $routerAccountIdsByEmail = is_array($seed['router_account_ids_by_email'] ?? null)
            ? $seed['router_account_ids_by_email']
            : [];
        $userId = $this->currentUserId();
        $db = db_connect();

        $db->transBegin();
        try {
            // Domain lokal yang di-reset (akun/subscription dan tabel turunannya).
            $tablesToClear = [
                'account_usage_histories',
                'account_usages',
                'subscription_renewal_histories',
                'reminder_logs',
                'subscriptions',
                'accounts',
            ];
            foreach ($tablesToClear as $tableName) {
                if (! $db->tableExists($tableName)) {
                    continue;
                }

                $db->table($tableName)->where('id >', 0)->delete();
            }

            // Reset relasi ai_router_accounts sebelum remap.
            if ($db->tableExists('ai_router_accounts')) {
                $db->table('ai_router_accounts')
                    ->set([
                        'user_id' => null,
                        'account_id' => null,
                        'subscription_id' => null,
                        'mapping_status' => 'unmapped',
                    ])
                    ->update();
            }

            $importedAccounts = 0;
            foreach ($emails as $email) {
                $accountId = (int) $this->accounts->insert([
                    'account_name' => $this->defaultAccountNameFromEmail($email),
                    'email' => $email,
                    'password_hint' => null,
                    'notes' => null,
                ], true);

                if ($accountId <= 0) {
                    throw new \RuntimeException('Gagal membuat akun untuk email: ' . $email);
                }

                $subscriptionId = (int) $this->subscriptions->insert([
                    'account_id' => $accountId,
                    'account_type' => 'free',
                    'pro_account_type' => null,
                    'workspace_name' => null,
                    'personal_workspace_name' => $this->defaultWorkspaceNameFromEmail($email),
                    'is_workspace_deactivated' => 0,
                    'store_source' => '9router_import',
                    'subscription_type' => 'Free (9router)',
                    'subscribed_at' => null,
                    'is_one_month_duration' => 0,
                    'expired_at' => null,
                    'status' => 'active',
                ], true);

                if ($subscriptionId <= 0) {
                    throw new \RuntimeException('Gagal membuat subscription untuk email: ' . $email);
                }

                // Tetap sinkronkan tabel usage lama agar UI/fitur legacy tetap konsisten.
                $this->syncUsagesForSubscription($subscriptionId, 'free', null, date('Y-m-d H:i:s'));

                $routerIds = $routerAccountIdsByEmail[$email] ?? [];
                if (is_array($routerIds) && $routerIds !== [] && $db->tableExists('ai_router_accounts')) {
                    $db->table('ai_router_accounts')
                        ->whereIn('id', array_values(array_unique(array_map('intval', $routerIds))))
                        ->set([
                            'user_id' => $userId,
                            'account_id' => $accountId,
                            'subscription_id' => $subscriptionId,
                            'mapping_status' => 'mapped',
                        ])
                        ->update();
                }

                $importedAccounts++;
            }
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Hard reset local accounts failed: {message}', ['message' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Hard reset gagal: ' . $e->getMessage());
        }

        if (! $db->transStatus()) {
            $db->transRollback();
            return redirect()->back()->with('error', 'Hard reset gagal karena transaksi database tidak valid.');
        }

        $db->transCommit();

        return redirect()->to('/settings')->with(
            'success',
            sprintf('Hard reset berhasil. %d akun lokal dibuat ulang dari data 9router.', $importedAccounts)
        );
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
            $isOneMonthDuration = SubscriptionStatusService::resolveOneMonthDurationForAccount(
                $accountType,
                SubscriptionStatusService::parseBoolean($subscription['is_one_month_duration'] ?? null)
            );
            $isWorkspaceDeactivated = SubscriptionStatusService::parseBoolean($subscription['is_workspace_deactivated'] ?? null);
            $proAccountType = SubscriptionStatusService::resolveProAccountTypeForAccount($accountType, $subscription['pro_account_type'] ?? null);
            $workspaceName = trim((string) ($subscription['workspace_name'] ?? ''));
            $workspaceName = $workspaceName === '' ? null : $workspaceName;
            $personalWorkspaceName = trim((string) ($subscription['personal_workspace_name'] ?? ''));
            $personalWorkspaceName = $personalWorkspaceName === '' ? null : $personalWorkspaceName;

            if (! SubscriptionStatusService::isWorkspaceAccountType($accountType)) {
                $proAccountType = null;
                if ($personalWorkspaceName === null && $workspaceName !== null) {
                    $personalWorkspaceName = $workspaceName;
                }
                $workspaceName = null;
                $subscription['subscribed_at'] = null;
                $isWorkspaceDeactivated = false;
                $isOneMonthDuration = false;
            } elseif ($accountType === 'plus') {
                if ($personalWorkspaceName === null && $workspaceName !== null) {
                    $personalWorkspaceName = $workspaceName;
                }
                $workspaceName = $personalWorkspaceName;
            } elseif ($proAccountType !== 'personal_invite') {
                $personalWorkspaceName = null;
            }

            $expiredAt = SubscriptionStatusService::isWorkspaceAccountType($accountType)
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
     * @param array<int, string|null> $emails
     *
     * @return array<string, array{
     *   requests_24h:int,
     *   tokens_24h:int,
     *   requests_7d:int,
     *   tokens_7d:int,
     *   cache_ratio_7d:float,
     *   avg_latency_ms_7d:int,
     *   last_event_at:string
     * }>
     */
    private function routerUsageByEmails(array $emails): array
    {
        $normalizedEmails = [];
        foreach ($emails as $email) {
            $value = strtolower(trim((string) $email));
            if ($value !== '') {
                $normalizedEmails[] = $value;
            }
        }

        $normalizedEmails = array_values(array_unique($normalizedEmails));
        if ($normalizedEmails === []) {
            return [];
        }

        $rows = db_connect()
            ->table('ai_router_usage_events e')
            ->select("
                LOWER(a.email) AS email,
                SUM(CASE WHEN e.event_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) AS requests_24h,
                SUM(CASE WHEN e.event_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 24 HOUR) THEN (e.input_tokens + e.output_tokens) ELSE 0 END) AS tokens_24h,
                SUM(CASE WHEN e.event_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS requests_7d,
                SUM(CASE WHEN e.event_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY) THEN (e.input_tokens + e.output_tokens) ELSE 0 END) AS tokens_7d,
                SUM(CASE WHEN e.event_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY) THEN e.input_tokens ELSE 0 END) AS input_tokens_7d,
                SUM(CASE WHEN e.event_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY) THEN e.cache_read_tokens ELSE 0 END) AS cache_read_tokens_7d,
                AVG(CASE WHEN e.event_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY) THEN NULLIF(e.duration_ms, 0) END) AS avg_latency_ms_7d,
                MAX(e.event_at) AS last_event_at
            ", false)
            ->join('ai_router_accounts a', 'a.provider = e.provider AND a.router_account_ref = e.router_account_ref', 'inner')
            ->whereIn('LOWER(a.email)', $normalizedEmails)
            ->groupBy('LOWER(a.email)')
            ->get()
            ->getResultArray();

        $usageMap = [];
        foreach ($rows as $row) {
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            if ($email === '') {
                continue;
            }

            $usageMap[$email] = $this->normalizeRouterUsageRow($row);
        }

        return $usageMap;
    }

    /**
     * @return array{
     *   requests_24h:int,
     *   tokens_24h:int,
     *   requests_7d:int,
     *   tokens_7d:int,
     *   cache_ratio_7d:float,
     *   avg_latency_ms_7d:int,
     *   last_event_at:string
     * }
     */
    private function routerUsageForEmail(string $email): array
    {
        $key = strtolower(trim($email));
        if ($key === '') {
            return $this->emptyRouterUsageRow();
        }

        $map = $this->routerUsageByEmails([$key]);

        return $map[$key] ?? $this->emptyRouterUsageRow();
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{
     *   requests_24h:int,
     *   tokens_24h:int,
     *   requests_7d:int,
     *   tokens_7d:int,
     *   cache_ratio_7d:float,
     *   avg_latency_ms_7d:int,
     *   last_event_at:string
     * }
     */
    private function normalizeRouterUsageRow(array $row): array
    {
        $inputTokens7d = (int) ($row['input_tokens_7d'] ?? 0);
        $cacheRead7d = (int) ($row['cache_read_tokens_7d'] ?? 0);

        return [
            'requests_24h' => (int) ($row['requests_24h'] ?? 0),
            'tokens_24h' => (int) ($row['tokens_24h'] ?? 0),
            'requests_7d' => (int) ($row['requests_7d'] ?? 0),
            'tokens_7d' => (int) ($row['tokens_7d'] ?? 0),
            'cache_ratio_7d' => $inputTokens7d > 0 ? round(($cacheRead7d / $inputTokens7d) * 100, 2) : 0.0,
            'avg_latency_ms_7d' => (int) round((float) ($row['avg_latency_ms_7d'] ?? 0)),
            'last_event_at' => (string) ($row['last_event_at'] ?? ''),
        ];
    }

    /**
     * @return array{
     *   requests_24h:int,
     *   tokens_24h:int,
     *   requests_7d:int,
     *   tokens_7d:int,
     *   cache_ratio_7d:float,
     *   avg_latency_ms_7d:int,
     *   last_event_at:string
     * }
     */
    private function emptyRouterUsageRow(): array
    {
        return [
            'requests_24h' => 0,
            'tokens_24h' => 0,
            'requests_7d' => 0,
            'tokens_7d' => 0,
            'cache_ratio_7d' => 0.0,
            'avg_latency_ms_7d' => 0,
            'last_event_at' => '',
        ];
    }

    /**
     * @return array{
     *   emails: array<int, string>,
     *   total_emails: int,
     *   total_router_accounts_with_email: int,
     *   total_router_sessions_with_email: int,
     *   router_account_ids_by_email: array<string, array<int, int>>
     * }
     */
    private function routerHardResetSeed(): array
    {
        $db = db_connect();
        $emails = [];
        $routerAccountIdsByEmail = [];
        $routerAccountRowsWithEmail = 0;
        $sessionRowsWithEmail = 0;

        if ($db->tableExists('ai_router_accounts')) {
            $accountRows = $db->table('ai_router_accounts')
                ->select('id, email')
                ->where('email IS NOT NULL', null, false)
                ->where('email !=', '')
                ->get()
                ->getResultArray();

            foreach ($accountRows as $row) {
                $email = $this->normalizeImportEmail((string) ($row['email'] ?? ''));
                if ($email === null) {
                    continue;
                }

                $routerAccountRowsWithEmail++;
                $emails[$email] = true;
                $routerId = (int) ($row['id'] ?? 0);
                if ($routerId > 0) {
                    $routerAccountIdsByEmail[$email][] = $routerId;
                }
            }
        }

        if ($db->tableExists('ai_router_account_sessions')) {
            $sessionRows = $db->table('ai_router_account_sessions')
                ->select('email')
                ->where('email IS NOT NULL', null, false)
                ->where('email !=', '')
                ->get()
                ->getResultArray();

            foreach ($sessionRows as $row) {
                $email = $this->normalizeImportEmail((string) ($row['email'] ?? ''));
                if ($email === null) {
                    continue;
                }

                $sessionRowsWithEmail++;
                $emails[$email] = true;
            }
        }

        $uniqueEmails = array_keys($emails);
        sort($uniqueEmails, SORT_NATURAL);

        foreach ($routerAccountIdsByEmail as $email => $ids) {
            $routerAccountIdsByEmail[$email] = array_values(array_unique(array_map('intval', $ids)));
        }

        return [
            'emails' => $uniqueEmails,
            'total_emails' => count($uniqueEmails),
            'total_router_accounts_with_email' => $routerAccountRowsWithEmail,
            'total_router_sessions_with_email' => $sessionRowsWithEmail,
            'router_account_ids_by_email' => $routerAccountIdsByEmail,
        ];
    }

    private function normalizeImportEmail(string $email): ?string
    {
        $normalized = strtolower(trim($email));
        if ($normalized === '') {
            return null;
        }

        return filter_var($normalized, FILTER_VALIDATE_EMAIL) ? $normalized : null;
    }

    private function defaultAccountNameFromEmail(string $email): string
    {
        $localPart = explode('@', $email, 2)[0] ?? '';
        $clean = (string) preg_replace('/[^a-z0-9]+/i', ' ', $localPart);
        $clean = trim((string) preg_replace('/\s+/', ' ', $clean));
        if ($clean === '') {
            $clean = $email;
        }

        $name = ucwords(substr($clean, 0, 90));

        return $name !== '' ? $name : 'Imported Account';
    }

    private function defaultWorkspaceNameFromEmail(string $email): string
    {
        return substr($this->defaultAccountNameFromEmail($email) . ' Workspace', 0, 120);
    }

    /**
     * @return array{
     *   checked:int,
     *   accounts_created:int,
     *   subscriptions_created:int,
     *   mappings_updated:int
     * }
     */
    private function syncLocalAccountsIncrementalFromRouter(): array
    {
        $db = db_connect();
        if (! $db->tableExists('ai_router_accounts')) {
            return [
                'checked' => 0,
                'accounts_created' => 0,
                'subscriptions_created' => 0,
                'mappings_updated' => 0,
            ];
        }

        $rows = $db->table('ai_router_accounts')
            ->select('id, email')
            ->where('email IS NOT NULL', null, false)
            ->where('email !=', '')
            ->get()
            ->getResultArray();

        $checked = 0;
        $accountsCreated = 0;
        $subscriptionsCreated = 0;
        $mappingsUpdated = 0;
        $userId = $this->currentUserId();

        foreach ($rows as $row) {
            $email = $this->normalizeImportEmail((string) ($row['email'] ?? ''));
            if ($email === null) {
                continue;
            }

            $checked++;
            $account = $this->accounts->where('email', $email)->first();
            if (! is_array($account)) {
                $accountId = (int) $this->accounts->insert([
                    'account_name' => $this->defaultAccountNameFromEmail($email),
                    'email' => $email,
                    'password_hint' => null,
                    'notes' => null,
                ], true);

                if ($accountId <= 0) {
                    continue;
                }

                $account = $this->accounts->find($accountId);
                $accountsCreated++;
            }

            $accountId = (int) (($account['id'] ?? 0));
            if ($accountId <= 0) {
                continue;
            }

            $subscription = $this->subscriptions
                ->where('account_id', $accountId)
                ->orderBy('id', 'DESC')
                ->first();

            $subscriptionId = (int) (($subscription['id'] ?? 0));
            if ($subscriptionId <= 0) {
                $subscriptionId = (int) $this->subscriptions->insert([
                    'account_id' => $accountId,
                    'account_type' => 'free',
                    'pro_account_type' => null,
                    'workspace_name' => null,
                    'personal_workspace_name' => $this->defaultWorkspaceNameFromEmail($email),
                    'is_workspace_deactivated' => 0,
                    'store_source' => '9router_import',
                    'subscription_type' => 'Free (9router)',
                    'subscribed_at' => null,
                    'is_one_month_duration' => 0,
                    'expired_at' => null,
                    'status' => 'active',
                ], true);

                if ($subscriptionId > 0) {
                    $this->syncUsagesForSubscription($subscriptionId, 'free', null, date('Y-m-d H:i:s'));
                    $subscriptionsCreated++;
                }
            }

            $routerAccountId = (int) ($row['id'] ?? 0);
            if ($routerAccountId > 0) {
                $db->table('ai_router_accounts')
                    ->where('id', $routerAccountId)
                    ->set([
                        'user_id' => $userId,
                        'account_id' => $accountId,
                        'subscription_id' => $subscriptionId > 0 ? $subscriptionId : null,
                        'mapping_status' => 'mapped',
                    ])
                    ->update();
                $mappingsUpdated++;
            }
        }

        return [
            'checked' => $checked,
            'accounts_created' => $accountsCreated,
            'subscriptions_created' => $subscriptionsCreated,
            'mappings_updated' => $mappingsUpdated,
        ];
    }

    /**
     * @return array{
     *   path:string,
     *   exists:bool,
     *   writable:bool,
     *   size_bytes:int,
     *   content:string,
     *   latest_backup_name:string,
     *   latest_backup_mtime:string,
     *   latest_backup_size_bytes:int
     * }
     */
    private function envEditorState(): array
    {
        $path = $this->envFilePath();
        $exists = is_file($path);
        $writable = $exists && is_writable($path);
        $content = '';
        $sizeBytes = 0;

        if ($exists) {
            $raw = @file_get_contents($path);
            if ($raw !== false) {
                $content = str_replace(["\r\n", "\r"], "\n", $raw);
                $sizeBytes = strlen($content);
            }
        }

        $oldInput = session()->getFlashdata('_ci_old_input');
        $oldContent = is_array($oldInput) ? ($oldInput['env_content'] ?? null) : null;
        if (is_string($oldContent)) {
            $content = str_replace(["\r\n", "\r"], "\n", $oldContent);
            $sizeBytes = strlen($content);
        }

        $latestBackupPath = $this->latestEnvBackupPath();
        $latestBackupName = '';
        $latestBackupMtime = '';
        $latestBackupSize = 0;
        if ($latestBackupPath !== null) {
            $latestBackupName = basename($latestBackupPath);
            $mtime = @filemtime($latestBackupPath);
            $latestBackupMtime = $mtime !== false ? date('Y-m-d H:i:s', $mtime) : '';
            $latestBackupSize = (int) (@filesize($latestBackupPath) ?: 0);
        }

        return [
            'path' => $path,
            'exists' => $exists,
            'writable' => $writable,
            'size_bytes' => $sizeBytes,
            'content' => $content,
            'latest_backup_name' => $latestBackupName,
            'latest_backup_mtime' => $latestBackupMtime,
            'latest_backup_size_bytes' => $latestBackupSize,
        ];
    }

    private function envFilePath(): string
    {
        return rtrim(ROOTPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.env';
    }

    private function latestEnvBackupPath(): ?string
    {
        $path = $this->envFilePath();
        $pattern = $path . '.bak.*';
        $matches = glob($pattern);
        if (! is_array($matches) || $matches === []) {
            return null;
        }

        $candidates = array_values(array_filter($matches, static function ($file): bool {
            return is_string($file) && is_file($file);
        }));

        if ($candidates === []) {
            return null;
        }

        usort($candidates, static function (string $a, string $b): int {
            $timeA = (int) (@filemtime($a) ?: 0);
            $timeB = (int) (@filemtime($b) ?: 0);
            if ($timeA === $timeB) {
                return strcmp($b, $a);
            }

            return $timeB <=> $timeA;
        });

        return $candidates[0] ?? null;
    }

    private function firstInvalidEnvLine(string $content): ?int
    {
        if (trim($content) === '') {
            return null;
        }

        $lines = explode("\n", $content);
        foreach ($lines as $index => $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            if (str_starts_with($trimmed, 'export ')) {
                $trimmed = trim(substr($trimmed, 7));
            }

            if (! str_contains($trimmed, '=')) {
                return $index + 1;
            }
        }

        return null;
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
        $personalWorkspaceName = trim((string) ($data['personal_workspace_name'] ?? ''));
        $personalWorkspaceName = $personalWorkspaceName === '' ? null : $personalWorkspaceName;
        $workspaceName = trim((string) ($data['workspace_name'] ?? ''));
        $workspaceName = $workspaceName === '' ? null : $workspaceName;

        if (! SubscriptionStatusService::isWorkspaceAccountType($accountType)) {
            if ($personalWorkspaceName === null) {
                return [
                    'payload' => [],
                    'account_type' => $accountType,
                    'pro_account_type' => null,
                    'default_reset_at' => date('Y-m-d H:i:s'),
                    'error' => 'Workspace personal wajib diisi untuk akun free.',
                ];
            }

            return [
                'payload' => [
                    'account_type' => 'free',
                    'pro_account_type' => null,
                    'workspace_name' => null,
                    'personal_workspace_name' => $personalWorkspaceName,
                    'is_workspace_deactivated' => 0,
                    'subscribed_at' => null,
                    'is_one_month_duration' => 0,
                    'expired_at' => null,
                    'status' => 'active',
                ],
                'account_type' => 'free',
                'pro_account_type' => null,
                'default_reset_at' => date('Y-m-d H:i:s'),
                'error' => null,
            ];
        }

        $proAccountType = SubscriptionStatusService::resolveProAccountTypeForAccount($accountType, $data['pro_account_type'] ?? null);

        $isWorkspaceDeactivated = SubscriptionStatusService::parseBoolean($data['is_workspace_deactivated'] ?? null, false);
        $isOneMonthDuration = SubscriptionStatusService::resolveOneMonthDurationForAccount(
            $accountType,
            SubscriptionStatusService::parseBoolean($data['is_one_month_duration'] ?? null, false)
        );
        $subscribedAt = $this->normalizeDateTimeInput($data['subscribed_at'] ?? null);

        if (SubscriptionStatusService::requiresInviteType($accountType) && $proAccountType === null) {
            return [
                'payload' => [],
                'account_type' => $accountType,
                'pro_account_type' => null,
                'default_reset_at' => date('Y-m-d H:i:s'),
                'error' => 'Jenis akun pro wajib dipilih (invite pribadi atau akun seller).',
            ];
        }

        if ($accountType === 'plus') {
            if ($personalWorkspaceName === null && $workspaceName !== null) {
                $personalWorkspaceName = $workspaceName;
            }

            if ($personalWorkspaceName === null) {
                return [
                    'payload' => [],
                    'account_type' => $accountType,
                    'pro_account_type' => $proAccountType,
                    'default_reset_at' => date('Y-m-d H:i:s'),
                    'error' => 'Workspace personal wajib diisi untuk akun plus.',
                ];
            }

            $workspaceName = $personalWorkspaceName;
        }

        if ($workspaceName === null) {
            return [
                'payload' => [],
                'account_type' => $accountType,
                'pro_account_type' => $proAccountType,
                'default_reset_at' => date('Y-m-d H:i:s'),
                'error' => 'Nama workspace wajib diisi untuk akun workspace (pro/plus).',
            ];
        }

        if ($subscribedAt === null) {
            return [
                'payload' => [],
                'account_type' => $accountType,
                'pro_account_type' => $proAccountType,
                'default_reset_at' => date('Y-m-d H:i:s'),
                'error' => 'Tanggal langganan wajib diisi untuk akun workspace (pro/plus).',
            ];
        }

        if ($accountType === 'pro' && $proAccountType === 'personal_invite' && $personalWorkspaceName === null) {
            return [
                'payload' => [],
                'account_type' => $accountType,
                'pro_account_type' => $proAccountType,
                'default_reset_at' => date('Y-m-d H:i:s'),
                'error' => 'Workspace personal (akun free) wajib diisi untuk tipe invite akun pribadi.',
            ];
        }

        if ($accountType === 'pro' && $proAccountType !== 'personal_invite') {
            $personalWorkspaceName = null;
        }

        $expiredAt = SubscriptionStatusService::isWorkspaceAccountType($accountType)
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
                'is_one_month_duration' => SubscriptionStatusService::isWorkspaceAccountType($accountType) ? ($isOneMonthDuration ? 1 : 0) : null,
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
                $usageId = (int) ($usage['id'] ?? 0);
                if ($usageId > 0 && $remainingPercent !== 100) {
                    $this->histories->insert([
                        'account_usage_id' => $usageId,
                        'old_percent' => $remainingPercent,
                        'new_percent' => 100,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }

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
            return SubscriptionStatusService::isWorkspaceAccountType($subscription['account_type'] ?? null);
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
            ->select('subscription_renewal_histories.*, subscriptions.workspace_name, subscriptions.personal_workspace_name, subscriptions.subscription_type, subscriptions.pro_account_type, subscriptions.account_type')
            ->join('subscriptions', 'subscriptions.id = subscription_renewal_histories.subscription_id')
            ->where('subscriptions.account_id', $accountId)
            ->whereIn('subscriptions.account_type', ['pro', 'plus']);

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

    private function currentUserId(): int
    {
        return (int) (session('user_id') ?? 0);
    }
}
