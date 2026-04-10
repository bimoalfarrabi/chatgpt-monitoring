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
$tableWrap = 'overflow-visible';
$inputClass = 'mt-1 w-full rounded-md border border-[rgba(38,37,30,0.22)] bg-surface200 px-3 py-2 font-ui text-[13px] leading-[1.45] text-[rgba(38,37,30,0.9)] shadow-[inset_0_1px_0_rgba(255,255,255,0.45)] outline-none transition-[border-color,box-shadow,background-color] duration-150 placeholder:text-[rgba(38,37,30,0.45)] focus:border-[rgba(38,37,30,0.38)] focus:bg-[#f8f7f3] focus:shadow-[rgba(0,0,0,0.1)_0_4px_12px]';
$labelClass = 'font-ui text-[12px] uppercase tracking-[0.05em] font-medium text-[rgba(38,37,30,0.62)]';
$buttonPrimary = 'inline-flex items-center justify-center gap-1.5 rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 px-3 py-2 font-display text-[13px] font-medium tracking-[0.025em] text-ink transition-colors duration-150 hover:text-danger hover:border-[rgba(38,37,30,0.2)]';
$buttonSecondary = 'inline-flex items-center justify-center gap-1.5 rounded-md border border-[rgba(38,37,30,0.1)] bg-surface400 px-3 py-2 font-display text-[13px] font-medium tracking-[0.025em] text-[rgba(38,37,30,0.75)] transition-colors duration-150 hover:text-danger hover:border-[rgba(38,37,30,0.2)]';

$oldAccountType = \App\Services\SubscriptionStatusService::normalizeAccountType((string) old('account_type', 'free'));
$oldProAccountType = \App\Services\SubscriptionStatusService::normalizeProAccountType((string) old('pro_account_type', ''));
$createFormExpanded = old('account_name') !== null
    || old('email') !== null
    || old('store_source') !== null
    || old('subscription_type') !== null;
?>

<section class="space-y-2">
    <h1>Daftar Akun</h1>
    <p class="max-w-[760px] font-serif text-[clamp(18px,1.35vw,20px)] leading-[1.45] text-[rgba(38,37,30,0.64)]">Kelola data akun beserta detail paket free/pro, informasi workspace, dan monitoring usage sesuai jenis akun.</p>
</section>

