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
$tableWrap = 'overflow-auto rounded-lg border border-[rgba(38,37,30,0.1)] bg-surface400 shadow-[rgba(0,0,0,0.02)_0_0_16px,rgba(0,0,0,0.008)_0_0_8px]';

$inputClass = 'mt-1 w-full rounded-md border border-[rgba(38,37,30,0.1)] bg-surface400 px-3 py-2 font-ui text-[13px] leading-[1.45] text-[rgba(38,37,30,0.82)] outline-none transition-[border-color,box-shadow] duration-150 focus:border-[rgba(38,37,30,0.2)] focus:shadow-[rgba(0,0,0,0.1)_0_4px_12px]';
$labelClass = 'font-ui text-[12px] uppercase tracking-[0.05em] font-medium text-[rgba(38,37,30,0.62)]';

$buttonPrimary = 'inline-flex items-center justify-center gap-1.5 rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 px-3 py-2 font-display text-[13px] font-medium tracking-[0.025em] text-ink transition-colors duration-150 hover:text-danger hover:border-[rgba(38,37,30,0.2)]';
$buttonSecondary = 'inline-flex items-center justify-center gap-1.5 rounded-md border border-[rgba(38,37,30,0.1)] bg-surface400 px-3 py-2 font-display text-[13px] font-medium tracking-[0.025em] text-[rgba(38,37,30,0.75)] transition-colors duration-150 hover:text-danger hover:border-[rgba(38,37,30,0.2)]';
$buttonDanger = 'inline-flex items-center justify-center gap-1.5 rounded-md border border-[color-mix(in_srgb,#cf2d56_40%,transparent_60%)] bg-[color-mix(in_srgb,#cf2d56_14%,#f2f1ed_86%)] px-3 py-2 font-display text-[13px] font-medium tracking-[0.025em] text-[#8f1f3c] transition-[border-color,box-shadow] duration-150 hover:border-[color-mix(in_srgb,#cf2d56_55%,transparent_45%)] hover:shadow-[rgba(0,0,0,0.1)_0_4px_12px]';
$todayMin = date('Y-m-d\\T00:00');
?>

<section class="space-y-2">
    <h1>Detail Akun</h1>
    <p class="max-w-[760px] font-serif text-[clamp(18px,1.35vw,20px)] leading-[1.45] text-[rgba(38,37,30,0.64)]">Ringkasan identitas akun, konfigurasi free/pro untuk setiap subscription, status workspace, serta histori perubahan usage.</p>
</section>

