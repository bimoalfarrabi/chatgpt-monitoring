<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$statusClasses = [
    'active' => 'inline-flex items-center gap-1.5 rounded-full px-2 py-[3px] font-display text-[14px] leading-[1.5] border border-[color-mix(in_srgb,#1f8a65_35%,transparent_65%)] text-[#165a44] bg-[color-mix(in_srgb,#1f8a65_18%,#f2f1ed_82%)]',
    'expiring_soon' => 'inline-flex items-center gap-1.5 rounded-full px-2 py-[3px] font-display text-[14px] leading-[1.5] border border-[color-mix(in_srgb,#c08532_40%,transparent_60%)] text-[#8f4d10] bg-[color-mix(in_srgb,#c08532_22%,#f2f1ed_78%)]',
    'expired' => 'inline-flex items-center gap-1.5 rounded-full px-2 py-[3px] font-display text-[14px] leading-[1.5] border border-[color-mix(in_srgb,#cf2d56_40%,transparent_60%)] text-[#8f1f3c] bg-[color-mix(in_srgb,#cf2d56_18%,#f2f1ed_82%)]',
    'deactivated' => 'inline-flex items-center gap-1.5 rounded-full px-2 py-[3px] font-display text-[14px] leading-[1.5] border border-[color-mix(in_srgb,#444444_34%,transparent_66%)] text-[#2d2d2d] bg-[color-mix(in_srgb,#444444_14%,#f2f1ed_86%)]',
];

$cardBase = 'rounded-lg border border-[rgba(38,37,30,0.1)] p-4 shadow-[rgba(0,0,0,0.02)_0_0_16px,rgba(0,0,0,0.008)_0_0_8px] transition-[box-shadow,border-color] duration-200 hover:border-[rgba(38,37,30,0.2)] hover:shadow-[rgba(0,0,0,0.14)_0_28px_70px,rgba(0,0,0,0.1)_0_14px_32px]';

$inputClass = 'mt-1 w-full rounded-md border border-[rgba(38,37,30,0.22)] bg-surface200 px-3 py-2 font-ui text-[13px] leading-[1.45] text-[rgba(38,37,30,0.9)] shadow-[inset_0_1px_0_rgba(255,255,255,0.45)] outline-none transition-[border-color,box-shadow,background-color,color] duration-150 focus:border-[rgba(38,37,30,0.38)] focus:bg-[#f8f7f3] focus:shadow-[rgba(0,0,0,0.1)_0_4px_12px] disabled:cursor-not-allowed disabled:border-[rgba(38,37,30,0.12)] disabled:bg-[rgba(38,37,30,0.06)] disabled:text-[rgba(38,37,30,0.45)]';
$labelClass = 'font-ui text-[12px] uppercase tracking-[0.05em] font-medium text-[rgba(38,37,30,0.62)]';

$buttonPrimary = 'inline-flex items-center justify-center gap-1.5 rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 px-3 py-2 font-display text-[13px] font-medium tracking-[0.025em] text-ink transition-colors duration-150 hover:text-danger hover:border-[rgba(38,37,30,0.2)]';
$buttonSecondary = 'inline-flex items-center justify-center gap-1.5 rounded-md border border-[rgba(38,37,30,0.1)] bg-surface400 px-3 py-2 font-display text-[13px] font-medium tracking-[0.025em] text-[rgba(38,37,30,0.75)] transition-colors duration-150 hover:text-danger hover:border-[rgba(38,37,30,0.2)]';
$buttonDanger = 'inline-flex items-center justify-center gap-1.5 rounded-md border border-[color-mix(in_srgb,#cf2d56_40%,transparent_60%)] bg-[color-mix(in_srgb,#cf2d56_14%,#f2f1ed_86%)] px-3 py-2 font-display text-[13px] font-medium tracking-[0.025em] text-[#8f1f3c] transition-[border-color,box-shadow] duration-150 hover:border-[color-mix(in_srgb,#cf2d56_55%,transparent_45%)] hover:shadow-[rgba(0,0,0,0.1)_0_4px_12px]';
$accountPasswordStored = (string) ($account['password_hint'] ?? '');
$accountPasswordInput = (string) old('password_hint', $accountPasswordStored);
$routerUsage = is_array($routerUsage ?? null) ? $routerUsage : [];
$routerRequests24h = (int) ($routerUsage['requests_24h'] ?? 0);
$routerTokens24h = (int) ($routerUsage['tokens_24h'] ?? 0);
$routerRequests7d = (int) ($routerUsage['requests_7d'] ?? 0);
$routerTokens7d = (int) ($routerUsage['tokens_7d'] ?? 0);
$routerCacheRatio7d = (float) ($routerUsage['cache_ratio_7d'] ?? 0);
$routerLatency7dMs = (int) ($routerUsage['avg_latency_ms_7d'] ?? 0);
$routerLatency7dLabel = $routerLatency7dMs >= 1000
    ? number_format($routerLatency7dMs / 1000, 1) . 's'
    : number_format($routerLatency7dMs) . 'ms';
