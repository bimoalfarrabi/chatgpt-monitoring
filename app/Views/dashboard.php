<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$totalSubscription = count($subscriptions);
$freeSubscriptions = is_array($freeSubscriptions ?? null) ? $freeSubscriptions : [];
$totalFreeAccount = count($freeSubscriptions);
$butuhTindakan = 0;
$usageKritis5h = 0;
$usageKritisWeekly = 0;
$usageKritisWeeklyFree = 0;

$urutExpired = $subscriptions;
usort($urutExpired, static function (array $a, array $b): int {
    $aExpired = (string) ($a['expired_at'] ?? '');
    $bExpired = (string) ($b['expired_at'] ?? '');
    $aTime = $aExpired === '' ? PHP_INT_MAX : (strtotime($aExpired) ?: PHP_INT_MAX);
    $bTime = $bExpired === '' ? PHP_INT_MAX : (strtotime($bExpired) ?: PHP_INT_MAX);
    return $aTime <=> $bTime;
});
$terdekatExpired = array_slice($urutExpired, 0, 5);
$urutFreeWeekly = $freeSubscriptions;
usort($urutFreeWeekly, static function (array $a, array $b): int {
    $aPercent = (int) ($a['usages']['weekly']['remaining_percent'] ?? 0);
    $bPercent = (int) ($b['usages']['weekly']['remaining_percent'] ?? 0);
    return $aPercent <=> $bPercent;
});

foreach ($subscriptions as $subscription) {
    if (in_array($subscription['status'], ['expiring_soon', 'expired', 'deactivated'], true)) {
        $butuhTindakan++;
    }

    $accountType = \App\Services\SubscriptionStatusService::normalizeAccountType((string) ($subscription['account_type'] ?? 'free'));
    $proType = \App\Services\SubscriptionStatusService::normalizeProAccountType((string) ($subscription['pro_account_type'] ?? ''));
    $isPersonalInvite = $accountType === 'pro' && $proType === 'personal_invite';

    $p5 = (int) (($subscription['usages']['5h']['remaining_percent'] ?? 100));
    $pwSeller = (int) (($subscription['usages']['weekly']['remaining_percent'] ?? 100));
    $pwPersonal = (int) (($subscription['usages']['weekly_personal']['remaining_percent'] ?? 100));

    if ($p5 <= 20) {
        $usageKritis5h++;
    }

    $isWeeklyCritical = $pwSeller <= 20 || ($isPersonalInvite && $pwPersonal <= 20);
    if ($isWeeklyCritical) {
        $usageKritisWeekly++;
    }
}

foreach ($freeSubscriptions as $subscription) {
    $pwWeekly = (int) (($subscription['usages']['weekly']['remaining_percent'] ?? 100));
    if ($pwWeekly <= 20) {
        $usageKritisWeeklyFree++;
    }
}

$statusClasses = [
    'active' => 'inline-flex items-center gap-1.5 rounded-full px-2 py-[3px] font-display text-[14px] leading-[1.5] border border-[color-mix(in_srgb,#1f8a65_35%,transparent_65%)] text-[#165a44] bg-[color-mix(in_srgb,#1f8a65_18%,#f2f1ed_82%)]',
    'expiring_soon' => 'inline-flex items-center gap-1.5 rounded-full px-2 py-[3px] font-display text-[14px] leading-[1.5] border border-[color-mix(in_srgb,#c08532_40%,transparent_60%)] text-[#8f4d10] bg-[color-mix(in_srgb,#c08532_22%,#f2f1ed_78%)]',
    'expired' => 'inline-flex items-center gap-1.5 rounded-full px-2 py-[3px] font-display text-[14px] leading-[1.5] border border-[color-mix(in_srgb,#cf2d56_40%,transparent_60%)] text-[#8f1f3c] bg-[color-mix(in_srgb,#cf2d56_18%,#f2f1ed_82%)]',
    'deactivated' => 'inline-flex items-center gap-1.5 rounded-full px-2 py-[3px] font-display text-[14px] leading-[1.5] border border-[color-mix(in_srgb,#444444_34%,transparent_66%)] text-[#2d2d2d] bg-[color-mix(in_srgb,#444444_14%,#f2f1ed_86%)]',
];

