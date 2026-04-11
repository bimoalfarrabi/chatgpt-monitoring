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
$readonlyInputClass = 'mt-1 w-full rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 px-3 py-2 font-ui text-[13px] leading-[1.45] text-[rgba(38,37,30,0.62)] shadow-[inset_0_1px_0_rgba(255,255,255,0.35)] cursor-default';
$labelClass = 'font-ui text-[12px] uppercase tracking-[0.05em] font-medium text-[rgba(38,37,30,0.62)]';

$buttonPrimary = 'inline-flex items-center justify-center gap-1.5 rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 px-3 py-2 font-display text-[13px] font-medium tracking-[0.025em] text-ink transition-colors duration-150 hover:text-danger hover:border-[rgba(38,37,30,0.2)]';
$buttonSecondary = 'inline-flex items-center justify-center gap-1.5 rounded-md border border-[rgba(38,37,30,0.1)] bg-surface400 px-3 py-2 font-display text-[13px] font-medium tracking-[0.025em] text-[rgba(38,37,30,0.75)] transition-colors duration-150 hover:text-danger hover:border-[rgba(38,37,30,0.2)]';
$buttonDanger = 'inline-flex items-center justify-center gap-1.5 rounded-md border border-[color-mix(in_srgb,#cf2d56_40%,transparent_60%)] bg-[color-mix(in_srgb,#cf2d56_14%,#f2f1ed_86%)] px-3 py-2 font-display text-[13px] font-medium tracking-[0.025em] text-[#8f1f3c] transition-[border-color,box-shadow] duration-150 hover:border-[color-mix(in_srgb,#cf2d56_55%,transparent_45%)] hover:shadow-[rgba(0,0,0,0.1)_0_4px_12px]';
$todayMin = date('Y-m-d\\T00:00');
$accountPassword = (string) ($account['password_hint'] ?? '');
$chartDateDefault = date('Y-m-d');
?>

<section class="space-y-2">
    <h1>Detail Akun</h1>
    <p class="max-w-[760px] font-serif text-[clamp(18px,1.35vw,20px)] leading-[1.45] text-[rgba(38,37,30,0.64)]">Ringkasan identitas akun, konfigurasi free/pro untuk setiap subscription, status workspace, serta histori perubahan usage.</p>
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

    <div class="grid gap-3 [grid-template-columns:repeat(auto-fit,minmax(220px,1fr))]">
        <article class="rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 p-3">
            <div class="font-ui text-[12px] uppercase tracking-[0.06em] font-medium text-[rgba(38,37,30,0.62)]">Password</div>
            <div class="mt-1 flex flex-wrap items-center gap-2" data-password-field-group>
                <input class="<?= $readonlyInputClass ?> mt-0 min-w-[180px] flex-1" type="password" value="<?= esc($accountPassword) ?>" readonly data-password-field>
                <button class="<?= $buttonSecondary ?>" type="button" data-password-toggle data-show-label="Unhide" data-hide-label="Hide" <?= $accountPassword === '' ? 'disabled' : '' ?>>Unhide</button>
                <button class="<?= $buttonSecondary ?>" type="button" data-password-copy <?= $accountPassword === '' ? 'disabled' : '' ?>>Copy</button>
            </div>
            <?php if ($accountPassword === ''): ?>
                <div class="mt-2 font-ui text-[12px] text-[rgba(38,37,30,0.55)]">Belum ada password tersimpan.</div>
            <?php endif; ?>
        </article>
        <article class="rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 p-3">
            <div class="font-ui text-[12px] uppercase tracking-[0.06em] font-medium text-[rgba(38,37,30,0.62)]">Catatan</div>
            <div class="mt-1 font-display text-[15px] leading-[1.5] text-[rgba(38,37,30,0.82)]"><?= esc($account['notes'] ?? '-') ?></div>
        </article>
    </div>
</section>