$routerLastSeen = trim((string) ($routerUsage['last_event_at'] ?? ''));
?>

<section class="space-y-2">
    <h1>Detail Akun</h1>
    <p class="max-w-[760px] font-serif text-[clamp(18px,1.35vw,20px)] leading-[1.45] text-[rgba(38,37,30,0.64)]">Ringkasan identitas akun, konfigurasi free/pro/plus untuk setiap subscription, status workspace, serta observability usage dari 9router.</p>
</section>

<section class="mt-6 <?= $cardBase ?> bg-surface400 space-y-3">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="space-y-1">
            <h3><?= esc($account['account_name']) ?></h3>
            <div class="flex flex-wrap items-center gap-2">
                <p class="font-ui text-[13px] leading-[1.44] tracking-[0.01em] text-[rgba(38,37,30,0.55)]">Email: <?= esc($account['email']) ?></p>
                <button
                    class="<?= $buttonSecondary ?> px-2 py-1 text-[12px]"
                    type="button"
                    data-copy-text="<?= esc((string) ($account['email'] ?? ''), 'attr') ?>"
                    data-copy-default-label="Copy Email"
                >
                    Copy Email
                </button>
            </div>
        </div>
        <form method="post" action="/accounts/<?= esc((string) $account['id']) ?>/delete" onsubmit="return confirm('Hapus akun ini beserta seluruh datanya?')">
            <button class="<?= $buttonDanger ?>" type="submit">Hapus Akun</button>
        </form>
    </div>

    <form method="post" action="/accounts/<?= esc((string) $account['id']) ?>/update-name" class="space-y-2 rounded-md border border-[rgba(38,37,30,0.12)] bg-surface300 p-3">
        <div class="grid gap-3 [grid-template-columns:repeat(auto-fit,minmax(220px,1fr))]">
            <label class="<?= $labelClass ?>">
                Ubah Nama Akun
                <input class="<?= $inputClass ?>" type="text" name="account_name" required value="<?= esc(old('account_name', (string) ($account['account_name'] ?? ''))) ?>">
            </label>
        </div>
        <div class="flex flex-wrap gap-2">
            <button class="<?= $buttonPrimary ?>" type="submit">Simpan Nama</button>
        </div>
    </form>

    <div class="grid gap-3 [grid-template-columns:repeat(auto-fit,minmax(220px,1fr))]">
        <article class="rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 p-3">
            <div class="font-ui text-[12px] uppercase tracking-[0.06em] font-medium text-[rgba(38,37,30,0.62)]">Password</div>
            <form method="post" action="/accounts/<?= esc((string) $account['id']) ?>/update-password" class="space-y-2">
                <div class="mt-1 flex flex-wrap items-center gap-2" data-password-field-group>
                    <input class="<?= $inputClass ?> mt-0 min-w-[180px] flex-1" type="password" name="password_hint" value="<?= esc($accountPasswordInput) ?>" autocomplete="off" data-password-field>
                    <button class="<?= $buttonSecondary ?>" type="button" data-password-toggle data-show-label="Unhide" data-hide-label="Hide">Unhide</button>
                    <button class="<?= $buttonSecondary ?>" type="button" data-password-copy>Copy</button>
                </div>
                <p class="font-ui text-[12px] text-[rgba(38,37,30,0.55)]">
                    Kosongkan lalu simpan jika ingin menghapus password tersimpan.
                </p>
                <button class="<?= $buttonPrimary ?>" type="submit">Simpan Password</button>
            </form>
        </article>
        <article class="rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 p-3">
            <div class="font-ui text-[12px] uppercase tracking-[0.06em] font-medium text-[rgba(38,37,30,0.62)]">Catatan</div>
            <div class="mt-1 font-display text-[15px] leading-[1.5] text-[rgba(38,37,30,0.82)]"><?= esc($account['notes'] ?? '-') ?></div>
        </article>
    </div>
</section>