$cardBase = 'rounded-lg border border-[rgba(38,37,30,0.1)] p-4 shadow-[rgba(0,0,0,0.02)_0_0_16px,rgba(0,0,0,0.008)_0_0_8px] transition-[box-shadow,border-color] duration-200 hover:border-[rgba(38,37,30,0.2)] hover:shadow-[rgba(0,0,0,0.14)_0_28px_70px,rgba(0,0,0,0.1)_0_14px_32px]';
$tableWrap = 'overflow-visible';
$sectionTitle = 'mb-2 space-y-2';
$chartDateDefault = date('Y-m-d');
?>

<section class="<?= $sectionTitle ?>">
    <h1>Dasbor Monitoring Subscription</h1>
    <p class="max-w-[760px] font-serif text-[clamp(18px,1.35vw,20px)] leading-[1.45] text-[rgba(38,37,30,0.64)]">Pantau kesehatan akun, status akses workspace, dan sisa kuota pemakaian dalam satu tampilan. Tanggal berakhir dihitung otomatis dari tanggal langganan + durasi satu bulan (jika dipilih).</p>
    <p class="font-mono text-[11px] leading-[1.55] tracking-[-0.01em] text-[rgba(38,37,30,0.76)]">Endpoint cepat: /api/accounts · /api/subscriptions · /api/account-usages/{id}/update · /api/telegram/test</p>
</section>

<section class="mt-6 <?= $cardBase ?> bg-surface400 space-y-2">
    <h2>Grafik Penggunaan Seluruh Akun</h2>
    <p class="font-ui text-[13px] leading-[1.44] tracking-[0.01em] text-[rgba(38,37,30,0.55)]">
        Sumbu vertikal menunjukkan persentase usage, sumbu horizontal menunjukkan waktu update pada tanggal terpilih.
    </p>
    <div class="flex flex-wrap items-end gap-2">
        <label class="font-ui text-[12px] uppercase tracking-[0.05em] font-medium text-[rgba(38,37,30,0.62)]">
            Tanggal Data
            <input
                type="date"
                value="<?= esc($chartDateDefault) ?>"
                data-dashboard-chart-date
                class="mt-1 w-[220px] rounded-md border border-[rgba(38,37,30,0.22)] bg-surface200 px-3 py-2 font-ui text-[13px] leading-[1.45] text-[rgba(38,37,30,0.9)] shadow-[inset_0_1px_0_rgba(255,255,255,0.45)] outline-none transition-[border-color,box-shadow,background-color,color] duration-150 focus:border-[rgba(38,37,30,0.38)] focus:bg-[#f8f7f3] focus:shadow-[rgba(0,0,0,0.1)_0_4px_12px]"
            >
        </label>
        <p class="font-ui text-[12px] leading-[1.4] text-[rgba(38,37,30,0.55)]" data-dashboard-chart-caption>
            Menampilkan data berdasarkan tanggal terpilih.
        </p>
    </div>
    <div class="grid gap-3 [grid-template-columns:repeat(auto-fit,minmax(320px,1fr))]">
        <article class="rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 p-3 space-y-2">
            <h3>Usage Weekly Seluruh Akun</h3>
            <div
                id="dashboard-usage-chart-weekly"
                data-chart-endpoint="/usage-chart/dashboard"
                data-initial-date="<?= esc($chartDateDefault, 'attr') ?>"
                class="rounded-md border border-[rgba(38,37,30,0.1)] bg-surface400 p-2"
            ></div>
        </article>
        <article class="rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 p-3 space-y-2">
            <h3>Usage 5h Akun Pro</h3>
            <div
                id="dashboard-usage-chart-5h"
                class="rounded-md border border-[rgba(38,37,30,0.1)] bg-surface400 p-2"
            ></div>
        </article>
    </div>
</section>