<section class="mt-6 <?= $cardBase ?> bg-surface400 space-y-2">
    <h2>Grafik Penggunaan Akun Ini</h2>
    <p class="font-ui text-[13px] leading-[1.44] tracking-[0.01em] text-[rgba(38,37,30,0.55)]">
        Sumbu vertikal menunjukkan persentase usage, sumbu horizontal menunjukkan waktu update pada tanggal terpilih.
    </p>
    <div class="flex flex-wrap items-end gap-2">
        <label class="<?= $labelClass ?>">
            Tanggal Data
            <input
                type="date"
                value="<?= esc($chartDateDefault) ?>"
                data-account-chart-date
                class="<?= $inputClass ?> w-[220px]"
            >
        </label>
        <p class="font-ui text-[12px] leading-[1.4] text-[rgba(38,37,30,0.55)]" data-account-chart-caption>
            Menampilkan data berdasarkan tanggal terpilih.
        </p>
    </div>
    <div class="grid gap-3 [grid-template-columns:repeat(auto-fit,minmax(320px,1fr))]">
        <article class="rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 p-3 space-y-2">
            <h3>Usage Weekly</h3>
            <div
                id="account-usage-chart-weekly"
                data-chart-endpoint="/accounts/<?= esc((string) $account['id']) ?>/usage-chart"
                data-initial-date="<?= esc($chartDateDefault, 'attr') ?>"
                class="rounded-md border border-[rgba(38,37,30,0.1)] bg-surface400 p-2"
            ></div>
        </article>
        <article class="rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 p-3 space-y-2">
            <h3>Usage 5h</h3>
            <div
                id="account-usage-chart-5h"
                class="rounded-md border border-[rgba(38,37,30,0.1)] bg-surface400 p-2"
            ></div>
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
    $usageAccent = [
        '5h' => 'border-[color-mix(in_srgb,#9fbbe0_36%,rgba(38,37,30,0.1)_64%)] bg-[color-mix(in_srgb,#9fbbe0_16%,#ebeae5_84%)]',
        'weekly' => 'border-[color-mix(in_srgb,#c0a8dd_34%,rgba(38,37,30,0.1)_66%)] bg-[color-mix(in_srgb,#c0a8dd_16%,#ebeae5_84%)]',
        'weekly_personal' => 'border-[color-mix(in_srgb,#8fb8aa_34%,rgba(38,37,30,0.1)_66%)] bg-[color-mix(in_srgb,#8fb8aa_16%,#ebeae5_84%)]',
    ];
    $accountType = \App\Services\SubscriptionStatusService::normalizeAccountType((string) ($subscription['account_type'] ?? 'free'));
    $isPro = $accountType === 'pro';
    $proType = (string) ($subscription['pro_account_type'] ?? '');
    $personalWorkspaceName = trim((string) ($subscription['personal_workspace_name'] ?? ''));
    $proTypeLabel = $proType === 'personal_invite'
        ? 'Invite Akun Pribadi'
        : ($proType === 'seller_account' ? 'Akun dari Seller' : '-');
    $usageLabels = $isPro
        ? ($proType === 'personal_invite'
            ? [
                '5h' => 'Usage 5 Jam (Workspace Seller)',
                'weekly' => 'Usage Mingguan (Workspace Seller)',
                'weekly_personal' => 'Usage Mingguan (Personal Free)',
            ]
            : ['5h' => 'Usage 5 Jam', 'weekly' => 'Usage Mingguan'])
        : ['weekly' => 'Usage Mingguan'];
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

        <?php if ($isPro): ?>
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
                            <option value="pro" selected>Pro (Workspace)</option>
                        </select>
                    </label>

                    <label class="<?= $labelClass ?> <?= $isPro ? '' : 'hidden' ?>" data-pro-only="<?= esc((string) $formId) ?>">
                        Jenis Akun Pro
                        <select class="<?= $inputClass ?>" name="pro_account_type" data-pro-type-select="<?= esc((string) $formId) ?>" data-pro-required="<?= esc((string) $formId) ?>">
                            <option value="">Pilih jenis akun pro</option>
                            <option value="personal_invite" <?= $proType === 'personal_invite' ? 'selected' : '' ?>>Invite Akun Pribadi</option>
                            <option value="seller_account" <?= $proType === 'seller_account' ? 'selected' : '' ?>>Akun dari Seller</option>
                        </select>
                    </label>
                    <label class="<?= $labelClass ?> <?= $isPro ? '' : 'hidden' ?>" data-pro-only="<?= esc((string) $formId) ?>">
                        Nama Workspace
                        <input class="<?= $inputClass ?>" type="text" name="workspace_name" value="<?= esc((string) ($subscription['workspace_name'] ?? '')) ?>" data-pro-required="<?= esc((string) $formId) ?>">
                    </label>
                    <label class="<?= $labelClass ?> <?= $isPro && $proType === 'personal_invite' ? '' : 'hidden' ?>" data-personal-invite-only="<?= esc((string) $formId) ?>">
                        Workspace Personal (Free Weekly)
                        <input class="<?= $inputClass ?>" type="text" name="personal_workspace_name" value="<?= esc($personalWorkspaceName) ?>" data-personal-invite-required="<?= esc((string) $formId) ?>">
                    </label>
                    <label class="<?= $labelClass ?> <?= $isPro ? '' : 'hidden' ?>" data-pro-only="<?= esc((string) $formId) ?>">
                        Status Workspace
                        <select class="<?= $inputClass ?>" name="is_workspace_deactivated" data-pro-required="<?= esc((string) $formId) ?>">
                            <option value="0" <?= ((int) ($subscription['is_workspace_deactivated'] ?? 0)) === 0 ? 'selected' : '' ?>>Aktif</option>
                            <option value="1" <?= ((int) ($subscription['is_workspace_deactivated'] ?? 0)) === 1 ? 'selected' : '' ?>>Deactivated</option>
                        </select>
                    </label>
                    <label class="<?= $labelClass ?> <?= $isPro ? '' : 'hidden' ?>" data-pro-only="<?= esc((string) $formId) ?>">
                        Tanggal Langganan
                        <input class="<?= $inputClass ?>" type="datetime-local" name="subscribed_at" value="<?= esc(isset($subscription['subscribed_at']) && $subscription['subscribed_at'] ? date('Y-m-d\\TH:i', strtotime((string) $subscription['subscribed_at'])) : '') ?>" data-pro-required="<?= esc((string) $formId) ?>">
                    </label>
                    <label class="<?= $labelClass ?> <?= $isPro ? '' : 'hidden' ?>" data-pro-only="<?= esc((string) $formId) ?>">
                        Durasi Satu Bulan?
                        <select class="<?= $inputClass ?>" name="is_one_month_duration" data-pro-required="<?= esc((string) $formId) ?>">
                            <option value="1" <?= ((int) ($subscription['is_one_month_duration'] ?? 0)) === 1 ? 'selected' : '' ?>>Ya</option>
                            <option value="0" <?= ((int) ($subscription['is_one_month_duration'] ?? 0)) === 0 ? 'selected' : '' ?>>Tidak</option>
                        </select>
                    </label>
                </div>
                <div class="font-ui text-[13px] leading-[1.44] tracking-[0.01em] text-[rgba(38,37,30,0.55)]">
                    Jenis akun pro saat ini: <?= esc($isPro ? $proTypeLabel : '-') ?>
                </div>
                <?php if ($isPro && $proType === 'personal_invite'): ?>
                    <div class="font-ui text-[13px] leading-[1.44] tracking-[0.01em] text-[rgba(38,37,30,0.55)]">
                        Workspace personal free: <?= esc($personalWorkspaceName !== '' ? $personalWorkspaceName : '-') ?>
                    </div>
                <?php endif; ?>
                <button class="<?= $buttonPrimary ?>" type="submit">Perbarui Subscription</button>
            </form>

            <?php if ($isPro && ((int) ($subscription['is_workspace_deactivated'] ?? 0)) === 0): ?>
                <form method="post" action="/subscriptions/<?= esc((string) $subscription['id']) ?>/renew" onsubmit="return confirm('Perpanjang subscription ini otomatis +1 bulan?')">
                    <button class="<?= $buttonSecondary ?>" type="submit">Perpanjang Subscription +1 Bulan (Auto)</button>
                </form>
            <?php endif; ?>

            <?php if ($isPro && ((int) ($subscription['is_workspace_deactivated'] ?? 0)) === 1): ?>
                <section class="rounded-md border border-[rgba(38,37,30,0.14)] bg-surface300 p-3 space-y-2">
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
                            <label class="<?= $labelClass ?>">
                                Jenis Akun Pro
                                <select class="<?= $inputClass ?>" name="pro_account_type" required data-workspace-create-pro-select>
                                    <option value="">Pilih jenis akun pro</option>
                                    <option value="personal_invite" <?= $proType === 'personal_invite' ? 'selected' : '' ?>>Invite Akun Pribadi</option>
                                    <option value="seller_account" <?= $proType === 'seller_account' ? 'selected' : '' ?>>Akun dari Seller</option>
                                </select>
                            </label>
                            <label class="<?= $labelClass ?>">
                                Nama Workspace Baru
                                <input class="<?= $inputClass ?>" type="text" name="workspace_name" required value="">
                            </label>
                            <label class="<?= $labelClass ?> <?= $proType === 'personal_invite' ? '' : 'hidden' ?>" data-workspace-create-personal-wrapper>
                                Workspace Personal (Free Weekly)
                                <input class="<?= $inputClass ?>" type="text" name="personal_workspace_name" value="<?= esc($personalWorkspaceName) ?>" data-workspace-create-personal-input>
                            </label>
                            <label class="<?= $labelClass ?>">
                                Tanggal Langganan Baru
                                <input class="<?= $inputClass ?>" type="datetime-local" name="subscribed_at" required value="<?= esc(date('Y-m-d\\TH:i')) ?>">
                            </label>
                            <label class="<?= $labelClass ?>">
                                Durasi Satu Bulan?
                                <select class="<?= $inputClass ?>" name="is_one_month_duration">
                                    <option value="1" selected>Ya</option>
                                    <option value="0">Tidak</option>
                                </select>
                            </label>
                        </div>

                        <button class="<?= $buttonPrimary ?>" type="submit">Simpan Workspace Baru</button>
                    </form>
                </section>
            <?php endif; ?>
        <?php else: ?>
            <div class="font-ui text-[13px] leading-[1.44] tracking-[0.01em] text-[rgba(38,37,30,0.55)]">
                Akun free tidak menggunakan form subscription. Data weekly tetap bisa dipantau dan diperbarui di bawah.
            </div>
        <?php endif; ?>

        <div class="grid gap-3 [grid-template-columns:repeat(auto-fit,minmax(220px,1fr))]">
            <?php foreach ($usageLabels as $type => $label): ?>
                <?php $usage = $subscription['usages'][$type] ?? null; ?>
                <article class="rounded-md border p-3 space-y-2 <?= $usageAccent[$type] ?>">
                    <div class="flex items-start justify-between gap-2">
                        <h4 class="text-[20px]"><?= esc($label) ?></h4>
                        <?php if ($usage): ?>
                            <?php $percent = (int) $usage['remaining_percent']; ?>
                            <?php
                            $percentBadge = $percent > 60
                                ? 'border-[color-mix(in_srgb,#1f8a65_40%,transparent_60%)] text-[#165a44] bg-[color-mix(in_srgb,#1f8a65_16%,#f2f1ed_84%)]'
                                : ($percent > 30
                                    ? 'border-[color-mix(in_srgb,#c08532_42%,transparent_58%)] text-[#8f4d10] bg-[color-mix(in_srgb,#c08532_22%,#f2f1ed_78%)]'
                                    : 'border-[color-mix(in_srgb,#cf2d56_42%,transparent_58%)] text-[#8f1f3c] bg-[color-mix(in_srgb,#cf2d56_16%,#f2f1ed_84%)]');
                            ?>
                            <span class="inline-flex items-center rounded-full border px-2 py-[3px] font-display text-[13px] leading-[1.5] <?= $percentBadge ?>"><?= esc((string) $percent) ?>%</span>
                        <?php endif; ?>
                    </div>

                    <?php if (! $usage): ?>
                        <p class="font-ui text-[13px] leading-[1.44] tracking-[0.01em] text-[rgba(38,37,30,0.55)]">Data usage belum tersedia.</p>
                    <?php else: ?>
                        <?php $percent = (int) $usage['remaining_percent']; ?>
                        <?php $progressColor = $percent > 60 ? 'bg-success' : ($percent > 30 ? 'bg-gold' : 'bg-danger'); ?>
                        <div class="h-2.5 w-full overflow-hidden rounded-full border border-[rgba(38,37,30,0.1)] bg-surface200"><span class="block h-full rounded-full <?= $progressColor ?>" style="width: <?= esc((string) $percent) ?>%"></span></div>
                        <p class="font-mono text-[11px] leading-[1.55] tracking-[-0.01em] text-[rgba(38,37,30,0.76)]">Reset: <?= esc($usage['reset_at'] ?? '-') ?></p>
                        <button type="button" class="<?= $buttonSecondary ?>" onclick="document.getElementById('usage-modal-<?= esc((string) $usage['id']) ?>').showModal()">Perbarui Usage</button>

                        <dialog id="usage-modal-<?= esc((string) $usage['id']) ?>" class="w-[min(540px,92vw)] rounded-lg border border-[rgba(38,37,30,0.1)] bg-surface400 p-0 shadow-[rgba(0,0,0,0.14)_0_28px_70px,rgba(0,0,0,0.1)_0_14px_32px]">
                            <form method="post" action="/usages/<?= esc((string) $usage['id']) ?>/update" class="space-y-3 p-4">
                                <div class="space-y-1">
                                    <h3>Perbarui <?= esc($label) ?></h3>
                                    <p class="font-ui text-[13px] leading-[1.44] tracking-[0.01em] text-[rgba(38,37,30,0.55)]">Sesuaikan sisa persentase dan waktu reset untuk subscription ini.</p>
                                </div>

                                <label class="<?= $labelClass ?>">
                                    Sisa Persentase
                                    <input class="<?= $inputClass ?>" type="number" min="0" max="100" name="remaining_percent" required value="<?= esc((string) $usage['remaining_percent']) ?>" data-usage-percent-input>
                                </label>

                                <label class="<?= $labelClass ?>">
                                    Waktu Reset
                                    <input class="<?= $inputClass ?>" type="datetime-local" name="reset_at" min="<?= esc($todayMin) ?>" value="<?= esc(($usage['reset_at'] ?? null) ? date('Y-m-d\\TH:i', strtotime((string) $usage['reset_at'])) : '') ?>" data-usage-reset-input>
                                </label>

                                <p class="font-ui text-[12px] leading-[1.4] text-[rgba(38,37,30,0.55)]" data-usage-reset-note>
                                    Waktu reset hanya berlaku jika sisa usage di bawah 100%.
                                </p>

                                <div class="flex flex-wrap gap-2 pt-1">
                                    <button class="<?= $buttonPrimary ?>" type="submit">Simpan</button>
                                    <button class="<?= $buttonSecondary ?>" type="button" onclick="document.getElementById('usage-modal-<?= esc((string) $usage['id']) ?>').close()">Batal</button>
                                </div>
                            </form>
                        </dialog>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
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