<section class="mt-6 <?= $cardBase ?> bg-surface400 space-y-2">
    <h2>Usage 9router (Email Akun)</h2>
    <p class="font-ui text-[13px] leading-[1.44] tracking-[0.01em] text-[rgba(38,37,30,0.55)]">Ringkasan ini otomatis dihitung dari event 9router berdasarkan email akun ini.</p>
    <div class="grid gap-3 [grid-template-columns:repeat(auto-fit,minmax(180px,1fr))]">
        <article class="rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 p-3">
            <div class="font-ui text-[12px] uppercase tracking-[0.06em] font-medium text-[rgba(38,37,30,0.62)]">24 Jam</div>
            <div class="mt-1 font-display text-[24px] leading-[1.2] text-[rgba(38,37,30,0.86)]"><?= esc(number_format($routerTokens24h)) ?> token</div>
            <div class="font-mono text-[11px] leading-[1.55] tracking-[-0.01em] text-[rgba(38,37,30,0.62)]"><?= esc(number_format($routerRequests24h)) ?> request</div>
        </article>
        <article class="rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 p-3">
            <div class="font-ui text-[12px] uppercase tracking-[0.06em] font-medium text-[rgba(38,37,30,0.62)]">7 Hari</div>
            <div class="mt-1 font-display text-[24px] leading-[1.2] text-[rgba(38,37,30,0.86)]"><?= esc(number_format($routerTokens7d)) ?> token</div>
            <div class="font-mono text-[11px] leading-[1.55] tracking-[-0.01em] text-[rgba(38,37,30,0.62)]"><?= esc(number_format($routerRequests7d)) ?> request</div>
        </article>
        <article class="rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 p-3">
            <div class="font-ui text-[12px] uppercase tracking-[0.06em] font-medium text-[rgba(38,37,30,0.62)]">Cache Ratio 7 Hari</div>
            <div class="mt-1 font-display text-[24px] leading-[1.2] text-[rgba(38,37,30,0.86)]"><?= esc(number_format($routerCacheRatio7d, 1)) ?>%</div>
        </article>
        <article class="rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 p-3">
            <div class="font-ui text-[12px] uppercase tracking-[0.06em] font-medium text-[rgba(38,37,30,0.62)]">Latency Rata-rata 7 Hari</div>
            <div class="mt-1 font-display text-[24px] leading-[1.2] text-[rgba(38,37,30,0.86)]"><?= esc($routerLatency7dLabel) ?></div>
            <div class="font-mono text-[11px] leading-[1.55] tracking-[-0.01em] text-[rgba(38,37,30,0.62)]">Last seen: <?= esc($routerLastSeen !== '' ? $routerLastSeen : '-') ?></div>
        </article>
    </div>
</section>

<?php if ($subscriptions === []): ?>
    <section class="mt-6 rounded-lg border border-[rgba(38,37,30,0.1)] bg-surface400 px-4 py-3 font-ui text-[13px] text-[rgba(38,37,30,0.55)]">
        Belum ada subscription untuk akun ini.
    </section>
<?php endif; ?>