<section class="mt-6 <?= $cardBase ?> bg-surface400 space-y-2">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h3>Buat Akun + Subscription</h3>
        <button
            class="<?= $buttonSecondary ?>"
            type="button"
            data-create-account-toggle
            aria-controls="create-account-panel"
            aria-expanded="<?= $createFormExpanded ? 'true' : 'false' ?>"
        >
            <?= $createFormExpanded ? 'Minimize Form' : 'Expand Form' ?>
        </button>
    </div>

    <div id="create-account-panel" data-create-account-panel class="space-y-2 <?= $createFormExpanded ? '' : 'hidden' ?>">
        <p class="font-ui text-[13px] leading-[1.44] tracking-[0.01em] text-[rgba(38,37,30,0.55)]">Akun free hanya punya usage weekly. Akun pro (workspace) punya usage 5 jam + weekly.</p>

        <form method="post" action="/accounts/create" class="space-y-3 rounded-md border border-[rgba(38,37,30,0.12)] bg-surface300 p-3">
            <div class="grid gap-3 [grid-template-columns:repeat(auto-fit,minmax(220px,1fr))]">
                <label class="<?= $labelClass ?>">
                    Nama Akun
                    <input class="<?= $inputClass ?>" type="text" name="account_name" required value="<?= esc(old('account_name', '')) ?>">
                </label>
                <label class="<?= $labelClass ?>">
                    Email
                    <input class="<?= $inputClass ?>" type="email" name="email" required value="<?= esc(old('email', '')) ?>">
                </label>
                <label class="<?= $labelClass ?>">
                    Password
                    <div class="mt-1 flex flex-wrap items-center gap-2" data-password-field-group>
                        <input class="<?= $inputClass ?> mt-0 min-w-[180px] flex-1" type="password" name="password_hint" value="<?= esc(old('password_hint', '')) ?>" autocomplete="off" data-password-field>
                        <button class="<?= $buttonSecondary ?>" type="button" data-password-toggle data-show-label="Unhide" data-hide-label="Hide">Unhide</button>
                        <button class="<?= $buttonSecondary ?>" type="button" data-password-copy>Copy</button>
                    </div>
                </label>
                <label class="<?= $labelClass ?>">
                    Jenis Akun ChatGPT
                    <select class="<?= $inputClass ?>" name="account_type" required data-account-type-select>
                        <option value="free" <?= $oldAccountType === 'free' ? 'selected' : '' ?>>Free</option>
                        <option value="pro" <?= $oldAccountType === 'pro' ? 'selected' : '' ?>>Pro (Workspace)</option>
                    </select>
                </label>
                <label class="<?= $labelClass ?>">
                    Sumber Store
                    <input class="<?= $inputClass ?>" type="text" name="store_source" required value="<?= esc(old('store_source', '')) ?>">
                </label>
                <label class="<?= $labelClass ?>">
                    Tipe Subscription
                    <input class="<?= $inputClass ?>" type="text" name="subscription_type" required value="<?= esc(old('subscription_type', '')) ?>">
                </label>

                <label class="<?= $labelClass ?> <?= $oldAccountType === 'pro' ? '' : 'hidden' ?>" data-pro-only>
                    Jenis Akun Pro
                    <select class="<?= $inputClass ?>" name="pro_account_type" data-pro-required>
                        <option value="">Pilih jenis akun pro</option>
                        <option value="personal_invite" <?= old('pro_account_type') === 'personal_invite' ? 'selected' : '' ?>>Invite Akun Pribadi</option>
                        <option value="seller_account" <?= old('pro_account_type') === 'seller_account' ? 'selected' : '' ?>>Akun dari Seller</option>
                    </select>
                </label>
                <label class="<?= $labelClass ?> <?= $oldAccountType === 'pro' ? '' : 'hidden' ?>" data-pro-only>
                    Nama Workspace
                    <input class="<?= $inputClass ?>" type="text" name="workspace_name" data-pro-required value="<?= esc(old('workspace_name', '')) ?>">
                </label>
                <label class="<?= $labelClass ?> <?= $oldAccountType === 'pro' && $oldProAccountType === 'personal_invite' ? '' : 'hidden' ?>" data-personal-invite-only>
                    Workspace Personal (Free Weekly)
                    <input class="<?= $inputClass ?>" type="text" name="personal_workspace_name" data-personal-invite-required value="<?= esc(old('personal_workspace_name', '')) ?>">
                </label>
                <label class="<?= $labelClass ?> <?= $oldAccountType === 'pro' ? '' : 'hidden' ?>" data-pro-only>
                    Status Workspace
                    <select class="<?= $inputClass ?>" name="is_workspace_deactivated" data-pro-required>
                        <option value="0" <?= old('is_workspace_deactivated', '0') === '0' ? 'selected' : '' ?>>Aktif</option>
                        <option value="1" <?= old('is_workspace_deactivated') === '1' ? 'selected' : '' ?>>Deactivated</option>
                    </select>
                </label>
                <label class="<?= $labelClass ?> <?= $oldAccountType === 'pro' ? '' : 'hidden' ?>" data-pro-only>
                    Tanggal Langganan
                    <input class="<?= $inputClass ?>" type="datetime-local" name="subscribed_at" data-pro-required value="<?= esc(old('subscribed_at', '')) ?>">
                </label>
                <label class="<?= $labelClass ?> <?= $oldAccountType === 'pro' ? '' : 'hidden' ?>" data-pro-only>
                    Durasi Satu Bulan?
                    <select class="<?= $inputClass ?>" name="is_one_month_duration" data-pro-required>
                        <option value="1" <?= old('is_one_month_duration', '1') === '1' ? 'selected' : '' ?>>Ya</option>
                        <option value="0" <?= old('is_one_month_duration') === '0' ? 'selected' : '' ?>>Tidak</option>
                    </select>
                </label>
            </div>

            <label class="<?= $labelClass ?>">
                Catatan
                <textarea class="<?= $inputClass ?>" name="notes" rows="3"><?= esc(old('notes', '')) ?></textarea>
            </label>

            <button class="<?= $buttonPrimary ?>" type="submit">Simpan Akun</button>
        </form>
    </div>