<section class="mt-6 <?= $cardBase ?> bg-surface400 space-y-2">
    <h2>Riwayat Perubahan Usage</h2>
    <div data-history-section="usage" data-account-id="<?= esc((string) $account['id']) ?>">
        <?= view('accounts/partials/history_usage', [
            'history' => $usageHistoryPage['rows'] ?? [],
            'pagination' => $usageHistoryPage['pagination'] ?? [],
        ]) ?>
    </div>
</section>

<script>
(() => {
    const weeklyRoot = document.getElementById('account-usage-chart-weekly');
    const fiveHourRoot = document.getElementById('account-usage-chart-5h');
    const accountDateInput = document.querySelector('[data-account-chart-date]');
    const accountCaption = document.querySelector('[data-account-chart-caption]');
    const accountChartEndpoint = weeklyRoot?.getAttribute('data-chart-endpoint') || '';

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

    const toPercent = (value) => {
        const numeric = Number(value);
        if (!Number.isFinite(numeric)) {
            return null;
        }

        return Math.max(0, Math.min(100, numeric));
    };

    const toMinute = (value) => {
        const numeric = Number(value);
        if (!Number.isFinite(numeric)) {
            return 0;
        }

        return Math.max(0, Math.min(1439, Math.round(numeric)));
    };

    const formatMinute = (minute) => {
        const safeMinute = toMinute(minute);
        const hour = String(Math.floor(safeMinute / 60)).padStart(2, '0');
        const minutes = String(safeMinute % 60).padStart(2, '0');
        return `${hour}:${minutes}`;
    };

    const renderTimeSeriesChart = (root, datasets, emptyMessage) => {
        if (!root) {
            return;
        }

        const normalized = (Array.isArray(datasets) ? datasets : [])
            .map((item) => {
                const points = (Array.isArray(item?.points) ? item.points : [])
                    .map((point) => {
                        const percent = toPercent(point?.percent);
                        if (percent === null) {
                            return null;
                        }

                        const minute = toMinute(point?.minute);
                        return {
                            minute,
                            percent,
                            time: typeof point?.time === 'string' && point.time !== '' ? point.time : formatMinute(minute),
                            at: String(point?.at ?? ''),
                        };
                    })
                    .filter((point) => point !== null);

                if (points.length === 0) {
                    return null;
                }

                return {
                    label: String(item?.label ?? 'Subscription'),
                    color: String(item?.color ?? '#2f6db5'),
                    accountType: String(item?.accountType ?? 'free'),
                    points,
                };
            })
            .filter((item) => item !== null);

        if (normalized.length === 0) {
            root.innerHTML = `<p class="font-ui text-[13px] text-[rgba(38,37,30,0.55)]">${escapeHtml(emptyMessage)}</p>`;
            return;
        }

        const width = 880;
        const height = 340;
        const margin = { top: 20, right: 18, bottom: 58, left: 42 };
        const plotWidth = width - margin.left - margin.right;
        const plotHeight = height - margin.top - margin.bottom;
        const yFromPercent = (percent) => margin.top + ((100 - percent) / 100) * plotHeight;
        const xFromMinute = (minute) => margin.left + (toMinute(minute) / 1439) * plotWidth;
        const ticks = [0, 20, 40, 60, 80, 100];
        const timeTicks = [0, 360, 720, 1080, 1439];

        const gridLines = ticks.map((tick) => {
            const y = yFromPercent(tick);
            return `<line x1="${margin.left}" y1="${y}" x2="${width - margin.right}" y2="${y}" stroke="rgba(38,37,30,0.14)" stroke-width="1" />
                <text x="${margin.left - 8}" y="${y + 4}" text-anchor="end" font-size="11" fill="rgba(38,37,30,0.62)">${tick}%</text>`;
        }).join('');

        const xAxisTicks = timeTicks.map((minute) => {
            const x = xFromMinute(minute);
            return `<line x1="${x}" y1="${margin.top}" x2="${x}" y2="${height - margin.bottom}" stroke="rgba(38,37,30,0.1)" stroke-width="1" />
                <text x="${x}" y="${height - margin.bottom + 22}" text-anchor="middle" font-size="11" fill="rgba(38,37,30,0.62)">${formatMinute(minute)}</text>`;
        }).join('');

        const seriesSvg = normalized.map((item) => {
            const pathD = item.points
                .map((point, index) => `${index === 0 ? 'M' : 'L'} ${xFromMinute(point.minute)} ${yFromPercent(point.percent)}`)
                .join(' ');

            const line = `<path d="${pathD}" fill="none" stroke="${item.color}" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" opacity="0.9">
                <title>${escapeHtml(item.label)}</title>
            </path>`;

            const circles = item.points.map((point) => `<circle cx="${xFromMinute(point.minute)}" cy="${yFromPercent(point.percent)}" r="3.6" fill="${item.color}" stroke="white" stroke-width="1.3">
                <title>${escapeHtml(item.label)} · ${escapeHtml(point.time)} · ${point.percent}%</title>
            </circle>`).join('');

            return `${line}${circles}`;
        }).join('');

        const legendItems = normalized.map((item) => {
            const lastPoint = item.points[item.points.length - 1];
            const valueText = lastPoint ? `${lastPoint.time} · ${lastPoint.percent}%` : '-';

            return `<li class="flex items-start gap-2 rounded-md border border-[rgba(38,37,30,0.1)] bg-surface400 px-2 py-1.5">
                <span class="mt-[3px] h-2.5 w-2.5 shrink-0 rounded-full" style="background:${escapeHtml(item.color)}"></span>
                <span class="min-w-0">
                    <span class="block truncate font-ui text-[12px] leading-[1.4] text-[rgba(38,37,30,0.82)]">${escapeHtml(item.label)}</span>
                    <span class="font-mono text-[11px] leading-[1.45] text-[rgba(38,37,30,0.62)]">${escapeHtml(valueText)}</span>
                </span>
            </li>`;
        }).join('');

        root.innerHTML = `
            <div class="space-y-3">
                <div class="overflow-x-auto">
                    <svg viewBox="0 0 ${width} ${height}" class="min-w-[680px] w-full h-auto rounded-md border border-[rgba(38,37,30,0.1)] bg-[color-mix(in_srgb,#f2f1ed_85%,white_15%)] p-2">
                        ${gridLines}
                        ${xAxisTicks}
                        ${seriesSvg}
                        <text x="${margin.left + (plotWidth / 2)}" y="${height - margin.bottom + 40}" text-anchor="middle" font-size="12" fill="rgba(38,37,30,0.72)">Waktu</text>
                    </svg>
                </div>
                <ul class="grid gap-2 [grid-template-columns:repeat(auto-fit,minmax(220px,1fr))]">${legendItems}</ul>
            </div>
        `;
    };

    const applyAccountCaption = (dateText, weeklyCount, fiveHourCount) => {
        if (!accountCaption) {
            return;
        }

        accountCaption.textContent = `Tanggal data: ${dateText} · weekly ${weeklyCount} seri · 5h ${fiveHourCount} seri`;
    };

    const setAccountChartLoading = (isLoading) => {
        if (!weeklyRoot || !fiveHourRoot) {
            return;
        }

        weeklyRoot.classList.toggle('opacity-70', isLoading);
        weeklyRoot.classList.toggle('pointer-events-none', isLoading);
        fiveHourRoot.classList.toggle('opacity-70', isLoading);
        fiveHourRoot.classList.toggle('pointer-events-none', isLoading);
    };

    const loadAccountChartByDate = async (dateValue) => {
        if (!weeklyRoot || !fiveHourRoot || !accountChartEndpoint || !dateValue) {
            return;
        }

        setAccountChartLoading(true);
        try {
            const response = await fetch(`${accountChartEndpoint}?date=${encodeURIComponent(dateValue)}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const payload = await response.json();
            if (!payload?.success || typeof payload.data !== 'object' || payload.data === null) {
                throw new Error('Invalid usage chart response.');
            }

            const weeklySeries = Array.isArray(payload.data.weekly) ? payload.data.weekly : [];
            const fiveHourSeries = Array.isArray(payload.data.five_hour) ? payload.data.five_hour : [];

            renderTimeSeriesChart(weeklyRoot, weeklySeries, 'Belum ada data weekly pada tanggal ini.');
            renderTimeSeriesChart(fiveHourRoot, fiveHourSeries, 'Belum ada data usage 5h pada tanggal ini.');
            applyAccountCaption(payload.date || dateValue, weeklySeries.length, fiveHourSeries.length);
        } catch (error) {
            weeklyRoot.innerHTML = '<p class="font-ui text-[13px] text-[rgba(38,37,30,0.55)]">Gagal memuat data weekly pada tanggal tersebut.</p>';
            fiveHourRoot.innerHTML = '<p class="font-ui text-[13px] text-[rgba(38,37,30,0.55)]">Gagal memuat data 5h pada tanggal tersebut.</p>';
            applyAccountCaption(dateValue, 0, 0);
        } finally {
            setAccountChartLoading(false);
        }
    };

    if (weeklyRoot && fiveHourRoot) {
        const initialDate = accountDateInput?.value || weeklyRoot.getAttribute('data-initial-date') || '';
        if (accountDateInput && initialDate !== '') {
            accountDateInput.value = initialDate;
        }

        if (accountChartEndpoint && initialDate !== '') {
            loadAccountChartByDate(initialDate);
        } else {
            renderTimeSeriesChart(weeklyRoot, [], 'Belum ada data weekly pada tanggal ini.');
            renderTimeSeriesChart(fiveHourRoot, [], 'Belum ada data usage 5h pada tanggal ini.');
            applyAccountCaption(initialDate !== '' ? initialDate : '-', 0, 0);
        }
    }

    accountDateInput?.addEventListener('change', () => {
        const selectedDate = accountDateInput.value;
        if (selectedDate !== '') {
            loadAccountChartByDate(selectedDate);
        }
    });

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
        const isPro = currentValue === 'pro';
        const blocks = Array.from(document.querySelectorAll(`[data-pro-only="${formId}"]`));
        const requiredFields = Array.from(document.querySelectorAll(`[data-pro-required="${formId}"]`));
        const personalInviteBlocks = Array.from(document.querySelectorAll(`[data-personal-invite-only="${formId}"]`));
        const personalInviteRequiredFields = Array.from(document.querySelectorAll(`[data-personal-invite-required="${formId}"]`));
        const proTypeSelect = document.querySelector(`[data-pro-type-select="${formId}"]`);
        const isPersonalInvite = isPro && proTypeSelect?.value === 'personal_invite';

        blocks.forEach((element) => {
            element.classList.toggle('hidden', !isPro);
        });

        requiredFields.forEach((field) => {
            field.required = isPro;
            field.disabled = !isPro;

            if (!isPro) {
                if (field.name === 'is_workspace_deactivated') {
                    field.value = '0';
                }
                if (field.name === 'is_one_month_duration') {
                    field.value = '1';
                }
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
        const proTypeSelect = form.querySelector('[data-workspace-create-pro-select]');
        const personalWrapper = form.querySelector('[data-workspace-create-personal-wrapper]');
        const personalInput = form.querySelector('[data-workspace-create-personal-input]');

        if (!proTypeSelect || !personalWrapper || !personalInput) {
            return;
        }

        const syncWorkspaceCreateForm = () => {
            const isPersonalInvite = proTypeSelect.value === 'personal_invite';

            personalWrapper.classList.toggle('hidden', !isPersonalInvite);
            personalInput.required = isPersonalInvite;
            personalInput.disabled = !isPersonalInvite;

            if (!isPersonalInvite) {
                personalInput.value = '';
            }
        };

        proTypeSelect.addEventListener('change', syncWorkspaceCreateForm);
        syncWorkspaceCreateForm();
    });

    const usageForms = Array.from(document.querySelectorAll('dialog form[action^="/usages/"]'));
    usageForms.forEach((form) => {
        const percentInput = form.querySelector('[data-usage-percent-input]');
        const resetInput = form.querySelector('[data-usage-reset-input]');
        const note = form.querySelector('[data-usage-reset-note]');

        if (!percentInput || !resetInput) {
            return;
        }

        const syncResetInput = () => {
            const value = Number(percentInput.value || 0);
            const needsReset = value < 100;

            resetInput.required = needsReset;
            resetInput.disabled = !needsReset;
            if (!needsReset) {
                resetInput.value = '';
            }

            if (note) {
                note.textContent = needsReset
                    ? 'Waktu reset wajib diisi karena sisa usage di bawah 100%.'
                    : 'Sisa usage 100%: waktu reset tidak diperlukan.';
            }
        };

        percentInput.addEventListener('input', syncResetInput);
        percentInput.addEventListener('change', syncResetInput);
        syncResetInput();
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