<?php foreach ($subscriptions as $subscription): ?>
    <?php
    $statusClass = $statusClasses[$subscription['status']] ?? $statusClasses['active'];
    $accountType = \App\Services\SubscriptionStatusService::normalizeAccountType((string) ($subscription['account_type'] ?? 'free'));
    $isWorkspace = \App\Services\SubscriptionStatusService::isWorkspaceAccountType($accountType);
    $isPro = $accountType === 'pro';
    $proType = (string) ($subscription['pro_account_type'] ?? '');
    $personalWorkspaceName = trim((string) ($subscription['personal_workspace_name'] ?? ''));
    $showPersonalWorkspace = $accountType === 'free' || $accountType === 'plus' || $proType === 'personal_invite';
    $proTypeLabel = $accountType === 'plus'
        ? 'Akun dari Seller (Personal)'
        : ($proType === 'personal_invite'
            ? 'Invite Akun Pribadi'
            : ($proType === 'seller_account' ? 'Akun dari Seller' : '-'));
    $formId = (int) $subscription['id'];
    ?>
    <section class="mt-6 <?= $cardBase ?> bg-surface400 space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="flex items-center gap-2.5">
                <?= esc($subscription['subscription_type']) ?>
                <span class="<?= $statusClass ?>">
                    <?= esc(\App\Services\SubscriptionStatusService::humanize((string) $subscription['status'])) ?>
                </span>
            </h3>
            <div class="font-mono text-[11px] leading-[1.55] tracking-[-0.01em] text-[rgba(38,37,30,0.76)]">
                Berakhir (otomatis): <?= esc($subscription['expired_at'] ?? '-') ?>
            </div>
        </div>

        <?php if ($isWorkspace): ?>
            <form method="post" action="/subscriptions/<?= esc((string) $subscription['id']) ?>/update" class="space-y-3">
                <div class="grid gap-3 [grid-template-columns:repeat(auto-fit,minmax(220px,1fr))]">
                    <label class="<?= $labelClass ?>">
                        Sumber Store
                        <input class="<?= $inputClass ?>" type="text" name="store_source" required value="<?= esc($subscription['store_source']) ?>">
                    </label>
                    <label class="<?= $labelClass ?>">
                        Tipe Subscription
                        <input class="<?= $inputClass ?>" type="text" name="subscription_type" required value="<?= esc($subscription['subscription_type']) ?>">
                    </label>
                    <label class="<?= $labelClass ?>">
                        Jenis Akun ChatGPT
                        <select class="<?= $inputClass ?>" name="account_type" required data-subscription-type-select="<?= esc((string) $formId) ?>">
                            <option value="free" <?= $accountType === 'free' ? 'selected' : '' ?>>Free (Personal)</option>
                            <option value="pro" <?= $accountType === 'pro' ? 'selected' : '' ?>>Pro (Workspace)</option>
                            <option value="plus" <?= $accountType === 'plus' ? 'selected' : '' ?>>Plus (Seller)</option>
                        </select>
                    </label>

                    <label class="<?= $labelClass ?> <?= $isPro ? '' : 'hidden' ?>" data-pro-invite-only="<?= esc((string) $formId) ?>">
                        Jenis Akun Pro
                        <select class="<?= $inputClass ?>" name="pro_account_type" data-pro-type-select="<?= esc((string) $formId) ?>" data-pro-invite-required="<?= esc((string) $formId) ?>">
                            <option value="">Pilih jenis akun pro</option>
                            <option value="personal_invite" <?= $proType === 'personal_invite' ? 'selected' : '' ?>>Invite Akun Pribadi</option>
                            <option value="seller_account" <?= $proType === 'seller_account' ? 'selected' : '' ?>>Akun dari Seller</option>
                        </select>
                    </label>
                    <label class="<?= $labelClass ?> <?= $isPro ? '' : 'hidden' ?>" data-pro-invite-only="<?= esc((string) $formId) ?>">
                        Nama Workspace Invite
                        <input class="<?= $inputClass ?>" type="text" name="workspace_name" value="<?= esc((string) ($subscription['workspace_name'] ?? '')) ?>" data-pro-invite-required="<?= esc((string) $formId) ?>">
                    </label>
                    <label class="<?= $labelClass ?> <?= $showPersonalWorkspace ? '' : 'hidden' ?>" data-personal-invite-only="<?= esc((string) $formId) ?>">
                        Workspace Personal
                        <input class="<?= $inputClass ?>" type="text" name="personal_workspace_name" value="<?= esc($personalWorkspaceName) ?>" data-personal-invite-required="<?= esc((string) $formId) ?>">
                    </label>
                    <label class="<?= $labelClass ?> <?= $isWorkspace ? '' : 'hidden' ?>" data-pro-only="<?= esc((string) $formId) ?>">
                        Status Workspace
                        <select class="<?= $inputClass ?>" name="is_workspace_deactivated" data-pro-required="<?= esc((string) $formId) ?>">
                            <option value="0" <?= ((int) ($subscription['is_workspace_deactivated'] ?? 0)) === 0 ? 'selected' : '' ?>>Aktif</option>
                            <option value="1" <?= ((int) ($subscription['is_workspace_deactivated'] ?? 0)) === 1 ? 'selected' : '' ?>>Deactivated</option>
                        </select>
                    </label>
                    <label class="<?= $labelClass ?> <?= $isWorkspace ? '' : 'hidden' ?>" data-pro-only="<?= esc((string) $formId) ?>">
                        Tanggal Langganan
                        <input class="<?= $inputClass ?>" type="datetime-local" name="subscribed_at" value="<?= esc(isset($subscription['subscribed_at']) && $subscription['subscribed_at'] ? date('Y-m-d\\TH:i', strtotime((string) $subscription['subscribed_at'])) : '') ?>" data-pro-required="<?= esc((string) $formId) ?>">
                    </label>
                    <label class="<?= $labelClass ?> <?= $isWorkspace ? '' : 'hidden' ?>" data-pro-only="<?= esc((string) $formId) ?>">
                        Durasi Satu Bulan?
                        <select class="<?= $inputClass ?>" name="is_one_month_duration" data-pro-required="<?= esc((string) $formId) ?>" data-one-month-select="<?= esc((string) $formId) ?>">
                            <option value="1" <?= ((int) ($subscription['is_one_month_duration'] ?? 0)) === 1 ? 'selected' : '' ?>>Ya</option>
                            <option value="0" <?= ((int) ($subscription['is_one_month_duration'] ?? 0)) === 0 ? 'selected' : '' ?>>Tidak</option>
                        </select>
                    </label>
                </div>
                <div class="font-ui text-[13px] leading-[1.44] tracking-[0.01em] text-[rgba(38,37,30,0.55)]">
                    Jenis akun workspace saat ini: <?= esc($isWorkspace ? $proTypeLabel : '-') ?>
                </div>
                <?php if ($showPersonalWorkspace): ?>
                    <div class="font-ui text-[13px] leading-[1.44] tracking-[0.01em] text-[rgba(38,37,30,0.55)]">
                        Workspace personal: <?= esc($personalWorkspaceName !== '' ? $personalWorkspaceName : '-') ?>
                    </div>
                <?php endif; ?>
                <button class="<?= $buttonPrimary ?>" type="submit">Perbarui Subscription</button>
            </form>

            <?php if ($isWorkspace && ((int) ($subscription['is_workspace_deactivated'] ?? 0)) === 0): ?>
                <div class="flex flex-wrap gap-2">
                    <form method="post" action="/subscriptions/<?= esc((string) $subscription['id']) ?>/renew" onsubmit="return confirm('Perpanjang subscription ini otomatis +1 bulan?')">
                        <button class="<?= $buttonSecondary ?>" type="submit">Perpanjang Subscription +1 Bulan (Auto)</button>
                    </form>
                    <form method="post" action="/subscriptions/<?= esc((string) $subscription['id']) ?>/deactivate" onsubmit="return confirm('Ubah status workspace ini menjadi deactivated?')">
                        <button class="<?= $buttonDanger ?>" type="submit">Set Deactivated</button>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($isWorkspace && ((int) ($subscription['is_workspace_deactivated'] ?? 0)) === 1): ?>
                <section class="rounded-md border border-[rgba(38,37,30,0.14)] bg-surface300 p-3 space-y-2">
                    <?php if ($accountType === 'plus'): ?>
                        <h4 class="text-[20px]">Update Akun Plus Deactivated</h4>
                        <p class="font-ui text-[13px] leading-[1.44] tracking-[0.01em] text-[rgba(38,37,30,0.55)]">
                            Jika akun plus lama sudah deactivated, Anda bisa langsung update data akun + buat subscription plus pengganti tanpa hapus akun dari awal.
                        </p>
                        <form method="post" action="/subscriptions/<?= esc((string) $subscription['id']) ?>/plus/update-deactivated" class="space-y-3">
                            <div class="grid gap-3 [grid-template-columns:repeat(auto-fit,minmax(220px,1fr))]">
                                <label class="<?= $labelClass ?>">
                                    Nama Akun Plus Baru
                                    <input class="<?= $inputClass ?>" type="text" name="plus_account_name" required value="<?= esc(old('plus_account_name', (string) ($account['account_name'] ?? ''))) ?>">
                                </label>
                                <label class="<?= $labelClass ?>">
                                    Email Akun Plus Baru
                                    <input class="<?= $inputClass ?>" type="email" name="plus_email" required value="<?= esc(old('plus_email', (string) ($account['email'] ?? ''))) ?>">
                                </label>
                                <label class="<?= $labelClass ?>">
                                    Password Akun Plus Baru
                                    <div class="mt-1 flex flex-wrap items-center gap-2" data-password-field-group>
                                        <input class="<?= $inputClass ?> mt-0 min-w-[180px] flex-1" type="password" name="plus_password_hint" value="<?= esc(old('plus_password_hint', (string) ($account['password_hint'] ?? ''))) ?>" autocomplete="off" data-password-field>
                                        <button class="<?= $buttonSecondary ?>" type="button" data-password-toggle data-show-label="Unhide" data-hide-label="Hide">Unhide</button>
                                        <button class="<?= $buttonSecondary ?>" type="button" data-password-copy>Copy</button>
                                    </div>
                                </label>
                                <label class="<?= $labelClass ?>">
                                    Tanggal Langganan Baru
                                    <input class="<?= $inputClass ?>" type="datetime-local" name="plus_subscribed_at" required value="<?= esc(old('plus_subscribed_at', date('Y-m-d\\TH:i'))) ?>">
                                </label>
                            </div>
                            <p class="font-ui text-[12px] leading-[1.4] text-[rgba(38,37,30,0.55)]">
                                Data workspace personal, sumber store, dan tipe subscription akan mengikuti data sebelumnya secara otomatis.
                            </p>
                            <label class="<?= $labelClass ?>">
                                Catatan Akun
                                <textarea class="<?= $inputClass ?>" name="plus_notes" rows="3"><?= esc(old('plus_notes', (string) ($account['notes'] ?? ''))) ?></textarea>
                            </label>

                            <button class="<?= $buttonPrimary ?>" type="submit">Update Akun Plus & Buat Subscription Baru</button>
                        </form>
                    <?php else: ?>
                        <h4 class="text-[20px]">Buat Workspace Baru</h4>
                        <p class="font-ui text-[13px] leading-[1.44] tracking-[0.01em] text-[rgba(38,37,30,0.55)]">
                            Workspace ini sudah deactivated. Buat workspace pengganti, sementara data lama tetap tersimpan sebagai histori.
                        </p>

                        <form method="post" action="/subscriptions/<?= esc((string) $subscription['id']) ?>/workspace/create" class="space-y-3" data-workspace-create-form>
                            <div class="grid gap-3 [grid-template-columns:repeat(auto-fit,minmax(220px,1fr))]">
                                <label class="<?= $labelClass ?>">
                                    Sumber Store
                                    <input class="<?= $inputClass ?>" type="text" name="store_source" required value="<?= esc($subscription['store_source']) ?>">
                                </label>
                                <label class="<?= $labelClass ?>">
                                    Tipe Subscription
                                    <input class="<?= $inputClass ?>" type="text" name="subscription_type" required value="<?= esc($subscription['subscription_type']) ?>">
                                </label>
                                <label class="<?= $labelClass ?> <?= $isPro ? '' : 'hidden' ?>" data-workspace-create-pro-invite-only>
                                    Jenis Akun Pro
                                    <select class="<?= $inputClass ?>" name="pro_account_type" <?= $isPro ? 'required' : '' ?> data-workspace-create-pro-select data-workspace-create-pro-invite-required>
                                        <option value="">Pilih jenis akun pro</option>
                                        <option value="personal_invite" <?= $proType === 'personal_invite' ? 'selected' : '' ?>>Invite Akun Pribadi</option>
                                        <option value="seller_account" <?= $proType === 'seller_account' ? 'selected' : '' ?>>Akun dari Seller</option>
                                    </select>
                                </label>
                                <label class="<?= $labelClass ?> <?= $isPro ? '' : 'hidden' ?>" data-workspace-create-pro-invite-only>
                                    Nama Workspace Invite Baru
                                    <input class="<?= $inputClass ?>" type="text" name="workspace_name" <?= $isPro ? 'required' : '' ?> value="" data-workspace-create-pro-invite-required>
                                </label>
                                <label class="<?= $labelClass ?> <?= $showPersonalWorkspace ? '' : 'hidden' ?>" data-workspace-create-personal-wrapper>
                                    Workspace Personal
                                    <input class="<?= $inputClass ?>" type="text" name="personal_workspace_name" value="<?= esc($personalWorkspaceName) ?>" data-workspace-create-personal-input>
                                </label>
                                <label class="<?= $labelClass ?>">
                                    Tanggal Langganan Baru
                                    <input class="<?= $inputClass ?>" type="datetime-local" name="subscribed_at" required value="<?= esc(date('Y-m-d\\TH:i')) ?>">
                                </label>
                                <label class="<?= $labelClass ?>">
                                    Durasi Satu Bulan?
                                    <select class="<?= $inputClass ?>" name="is_one_month_duration" data-workspace-create-one-month-select>
                                        <option value="1" selected>Ya</option>
                                        <option value="0">Tidak</option>
                                    </select>
                                </label>
                            </div>

                            <button class="<?= $buttonPrimary ?>" type="submit">Simpan Workspace Baru</button>
                        </form>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        <?php else: ?>
            <?php $freePersonalWorkspace = trim((string) ($subscription['personal_workspace_name'] ?? '')); ?>
            <div class="font-ui text-[13px] leading-[1.44] tracking-[0.01em] text-[rgba(38,37,30,0.55)]">
                Akun free tidak menggunakan form subscription manual.
            </div>
            <div class="font-ui text-[13px] leading-[1.44] tracking-[0.01em] text-[rgba(38,37,30,0.55)]">
                Workspace personal: <?= esc($freePersonalWorkspace !== '' ? $freePersonalWorkspace : '-') ?>
            </div>
        <?php endif; ?>

        <div class="rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 px-3 py-2 font-ui text-[13px] leading-[1.44] tracking-[0.01em] text-[rgba(38,37,30,0.55)]">
            Penggunaan mengikuti panel <strong class="font-medium text-[rgba(38,37,30,0.82)]">Usage 9router (Email Akun)</strong> di bagian atas halaman ini.
        </div>
    </section>