<section class="mt-6 <?= $cardBase ?> bg-surface400 space-y-3">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="space-y-1">
            <h3><?= esc($account['account_name']) ?></h3>
            <p class="font-ui text-[13px] leading-[1.44] tracking-[0.01em] text-[rgba(38,37,30,0.55)]">Email: <?= esc($account['email']) ?></p>
        </div>
        <form method="post" action="/accounts/<?= esc((string) $account['id']) ?>/delete" onsubmit="return confirm('Hapus akun ini beserta seluruh datanya?')">
            <button class="<?= $buttonDanger ?>" type="submit">Hapus Akun</button>
        </form>
    </div>

    <div class="grid gap-3 [grid-template-columns:repeat(auto-fit,minmax(220px,1fr))]">
        <article class="rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 p-3">
            <div class="font-ui text-[12px] uppercase tracking-[0.06em] font-medium text-[rgba(38,37,30,0.62)]">Password Hint</div>
            <div class="mt-1 font-display text-[15px] leading-[1.5] text-[rgba(38,37,30,0.82)]"><?= esc($account['password_hint'] ?? '-') ?></div>
        </article>
        <article class="rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 p-3">
            <div class="font-ui text-[12px] uppercase tracking-[0.06em] font-medium text-[rgba(38,37,30,0.62)]">Catatan</div>
            <div class="mt-1 font-display text-[15px] leading-[1.5] text-[rgba(38,37,30,0.82)]"><?= esc($account['notes'] ?? '-') ?></div>
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
    ];
    $accountType = \App\Services\SubscriptionStatusService::normalizeAccountType((string) ($subscription['account_type'] ?? 'free'));
    $isPro = $accountType === 'pro';
    $proType = (string) ($subscription['pro_account_type'] ?? '');
    $proTypeLabel = $proType === 'personal_invite'
        ? 'Invite Akun Pribadi'
        : ($proType === 'seller_account' ? 'Akun dari Seller' : '-');
    $usageLabels = $isPro
        ? ['5h' => 'Usage 5 Jam', 'weekly' => 'Usage Mingguan']
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
                        <option value="free" <?= $accountType === 'free' ? 'selected' : '' ?>>Free</option>
                        <option value="pro" <?= $accountType === 'pro' ? 'selected' : '' ?>>Pro (Workspace)</option>
                    </select>
                </label>

                <label class="<?= $labelClass ?> <?= $isPro ? '' : 'hidden' ?>" data-pro-only="<?= esc((string) $formId) ?>">
                    Jenis Akun Pro
                    <select class="<?= $inputClass ?>" name="pro_account_type" data-pro-required="<?= esc((string) $formId) ?>">
                        <option value="">Pilih jenis akun pro</option>
                        <option value="personal_invite" <?= $proType === 'personal_invite' ? 'selected' : '' ?>>Invite Akun Pribadi</option>
                        <option value="seller_account" <?= $proType === 'seller_account' ? 'selected' : '' ?>>Akun dari Seller</option>
                    </select>
                </label>
                <label class="<?= $labelClass ?> <?= $isPro ? '' : 'hidden' ?>" data-pro-only="<?= esc((string) $formId) ?>">
                    Nama Workspace
                    <input class="<?= $inputClass ?>" type="text" name="workspace_name" value="<?= esc((string) ($subscription['workspace_name'] ?? '')) ?>" data-pro-required="<?= esc((string) $formId) ?>">
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
            <button class="<?= $buttonPrimary ?>" type="submit">Perbarui Subscription</button>
        </form>

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
                                    <input class="<?= $inputClass ?>" type="number" min="0" max="100" name="remaining_percent" required value="<?= esc((string) $usage['remaining_percent']) ?>">
                                </label>

                                <label class="<?= $labelClass ?>">
                                    Waktu Reset
                                    <input class="<?= $inputClass ?>" type="datetime-local" name="reset_at" min="<?= esc($todayMin) ?>" required value="<?= esc(date('Y-m-d\\TH:i', strtotime((string) $usage['reset_at']))) ?>">
                                </label>

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
    <h2>Riwayat Perubahan Usage</h2>
    <div class="<?= $tableWrap ?>">
        <table>
            <thead>
            <tr>
                <th>ID Subscription</th>
                <th>Tipe Usage</th>
                <th>Persen Lama</th>
                <th>Persen Baru</th>
                <th>Diperbarui Pada</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($history === []): ?>
                <tr>
                    <td colspan="5" class="font-ui text-[13px] text-[rgba(38,37,30,0.55)]">Belum ada riwayat perubahan usage.</td>
                </tr>
            <?php endif; ?>

            <?php foreach ($history as $row): ?>
                <tr>
                    <td><?= esc((string) $row['subscription_id']) ?></td>
                    <td><?= esc($row['usage_type']) ?></td>
                    <td><?= esc((string) ($row['old_percent'] ?? '-')) ?></td>
                    <td><?= esc((string) $row['new_percent']) ?></td>
                    <td class="font-mono text-[11px] leading-[1.55] tracking-[-0.01em] text-[rgba(38,37,30,0.76)]"><?= esc($row['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<script>
(() => {
    const selectors = Array.from(document.querySelectorAll('[data-subscription-type-select]'));

    const syncSubscriptionForm = (formId, currentValue) => {
        const isPro = currentValue === 'pro';
        const blocks = Array.from(document.querySelectorAll(`[data-pro-only="${formId}"]`));
        const requiredFields = Array.from(document.querySelectorAll(`[data-pro-required="${formId}"]`));

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
        onChange();
    });
})();
</script>
<?= $this->endSection() ?>