<section class="mt-6 <?= $cardBase ?> bg-surface400 space-y-2">
    <h2>Subscription Terdekat Expired</h2>
    <p class="font-ui text-[13px] leading-[1.44] tracking-[0.01em] text-[rgba(38,37,30,0.55)]">5 data teratas berdasarkan tanggal berakhir otomatis paling dekat.</p>
    <div class="<?= $tableWrap ?>">
        <table class="data-table-cards">
            <thead>
            <tr>
                <th>Nama Akun</th>
                <th>Email</th>
                <th>Tipe Subscription</th>
                <th>Berakhir (Otomatis)</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($terdekatExpired === []): ?>
                <tr><td colspan="6" class="font-ui text-[13px] text-[rgba(38,37,30,0.55)]">Belum ada data subscription.</td></tr>
            <?php endif; ?>

            <?php foreach ($terdekatExpired as $subscription): ?>
                <?php $account = $accountMap[$subscription['account_id']] ?? null; ?>
                <?php $statusClass = $statusClasses[$subscription['status']] ?? $statusClasses['active']; ?>
                <tr>
                    <td><?= esc($account['account_name'] ?? '-') ?></td>
                    <td><?= esc($account['email'] ?? '-') ?></td>
                    <td><?= esc($subscription['subscription_type']) ?></td>
                    <td class="font-mono text-[11px] leading-[1.55] tracking-[-0.01em] text-[rgba(38,37,30,0.76)]"><?= esc($subscription['expired_at'] ?? '-') ?></td>
                    <td><span class="<?= $statusClass ?>"><?= esc(\App\Services\SubscriptionStatusService::humanize($subscription['status'])) ?></span></td>
                    <td>
                        <?php if ($account): ?>
                            <a class="inline-flex items-center justify-center gap-1.5 rounded-full border border-[rgba(38,37,30,0.1)] bg-surface400 px-2 py-[3px] no-underline font-display text-[13px] font-medium tracking-[0.025em] text-[rgba(38,37,30,0.6)] hover:text-danger hover:border-[rgba(38,37,30,0.2)]" href="/accounts/<?= esc((string) $account['id']) ?>">Detail</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="mt-6 <?= $cardBase ?> bg-surface400 space-y-2">
    <h2>Monitoring Detail Seluruh Subscription</h2>
    <p class="font-ui text-[13px] leading-[1.44] tracking-[0.01em] text-[rgba(38,37,30,0.55)]">Tampilan lengkap untuk evaluasi status invite, sisa kuota 5 jam, dan kuota mingguan.</p>
    <div class="<?= $tableWrap ?>">
        <table class="data-table-cards">
            <thead>
            <tr>
                <th>Nama</th>
                <th>Email</th>
                <th>Sumber Store</th>
                <th>Tipe Subscription</th>
                <th>Berakhir (Otomatis)</th>
                <th>Status</th>
                <th>Usage 5 Jam</th>
                <th>Usage Mingguan</th>
                <th>Aksi</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($subscriptions === []): ?>
                <tr>
                    <td colspan="9" class="font-ui text-[13px] text-[rgba(38,37,30,0.55)]">Belum ada data monitoring.</td>
                </tr>
            <?php endif; ?>

            <?php foreach ($subscriptions as $subscription): ?>
                <?php $account = $accountMap[$subscription['account_id']] ?? null; ?>
                <?php $statusClass = $statusClasses[$subscription['status']] ?? $statusClasses['active']; ?>
                <tr>
                    <td><?= esc($account['account_name'] ?? '-') ?></td>
                    <td><?= esc($account['email'] ?? '-') ?></td>
                    <td><?= esc($subscription['store_source']) ?></td>
                    <td><?= esc($subscription['subscription_type']) ?></td>
                    <td class="font-mono text-[11px] leading-[1.55] tracking-[-0.01em] text-[rgba(38,37,30,0.76)]"><?= esc($subscription['expired_at'] ?? '-') ?></td>
                    <td>
                        <span class="<?= $statusClass ?>">
                            <?= esc(\App\Services\SubscriptionStatusService::humanize($subscription['status'])) ?>
                        </span>
                    </td>
                    <td>
                        <?php $isPro = \App\Services\SubscriptionStatusService::normalizeAccountType((string) ($subscription['account_type'] ?? 'free')) === 'pro'; ?>
                        <?php $isPersonalInvite = $isPro && \App\Services\SubscriptionStatusService::normalizeProAccountType((string) ($subscription['pro_account_type'] ?? '')) === 'personal_invite'; ?>
                        <?php if (! $isPro): ?>
                            <span class="font-ui text-[13px] text-[rgba(38,37,30,0.55)]">N/A</span>
                        <?php else: ?>
                            <?php $usage5h = $subscription['usages']['5h'] ?? null; ?>
                            <?php $p5 = (int) ($usage5h['remaining_percent'] ?? 0); ?>
                            <span class="font-ui text-[13px]"><?= esc((string) $p5) ?>%</span>
                            <?php $progressColor5 = $p5 > 60 ? 'bg-success' : ($p5 > 30 ? 'bg-gold' : 'bg-danger'); ?>
                            <div class="mt-1.5 h-2.5 w-full overflow-hidden rounded-full border border-[rgba(38,37,30,0.1)] bg-surface200"><span class="block h-full rounded-full <?= $progressColor5 ?>" style="width: <?= esc((string) $p5) ?>%"></span></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php $usageWSeller = $subscription['usages']['weekly'] ?? null; ?>
                        <?php $pwSeller = (int) ($usageWSeller['remaining_percent'] ?? 0); ?>
                        <?php $progressColorWSeller = $pwSeller > 60 ? 'bg-success' : ($pwSeller > 30 ? 'bg-gold' : 'bg-danger'); ?>
                        <div class="font-ui text-[12px] leading-[1.35] text-[rgba(38,37,30,0.66)]"><?= esc($isPersonalInvite ? 'Seller' : 'Weekly') ?></div>
                        <span class="font-ui text-[13px]"><?= esc((string) $pwSeller) ?>%</span>
                        <div class="mt-1.5 h-2.5 w-full overflow-hidden rounded-full border border-[rgba(38,37,30,0.1)] bg-surface200"><span class="block h-full rounded-full <?= $progressColorWSeller ?>" style="width: <?= esc((string) $pwSeller) ?>%"></span></div>

                        <?php if ($isPersonalInvite): ?>
                            <?php $usageWPersonal = $subscription['usages']['weekly_personal'] ?? null; ?>
                            <?php $pwPersonal = (int) ($usageWPersonal['remaining_percent'] ?? 0); ?>
                            <?php $progressColorWPersonal = $pwPersonal > 60 ? 'bg-success' : ($pwPersonal > 30 ? 'bg-gold' : 'bg-danger'); ?>
                            <div class="mt-2 font-ui text-[12px] leading-[1.35] text-[rgba(38,37,30,0.66)]">Personal</div>
                            <span class="font-ui text-[13px]"><?= esc((string) $pwPersonal) ?>%</span>
                            <div class="mt-1.5 h-2.5 w-full overflow-hidden rounded-full border border-[rgba(38,37,30,0.1)] bg-surface200"><span class="block h-full rounded-full <?= $progressColorWPersonal ?>" style="width: <?= esc((string) $pwPersonal) ?>%"></span></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($account): ?>
                            <a class="inline-flex items-center justify-center gap-1.5 rounded-full border border-[rgba(38,37,30,0.1)] bg-surface400 px-2 py-[3px] no-underline font-display text-[13px] font-medium tracking-[0.025em] text-[rgba(38,37,30,0.6)] hover:text-danger hover:border-[rgba(38,37,30,0.2)]" href="/accounts/<?= esc((string) $account['id']) ?>">Lihat Detail</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="mt-6 <?= $cardBase ?> bg-surface400 space-y-2">
    <h2>Monitoring Free Account</h2>
    <p class="font-ui text-[13px] leading-[1.44] tracking-[0.01em] text-[rgba(38,37,30,0.55)]">Khusus akun free dengan fokus utama pada sisa kuota weekly.</p>
    <div class="<?= $tableWrap ?>">
        <table class="data-table-cards">
            <thead>
            <tr>
                <th>Nama</th>
                <th>Email</th>
                <th>Tipe Subscription</th>
                <th>Status</th>
                <th>Usage Weekly</th>
                <th>Aksi</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($urutFreeWeekly === []): ?>
                <tr>
                    <td colspan="6" class="font-ui text-[13px] text-[rgba(38,37,30,0.55)]">Belum ada data free account.</td>
                </tr>
            <?php endif; ?>

            <?php foreach ($urutFreeWeekly as $subscription): ?>
                <?php $account = $accountMap[$subscription['account_id']] ?? null; ?>
                <?php $statusClass = $statusClasses[$subscription['status']] ?? $statusClasses['active']; ?>
                <?php $pwWeekly = (int) (($subscription['usages']['weekly']['remaining_percent'] ?? 0)); ?>
                <?php $progressColorWeekly = $pwWeekly > 60 ? 'bg-success' : ($pwWeekly > 30 ? 'bg-gold' : 'bg-danger'); ?>
                <tr>
                    <td><?= esc($account['account_name'] ?? '-') ?></td>
                    <td><?= esc($account['email'] ?? '-') ?></td>
                    <td><?= esc($subscription['subscription_type'] ?? 'Free Weekly') ?></td>
                    <td><span class="<?= $statusClass ?>"><?= esc(\App\Services\SubscriptionStatusService::humanize((string) ($subscription['status'] ?? 'active'))) ?></span></td>
                    <td>
                        <span class="font-ui text-[13px]"><?= esc((string) $pwWeekly) ?>%</span>
                        <div class="mt-1.5 h-2.5 w-full overflow-hidden rounded-full border border-[rgba(38,37,30,0.1)] bg-surface200"><span class="block h-full rounded-full <?= $progressColorWeekly ?>" style="width: <?= esc((string) $pwWeekly) ?>%"></span></div>
                    </td>
                    <td>
                        <?php if ($account): ?>
                            <a class="inline-flex items-center justify-center gap-1.5 rounded-full border border-[rgba(38,37,30,0.1)] bg-surface400 px-2 py-[3px] no-underline font-display text-[13px] font-medium tracking-[0.025em] text-[rgba(38,37,30,0.6)] hover:text-danger hover:border-[rgba(38,37,30,0.2)]" href="/accounts/<?= esc((string) $account['id']) ?>">Lihat Detail</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="mt-6 grid gap-3.5 [grid-template-columns:repeat(auto-fit,minmax(180px,1fr))] max-[900px]:[grid-template-columns:repeat(2,minmax(0,1fr))] max-[600px]:grid-cols-1">
    <article class="relative overflow-hidden rounded-lg border border-[color-mix(in_srgb,#9fbbe0_36%,rgba(38,37,30,0.1)_64%)] bg-[color-mix(in_srgb,#9fbbe0_16%,#ebeae5_84%)] p-4 shadow-[rgba(0,0,0,0.02)_0_0_16px,rgba(0,0,0,0.008)_0_0_8px] transition-[box-shadow,border-color] duration-200 hover:border-[rgba(38,37,30,0.2)] hover:shadow-[rgba(0,0,0,0.14)_0_28px_70px,rgba(0,0,0,0.1)_0_14px_32px] before:absolute before:inset-y-0 before:left-0 before:w-1 before:bg-[color-mix(in_srgb,#9fbbe0_65%,#26251e_35%)]">
        <div class="font-display text-[11px] uppercase tracking-[0.06em] font-medium text-[rgba(38,37,30,0.72)]">Total Akun</div>
        <div class="mt-2 font-display text-[clamp(34px,3.3vw,44px)] leading-[1.08] tracking-[-0.65px] font-semibold text-[color-mix(in_srgb,#9fbbe0_40%,#26251e_60%)]"><?= esc((string) $summary['total_accounts']) ?></div>
    </article>

    <article class="relative overflow-hidden rounded-lg border border-[color-mix(in_srgb,#c0a8dd_34%,rgba(38,37,30,0.1)_66%)] bg-[color-mix(in_srgb,#c0a8dd_16%,#ebeae5_84%)] p-4 shadow-[rgba(0,0,0,0.02)_0_0_16px,rgba(0,0,0,0.008)_0_0_8px] transition-[box-shadow,border-color] duration-200 hover:border-[rgba(38,37,30,0.2)] hover:shadow-[rgba(0,0,0,0.14)_0_28px_70px,rgba(0,0,0,0.1)_0_14px_32px] before:absolute before:inset-y-0 before:left-0 before:w-1 before:bg-[color-mix(in_srgb,#c0a8dd_65%,#26251e_35%)]">
        <div class="font-display text-[11px] uppercase tracking-[0.06em] font-medium text-[rgba(38,37,30,0.72)]">Total Subscription</div>
        <div class="mt-2 font-display text-[clamp(34px,3.3vw,44px)] leading-[1.08] tracking-[-0.65px] font-semibold text-[color-mix(in_srgb,#c0a8dd_40%,#26251e_60%)]"><?= esc((string) $totalSubscription) ?></div>
    </article>

    <article class="relative overflow-hidden rounded-lg border border-[color-mix(in_srgb,#8fb8aa_36%,rgba(38,37,30,0.1)_64%)] bg-[color-mix(in_srgb,#8fb8aa_16%,#ebeae5_84%)] p-4 shadow-[rgba(0,0,0,0.02)_0_0_16px,rgba(0,0,0,0.008)_0_0_8px] transition-[box-shadow,border-color] duration-200 hover:border-[rgba(38,37,30,0.2)] hover:shadow-[rgba(0,0,0,0.14)_0_28px_70px,rgba(0,0,0,0.1)_0_14px_32px] before:absolute before:inset-y-0 before:left-0 before:w-1 before:bg-[color-mix(in_srgb,#8fb8aa_65%,#26251e_35%)]">
        <div class="font-display text-[11px] uppercase tracking-[0.06em] font-medium text-[rgba(38,37,30,0.72)]">Total Free Account</div>
        <div class="mt-2 font-display text-[clamp(34px,3.3vw,44px)] leading-[1.08] tracking-[-0.65px] font-semibold text-[color-mix(in_srgb,#8fb8aa_40%,#26251e_60%)]"><?= esc((string) $totalFreeAccount) ?></div>
    </article>

    <article class="relative overflow-hidden rounded-lg border border-[color-mix(in_srgb,#1f8a65_30%,rgba(38,37,30,0.1)_70%)] bg-[color-mix(in_srgb,#9fc9a2_18%,#ebeae5_82%)] p-4 shadow-[rgba(0,0,0,0.02)_0_0_16px,rgba(0,0,0,0.008)_0_0_8px] transition-[box-shadow,border-color] duration-200 hover:border-[rgba(38,37,30,0.2)] hover:shadow-[rgba(0,0,0,0.14)_0_28px_70px,rgba(0,0,0,0.1)_0_14px_32px] before:absolute before:inset-y-0 before:left-0 before:w-1 before:bg-[color-mix(in_srgb,#1f8a65_65%,#26251e_35%)]">
        <div class="font-display text-[11px] uppercase tracking-[0.06em] font-medium text-[rgba(38,37,30,0.72)]">Subscription Aktif</div>
        <div class="mt-2 font-display text-[clamp(34px,3.3vw,44px)] leading-[1.08] tracking-[-0.65px] font-semibold text-[color-mix(in_srgb,#1f8a65_40%,#26251e_60%)]"><?= esc((string) $summary['active']) ?></div>
    </article>

    <article class="relative overflow-hidden rounded-lg border border-[color-mix(in_srgb,#cf2d56_32%,rgba(38,37,30,0.1)_68%)] bg-[color-mix(in_srgb,#dfa88f_24%,#ebeae5_76%)] p-4 shadow-[rgba(0,0,0,0.02)_0_0_16px,rgba(0,0,0,0.008)_0_0_8px] transition-[box-shadow,border-color] duration-200 hover:border-[rgba(38,37,30,0.2)] hover:shadow-[rgba(0,0,0,0.14)_0_28px_70px,rgba(0,0,0,0.1)_0_14px_32px] before:absolute before:inset-y-0 before:left-0 before:w-1 before:bg-[color-mix(in_srgb,#cf2d56_65%,#26251e_35%)]">
        <div class="font-display text-[11px] uppercase tracking-[0.06em] font-medium text-[rgba(38,37,30,0.72)]">Perlu Tindak Lanjut</div>
        <div class="mt-2 font-display text-[clamp(34px,3.3vw,44px)] leading-[1.08] tracking-[-0.65px] font-semibold text-[color-mix(in_srgb,#cf2d56_40%,#26251e_60%)]"><?= esc((string) $butuhTindakan) ?></div>
    </article>