<?php endforeach; ?>

<section class="mt-6 <?= $cardBase ?> bg-surface400 space-y-2">
    <h2>Histori Workspace</h2>
    <div data-history-section="workspace" data-account-id="<?= esc((string) $account['id']) ?>">
        <?= view('accounts/partials/history_workspace', [
            'workspaceHistory' => $workspaceHistoryPage['rows'] ?? [],
            'pagination' => $workspaceHistoryPage['pagination'] ?? [],
        ]) ?>
    </div>
</section>

<section class="mt-6 <?= $cardBase ?> bg-surface400 space-y-2">
    <h2>Riwayat Perpanjangan Subscription</h2>
    <div data-history-section="renewal" data-account-id="<?= esc((string) $account['id']) ?>">
        <?= view('accounts/partials/history_renewal', [
            'renewalHistory' => $renewalHistoryPage['rows'] ?? [],
            'pagination' => $renewalHistoryPage['pagination'] ?? [],
        ]) ?>
    </div>
</section>

<script>
(() => {
    const historySections = Array.from(document.querySelectorAll('[data-history-section][data-account-id]'));
    historySections.forEach((section) => {
        section.addEventListener('click', async (event) => {
            const pageButton = event.target.closest('[data-history-page]');
            if (!pageButton || !section.contains(pageButton) || pageButton.disabled) {
                return;
            }

            const accountId = section.getAttribute('data-account-id');
            const historySection = section.getAttribute('data-history-section');
            const targetPage = Number(pageButton.getAttribute('data-history-page') || 1);

            if (!accountId || !historySection || !Number.isInteger(targetPage) || targetPage < 1) {
                return;
            }

            if (section.getAttribute('data-history-loading') === '1') {
                return;
            }

            section.setAttribute('data-history-loading', '1');
            section.classList.add('opacity-70', 'pointer-events-none');

            try {
                const response = await fetch(`/accounts/${encodeURIComponent(accountId)}/history/${encodeURIComponent(historySection)}?page=${targetPage}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const payload = await response.json();
                if (!payload?.success || typeof payload.html !== 'string') {
                    throw new Error('Invalid history response.');
                }

                section.innerHTML = payload.html;
            } catch (error) {
                window.alert('Gagal memuat data riwayat. Silakan coba lagi.');
            } finally {
                section.removeAttribute('data-history-loading');
                section.classList.remove('opacity-70', 'pointer-events-none');
            }
        });
    });

    const selectors = Array.from(document.querySelectorAll('[data-subscription-type-select]'));

    const syncSubscriptionForm = (formId, currentValue) => {
        const isWorkspace = currentValue === 'pro' || currentValue === 'plus';
        const isPro = currentValue === 'pro';
        const blocks = Array.from(document.querySelectorAll(`[data-pro-only="${formId}"]`));
        const requiredFields = Array.from(document.querySelectorAll(`[data-pro-required="${formId}"]`));
        const proInviteBlocks = Array.from(document.querySelectorAll(`[data-pro-invite-only="${formId}"]`));
        const proInviteRequiredFields = Array.from(document.querySelectorAll(`[data-pro-invite-required="${formId}"]`));
        const personalInviteBlocks = Array.from(document.querySelectorAll(`[data-personal-invite-only="${formId}"]`));
        const personalInviteRequiredFields = Array.from(document.querySelectorAll(`[data-personal-invite-required="${formId}"]`));
        const proTypeSelect = document.querySelector(`[data-pro-type-select="${formId}"]`);
        const oneMonthSelect = document.querySelector(`[data-one-month-select="${formId}"]`);

        if (proTypeSelect && !isPro) {
            proTypeSelect.value = isWorkspace ? 'seller_account' : '';
        }

        const isPersonalInvite = currentValue === 'free' || currentValue === 'plus' || (isPro && proTypeSelect?.value === 'personal_invite');

        blocks.forEach((element) => {
            element.classList.toggle('hidden', !isWorkspace);
        });

        requiredFields.forEach((field) => {
            field.required = isWorkspace;
            field.disabled = !isWorkspace;

            if (!isWorkspace) {
                if (field.name === 'is_workspace_deactivated') {
                    field.value = '0';
                }
                if (field.name === 'is_one_month_duration') {
                    field.value = '1';
                }
            }
        });

        if (oneMonthSelect) {
            const forceOneMonth = currentValue === 'plus';
            if (forceOneMonth) {
                oneMonthSelect.value = '1';
            }
            oneMonthSelect.disabled = !isWorkspace || forceOneMonth;
        }

        proInviteBlocks.forEach((element) => {
            element.classList.toggle('hidden', !isPro);
        });

        proInviteRequiredFields.forEach((field) => {
            field.required = isPro;
            field.disabled = !isPro;

            if (!isPro) {
                field.value = isWorkspace ? 'seller_account' : '';
            }
        });

        personalInviteBlocks.forEach((element) => {
            element.classList.toggle('hidden', !isPersonalInvite);
        });

        personalInviteRequiredFields.forEach((field) => {
            field.required = isPersonalInvite;
            field.disabled = !isPersonalInvite;

            if (!isPersonalInvite) {
                field.value = '';
            }
        });
    };

    selectors.forEach((select) => {
        const formId = select.getAttribute('data-subscription-type-select');
        if (!formId) {
            return;
        }

        const onChange = () => {
            syncSubscriptionForm(formId, select.value);
        };

        select.addEventListener('change', onChange);
        const proTypeSelect = document.querySelector(`[data-pro-type-select="${formId}"]`);
        proTypeSelect?.addEventListener('change', onChange);
        onChange();
    });

    const workspaceCreateForms = Array.from(document.querySelectorAll('[data-workspace-create-form]'));
    workspaceCreateForms.forEach((form) => {
        const accountTypeInput = form.closest('section')?.querySelector('[data-subscription-type-select]');
        const proTypeSelect = form.querySelector('[data-workspace-create-pro-select]');
        const proInviteBlocks = Array.from(form.querySelectorAll('[data-workspace-create-pro-invite-only]'));
        const proInviteRequiredFields = Array.from(form.querySelectorAll('[data-workspace-create-pro-invite-required]'));
        const personalWrapper = form.querySelector('[data-workspace-create-personal-wrapper]');
        const personalInput = form.querySelector('[data-workspace-create-personal-input]');
        const oneMonthSelect = form.querySelector('[data-workspace-create-one-month-select]');

        if (!proTypeSelect || !personalWrapper || !personalInput) {
            return;
        }

        const syncWorkspaceCreateForm = () => {
            const isPro = (accountTypeInput?.value || 'pro') === 'pro';
            if (!isPro) {
                proTypeSelect.value = 'seller_account';
            }

            proInviteBlocks.forEach((element) => {
                element.classList.toggle('hidden', !isPro);
            });

            proInviteRequiredFields.forEach((field) => {
                field.required = isPro;
                field.disabled = !isPro;

                if (!isPro) {
                    field.value = 'seller_account';
                }
            });

            const accountType = accountTypeInput?.value || 'pro';
            const isPersonalInvite = accountType === 'plus' || proTypeSelect.value === 'personal_invite';

            if (oneMonthSelect) {
                const forceOneMonth = accountType === 'plus';
                if (forceOneMonth) {
                    oneMonthSelect.value = '1';
                }
                oneMonthSelect.disabled = forceOneMonth;
            }

            personalWrapper.classList.toggle('hidden', !isPersonalInvite);
            personalInput.required = isPersonalInvite;
            personalInput.disabled = !isPersonalInvite;

            if (!isPersonalInvite) {
                personalInput.value = '';
            }
        };

        accountTypeInput?.addEventListener('change', syncWorkspaceCreateForm);
        proTypeSelect.addEventListener('change', syncWorkspaceCreateForm);
        syncWorkspaceCreateForm();
    });

    const passwordGroups = Array.from(document.querySelectorAll('[data-password-field-group]'));
    passwordGroups.forEach((group) => {
        const field = group.querySelector('[data-password-field]');
        const toggleButton = group.querySelector('[data-password-toggle]');
        const copyButton = group.querySelector('[data-password-copy]');

        if (!field) {
            return;
        }

        const showLabel = toggleButton?.getAttribute('data-show-label') || 'Unhide';
        const hideLabel = toggleButton?.getAttribute('data-hide-label') || 'Hide';
        const copyLabel = copyButton?.textContent || 'Copy';

        const syncToggleLabel = () => {
            if (!toggleButton) {
                return;
            }

            toggleButton.textContent = field.type === 'password' ? showLabel : hideLabel;
        };

        if (toggleButton) {
            toggleButton.addEventListener('click', () => {
                field.type = field.type === 'password' ? 'text' : 'password';
                syncToggleLabel();
            });
            syncToggleLabel();
        }

        if (copyButton) {
            copyButton.addEventListener('click', async () => {
                const value = field.value || '';
                if (value === '') {
                    return;
                }

                let copied = false;
                if (navigator.clipboard?.writeText) {
                    try {
                        await navigator.clipboard.writeText(value);
                        copied = true;
                    } catch (error) {
                        copied = false;
                    }
                }

                if (!copied) {
                    const previousType = field.type;
                    field.type = 'text';
                    field.focus();
                    field.select();
                    copied = document.execCommand('copy');
                    field.setSelectionRange(field.value.length, field.value.length);
                    field.type = previousType;
                }

                if (copied) {
                    copyButton.textContent = 'Copied';
                    setTimeout(() => {
                        copyButton.textContent = copyLabel;
                    }, 1200);
                }
            });
        }
    });

    const quickCopyButtons = Array.from(document.querySelectorAll('[data-copy-text]'));
    quickCopyButtons.forEach((button) => {
        button.addEventListener('click', async () => {
            const value = button.getAttribute('data-copy-text') || '';
            if (value === '') {
                return;
            }

            let copied = false;
            if (navigator.clipboard?.writeText) {
                try {
                    await navigator.clipboard.writeText(value);
                    copied = true;
                } catch (error) {
                    copied = false;
                }
            }

            if (!copied) {
                const tempInput = document.createElement('input');
                tempInput.type = 'text';
                tempInput.value = value;
                tempInput.setAttribute('readonly', 'readonly');
                tempInput.style.position = 'fixed';
                tempInput.style.left = '-1000px';
                document.body.appendChild(tempInput);
                tempInput.select();
                copied = document.execCommand('copy');
                document.body.removeChild(tempInput);
            }

            if (copied) {
                const defaultLabel = button.getAttribute('data-copy-default-label') || 'Copy';
                button.textContent = 'Copied';
                setTimeout(() => {
                    button.textContent = defaultLabel;
                }, 1200);
            }
        });
    });
})();
</script>
<?= $this->endSection() ?>