</section>

<section class="mt-6 <?= $cardBase ?> bg-surface400 space-y-2">
    <h2>Subscription per Akun</h2>
    <div class="<?= $tableWrap ?>">
        <table class="data-table-cards">
            <thead>
            <tr>
                <th>Nama</th>
                <th>Email</th>
                <th>Jenis Akun</th>
                <th>Jenis Akun Pro</th>
                <th>Workspace Seller (Pro)</th>
                <th>Workspace Personal (Free)</th>
                <th>Status Workspace</th>
                <th>Sumber Store</th>
                <th>Tipe Subscription</th>
                <th>Tgl Langganan</th>
                <th>Durasi 1 Bulan</th>
                <th>Berakhir (Otomatis)</th>
                <th>Status</th>
                <th>5H</th>
                <th>Weekly</th>
                <th>Aksi</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($accounts === []): ?>
                <tr><td colspan="16" class="font-ui text-[13px] text-[rgba(38,37,30,0.55)]">Belum ada data akun.</td></tr>
            <?php endif; ?>

            <?php foreach ($accounts as $account): ?>
                <?php foreach ($account['subscriptions'] as $subscription): ?>
                    <?php
                    $statusClass = $statusClasses[$subscription['status']] ?? $statusClasses['active'];
                    $accountType = \App\Services\SubscriptionStatusService::normalizeAccountType((string) ($subscription['account_type'] ?? 'free'));
                    $isPro = $accountType === 'pro';
                    $proType = (string) ($subscription['pro_account_type'] ?? '');
                    $proTypeLabel = $proType === 'personal_invite'
                        ? 'Invite Pribadi'
                        : ($proType === 'seller_account' ? 'Akun Seller' : '-');
                    $personalWorkspaceName = trim((string) ($subscription['personal_workspace_name'] ?? ''));
                    ?>
                    <tr>
                        <td><?= esc($account['account_name']) ?></td>
                        <td><?= esc($account['email']) ?></td>
                        <td><?= esc(strtoupper($accountType)) ?></td>
                        <td><?= esc($isPro ? $proTypeLabel : '-') ?></td>
                        <td><?= esc($isPro ? ((string) ($subscription['workspace_name'] ?? '-')) : '-') ?></td>
                        <td><?= esc($isPro && $proType === 'personal_invite' ? ($personalWorkspaceName !== '' ? $personalWorkspaceName : '-') : '-') ?></td>
                        <td>
                            <?php if ($isPro): ?>
                                <?= ((int) ($subscription['is_workspace_deactivated'] ?? 0)) === 1 ? 'Deactivated' : 'Aktif' ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?= esc($subscription['store_source']) ?></td>
                        <td><?= esc($subscription['subscription_type']) ?></td>
                        <td class="font-mono text-[11px] leading-[1.55] tracking-[-0.01em] text-[rgba(38,37,30,0.76)]"><?= esc($isPro ? ((string) ($subscription['subscribed_at'] ?? '-')) : '-') ?></td>
                        <td><?= esc($isPro ? (((int) ($subscription['is_one_month_duration'] ?? 0)) === 1 ? 'Ya' : 'Tidak') : '-') ?></td>
                        <td class="font-mono text-[11px] leading-[1.55] tracking-[-0.01em] text-[rgba(38,37,30,0.76)]"><?= esc($subscription['expired_at'] ?? '-') ?></td>
                        <td><span class="<?= $statusClass ?>"><?= esc(\App\Services\SubscriptionStatusService::humanize((string) $subscription['status'])) ?></span></td>
                        <td>
                            <?php if (! $isPro): ?>
                                <span class="font-ui text-[13px] text-[rgba(38,37,30,0.55)]">N/A</span>
                            <?php else: ?>
                                <?php $p5 = (int) ($subscription['usages']['5h']['remaining_percent'] ?? 0); ?>
                                <span class="font-ui text-[13px]"><?= esc((string) $p5) ?>%</span>
                                <?php $progressColor5 = $p5 > 60 ? 'bg-success' : ($p5 > 30 ? 'bg-gold' : 'bg-danger'); ?>
                                <div class="mt-1.5 h-2.5 w-full overflow-hidden rounded-full border border-[rgba(38,37,30,0.1)] bg-surface200"><span class="block h-full rounded-full <?= $progressColor5 ?>" style="width: <?= esc((string) $p5) ?>%"></span></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php $pw = (int) ($subscription['usages']['weekly']['remaining_percent'] ?? 0); ?>
                            <span class="font-ui text-[13px]"><?= esc((string) $pw) ?>%</span>
                            <?php $progressColorW = $pw > 60 ? 'bg-success' : ($pw > 30 ? 'bg-gold' : 'bg-danger'); ?>
                            <div class="mt-1.5 h-2.5 w-full overflow-hidden rounded-full border border-[rgba(38,37,30,0.1)] bg-surface200"><span class="block h-full rounded-full <?= $progressColorW ?>" style="width: <?= esc((string) $pw) ?>%"></span></div>
                        </td>
                        <td>
                            <a class="inline-flex items-center justify-center gap-1.5 rounded-full border border-[rgba(38,37,30,0.1)] bg-surface400 px-2 py-[3px] no-underline font-display text-[13px] font-medium tracking-[0.025em] text-[rgba(38,37,30,0.6)] hover:text-danger hover:border-[rgba(38,37,30,0.2)]" href="/accounts/<?= esc((string) $account['id']) ?>">Detail</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<script>