</section>

<section class="mt-6 grid gap-3.5 [grid-template-columns:repeat(auto-fit,minmax(280px,1fr))]">
    <article class="rounded-lg border border-[rgba(38,37,30,0.1)] bg-surface400 p-4 shadow-[rgba(0,0,0,0.02)_0_0_16px,rgba(0,0,0,0.008)_0_0_8px] transition-[box-shadow,border-color] duration-200 hover:border-[rgba(38,37,30,0.2)] hover:shadow-[rgba(0,0,0,0.14)_0_28px_70px,rgba(0,0,0,0.1)_0_14px_32px]">
        <h3>Prioritas Hari Ini</h3>
        <p class="font-ui text-[13px] leading-[1.44] tracking-[0.01em] text-[rgba(38,37,30,0.55)]">Fokuskan review pada akses yang segera habis dan kuota yang kritis.</p>
        <div class="mt-2 space-y-2">
            <div class="flex flex-wrap items-center gap-2 rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 px-2.5 py-2">
                <span class="<?= $statusClasses['expiring_soon'] ?>">Expiring Soon</span>
                <span class="font-ui text-[13px] text-[rgba(38,37,30,0.64)]"><strong class="font-semibold text-[rgba(38,37,30,0.82)]"><?= esc((string) $summary['expiring_soon']) ?></strong> subscription mendekati jatuh tempo.</span>
            </div>

            <div class="flex flex-wrap items-center gap-2 rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 px-2.5 py-2">
                <span class="<?= $statusClasses['expired'] ?>">Expired</span>
                <span class="font-ui text-[13px] text-[rgba(38,37,30,0.64)]"><strong class="font-semibold text-[rgba(38,37,30,0.82)]"><?= esc((string) $summary['expired']) ?></strong> subscription sudah melewati masa invite.</span>
            </div>

            <div class="flex flex-wrap items-center gap-2 rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 px-2.5 py-2">
                <span class="<?= $statusClasses['deactivated'] ?>">Deactivated</span>
                <span class="font-ui text-[13px] text-[rgba(38,37,30,0.64)]"><strong class="font-semibold text-[rgba(38,37,30,0.82)]"><?= esc((string) $summary['deactivated']) ?></strong> workspace dinonaktifkan.</span>
            </div>

            <div class="flex flex-wrap items-center gap-2 rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 px-2.5 py-2">
                <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-[3px] border border-[color-mix(in_srgb,#9fbbe0_40%,transparent_60%)] text-[#2d4f7d] bg-[color-mix(in_srgb,#9fbbe0_20%,#f2f1ed_80%)] font-display text-[14px] leading-[1.5]">5H Kritis <= 20%</span>
                <span class="font-ui text-[13px] text-[rgba(38,37,30,0.64)]"><strong class="font-semibold text-[rgba(38,37,30,0.82)]"><?= esc((string) $usageKritis5h) ?></strong> subscription.</span>
            </div>

            <div class="flex flex-wrap items-center gap-2 rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 px-2.5 py-2">
                <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-[3px] border border-[color-mix(in_srgb,#c0a8dd_42%,transparent_58%)] text-[#5f4a83] bg-[color-mix(in_srgb,#c0a8dd_20%,#f2f1ed_80%)] font-display text-[14px] leading-[1.5]">Weekly Kritis <= 20%</span>
                <span class="font-ui text-[13px] text-[rgba(38,37,30,0.64)]"><strong class="font-semibold text-[rgba(38,37,30,0.82)]"><?= esc((string) $usageKritisWeekly) ?></strong> subscription.</span>
            </div>

            <div class="flex flex-wrap items-center gap-2 rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 px-2.5 py-2">
                <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-[3px] border border-[color-mix(in_srgb,#8fb8aa_42%,transparent_58%)] text-[#3f6357] bg-[color-mix(in_srgb,#8fb8aa_20%,#f2f1ed_80%)] font-display text-[14px] leading-[1.5]">Free Weekly <= 20%</span>
                <span class="font-ui text-[13px] text-[rgba(38,37,30,0.64)]"><strong class="font-semibold text-[rgba(38,37,30,0.82)]"><?= esc((string) $usageKritisWeeklyFree) ?></strong> free account.</span>
            </div>
        </div>
    </article>

    <article class="rounded-lg border border-[rgba(38,37,30,0.1)] bg-surface400 p-4 shadow-[rgba(0,0,0,0.02)_0_0_16px,rgba(0,0,0,0.008)_0_0_8px] transition-[box-shadow,border-color] duration-200 hover:border-[rgba(38,37,30,0.2)] hover:shadow-[rgba(0,0,0,0.14)_0_28px_70px,rgba(0,0,0,0.1)_0_14px_32px]">
        <h3>Alur Monitoring</h3>
        <p class="font-ui text-[13px] leading-[1.44] tracking-[0.01em] text-[rgba(38,37,30,0.55)]">Urutan kerja cepat untuk operasional harian.</p>
        <div class="relative mt-2 pl-[18px] before:content-[''] before:absolute before:left-[7px] before:top-1 before:bottom-1 before:w-px before:bg-[rgba(38,37,30,0.1)]">
            <div class="relative pl-[18px] pt-[3px] pb-[10px] before:content-[''] before:absolute before:left-[-1px] before:top-2 before:w-2 before:h-2 before:rounded-full before:border before:border-[rgba(38,37,30,0.1)] before:bg-[#dfa88f]">
                <div class="font-ui text-[12px] uppercase tracking-[0.06em] font-medium text-[rgba(38,37,30,0.65)]">Analisis</div>
                <div class="font-display text-[15px] leading-[1.52]">Identifikasi invite yang akan expired dalam waktu dekat.</div>
            </div>
            <div class="relative pl-[18px] pt-[3px] pb-[10px] before:content-[''] before:absolute before:left-[-1px] before:top-2 before:w-2 before:h-2 before:rounded-full before:border before:border-[rgba(38,37,30,0.1)] before:bg-[#9fc9a2]">
                <div class="font-ui text-[12px] uppercase tracking-[0.06em] font-medium text-[rgba(38,37,30,0.65)]">Penyaringan</div>
                <div class="font-display text-[15px] leading-[1.52]">Pisahkan akun dengan status Expiring Soon dan Expired.</div>
            </div>
            <div class="relative pl-[18px] pt-[3px] pb-[10px] before:content-[''] before:absolute before:left-[-1px] before:top-2 before:w-2 before:h-2 before:rounded-full before:border before:border-[rgba(38,37,30,0.1)] before:bg-[#9fbbe0]">
                <div class="font-ui text-[12px] uppercase tracking-[0.06em] font-medium text-[rgba(38,37,30,0.65)]">Pemeriksaan</div>
                <div class="font-display text-[15px] leading-[1.52]">Cek usage 5 jam dan mingguan, terutama yang mendekati nol.</div>
            </div>
            <div class="relative pl-[18px] pt-[3px] pb-[10px] before:content-[''] before:absolute before:left-[-1px] before:top-2 before:w-2 before:h-2 before:rounded-full before:border before:border-[rgba(38,37,30,0.1)] before:bg-[#c0a8dd]">
                <div class="font-ui text-[12px] uppercase tracking-[0.06em] font-medium text-[rgba(38,37,30,0.65)]">Tindakan</div>
                <div class="font-display text-[15px] leading-[1.52]">Perbarui data usage / subscription lalu kirim reminder Telegram.</div>
            </div>
        </div>
    </article>
</section>
<script>
(() => {
    const weeklyRoot = document.getElementById('dashboard-usage-chart-weekly');
    const fiveHourRoot = document.getElementById('dashboard-usage-chart-5h');
    const dateInput = document.querySelector('[data-dashboard-chart-date]');
    const caption = document.querySelector('[data-dashboard-chart-caption]');
    if (!weeklyRoot || !fiveHourRoot) {
        return;
    }

    const endpoint = weeklyRoot.getAttribute('data-chart-endpoint') || '';

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
                    label: String(item?.label ?? 'Akun'),
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

        const width = 920;
        const height = 350;
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
                    <svg viewBox="0 0 ${width} ${height}" class="min-w-[720px] w-full h-auto rounded-md border border-[rgba(38,37,30,0.1)] bg-[color-mix(in_srgb,#f2f1ed_85%,white_15%)] p-2">
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

    const applyCaption = (dateText, weeklyCount, fiveHourCount) => {
        if (!caption) {
            return;
        }

        caption.textContent = `Tanggal data: ${dateText} · weekly ${weeklyCount} seri · 5h ${fiveHourCount} seri`;
    };

    const setChartLoading = (isLoading) => {
        weeklyRoot.classList.toggle('opacity-70', isLoading);
        weeklyRoot.classList.toggle('pointer-events-none', isLoading);
        fiveHourRoot.classList.toggle('opacity-70', isLoading);
        fiveHourRoot.classList.toggle('pointer-events-none', isLoading);
    };

    const loadChartByDate = async (dateValue) => {
        if (!endpoint || !dateValue) {
            return;
        }

        setChartLoading(true);
        try {
            const response = await fetch(`${endpoint}?date=${encodeURIComponent(dateValue)}`, {
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
            renderTimeSeriesChart(fiveHourRoot, fiveHourSeries, 'Belum ada data usage 5h (akun pro) pada tanggal ini.');
            applyCaption(payload.date || dateValue, weeklySeries.length, fiveHourSeries.length);
        } catch (error) {
            weeklyRoot.innerHTML = '<p class="font-ui text-[13px] text-[rgba(38,37,30,0.55)]">Gagal memuat data weekly pada tanggal tersebut.</p>';
            fiveHourRoot.innerHTML = '<p class="font-ui text-[13px] text-[rgba(38,37,30,0.55)]">Gagal memuat data 5h pada tanggal tersebut.</p>';
            applyCaption(dateValue, 0, 0);
        } finally {
            setChartLoading(false);
        }
    };

    const initialDate = dateInput?.value || weeklyRoot.getAttribute('data-initial-date') || '';
    if (dateInput && initialDate !== '') {
        dateInput.value = initialDate;
    }

    if (endpoint && initialDate !== '') {
        loadChartByDate(initialDate);
    } else {
        renderTimeSeriesChart(weeklyRoot, [], 'Belum ada data weekly pada tanggal ini.');
        renderTimeSeriesChart(fiveHourRoot, [], 'Belum ada data usage 5h (akun pro) pada tanggal ini.');
        applyCaption(initialDate !== '' ? initialDate : '-', 0, 0);
    }

    dateInput?.addEventListener('change', () => {
        const selectedDate = dateInput.value;
        if (selectedDate !== '') {
            loadChartByDate(selectedDate);
        }
    });
})();
</script>
<?= $this->endSection() ?>