(() => {
    const createFormToggle = document.querySelector('[data-create-account-toggle]');
    const createFormPanel = document.querySelector('[data-create-account-panel]');

    if (createFormToggle && createFormPanel) {
        const updateToggleLabel = () => {
            const expanded = !createFormPanel.classList.contains('hidden');
            createFormToggle.textContent = expanded ? 'Minimize Form' : 'Expand Form';
            createFormToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        };

        createFormToggle.addEventListener('click', () => {
            createFormPanel.classList.toggle('hidden');
            updateToggleLabel();
        });

        updateToggleLabel();
    }

    const accountTypeSelect = document.querySelector('[data-account-type-select]');
    const proTypeSelect = document.querySelector('select[name="pro_account_type"]');
    if (!accountTypeSelect) {
        return;
    }

    const proBlocks = Array.from(document.querySelectorAll('[data-pro-only]'));
    const proRequiredFields = Array.from(document.querySelectorAll('[data-pro-required]'));
    const personalInviteBlocks = Array.from(document.querySelectorAll('[data-personal-invite-only]'));
    const personalInviteRequiredFields = Array.from(document.querySelectorAll('[data-personal-invite-required]'));

    const syncVisibility = () => {
        const isPro = accountTypeSelect.value === 'pro';
        const isPersonalInvite = isPro && (proTypeSelect?.value === 'personal_invite');

        proBlocks.forEach((element) => {
            element.classList.toggle('hidden', !isPro);
        });

        proRequiredFields.forEach((field) => {
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

    accountTypeSelect.addEventListener('change', syncVisibility);
    proTypeSelect?.addEventListener('change', syncVisibility);
    syncVisibility();

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
})();
</script>
<?= $this->endSection() ?>
