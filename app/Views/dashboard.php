<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$totalSubscription = count($subscriptions);
$butuhTindakan = 0;
$usageKritis5h = 0;
$usageKritisWeekly = 0;

$urutExpired = $subscriptions;
usort($urutExpired, static function (array $a, array $b): int {
    $aExpired = (string) ($a['expired_at'] ?? '');
    $bExpired = (string) ($b['expired_at'] ?? '');
    $aTime = $aExpired === '' ? PHP_INT_MAX : (strtotime($aExpired) ?: PHP_INT_MAX);
    $bTime = $bExpired === '' ? PHP_INT_MAX : (strtotime($bExpired) ?: PHP_INT_MAX);
    return $aTime <=> $bTime;
});
$terdekatExpired = array_slice($urutExpired, 0, 5);

foreach ($subscriptions as $subscription) {
    if (in_array($subscription['status'], ['expiring_soon', 'expired', 'deactivated'], true)) {
        $butuhTindakan++;
    }

    $p5 = (int) (($subscription['usages']['5h']['remaining_percent'] ?? 100));
    $pw = (int) (($subscription['usages']['weekly']['remaining_percent'] ?? 100));

    if ($p5 <= 20) {
        $usageKritis5h++;
    }

    if ($pw <= 20) {
        $usageKritisWeekly++;
    }
}

$statusClasses = [
    'active' => 'inline-flex items-center gap-1.5 rounded-full px-2 py-[3px] font-display text-[14px] leading-[1.5] border border-[color-mix(in_srgb,#1f8a65_35%,transparent_65%)] text-[#165a44] bg-[color-mix(in_srgb,#1f8a65_18%,#f2f1ed_82%)]',
    'expiring_soon' => 'inline-flex items-center gap-1.5 rounded-full px-2 py-[3px] font-display text-[14px] leading-[1.5] border border-[color-mix(in_srgb,#c08532_40%,transparent_60%)] text-[#8f4d10] bg-[color-mix(in_srgb,#c08532_22%,#f2f1ed_78%)]',
    'expired' => 'inline-flex items-center gap-1.5 rounded-full px-2 py-[3px] font-display text-[14px] leading-[1.5] border border-[color-mix(in_srgb,#cf2d56_40%,transparent_60%)] text-[#8f1f3c] bg-[color-mix(in_srgb,#cf2d56_18%,#f2f1ed_82%)]',
    'deactivated' => 'inline-flex items-center gap-1.5 rounded-full px-2 py-[3px] font-display text-[14px] leading-[1.5] border border-[color-mix(in_srgb,#444444_34%,transparent_66%)] text-[#2d2d2d] bg-[color-mix(in_srgb,#444444_14%,#f2f1ed_86%)]',
];

$cardBase = 'rounded-lg border border-[rgba(38,37,30,0.1)] p-4 shadow-[rgba(0,0,0,0.02)_0_0_16px,rgba(0,0,0,0.008)_0_0_8px] transition-[box-shadow,border-color] duration-200 hover:border-[rgba(38,37,30,0.2)] hover:shadow-[rgba(0,0,0,0.14)_0_28px_70px,rgba(0,0,0,0.1)_0_14px_32px]';
$tableWrap = 'overflow-auto rounded-lg border border-[rgba(38,37,30,0.1)] bg-surface400 shadow-[rgba(0,0,0,0.02)_0_0_16px,rgba(0,0,0,0.008)_0_0_8px]';
$sectionTitle = 'mb-2 space-y-2';
?>

<section class="<?= $sectionTitle ?>">
    <h1>Dasbor Monitoring Subscription</h1>
    <p class="max-w-[760px] font-serif text-[clamp(18px,1.35vw,20px)] leading-[1.45] text-[rgba(38,37,30,0.64)]">Pantau kesehatan akun, status akses workspace, dan sisa kuota pemakaian dalam satu tampilan. Tanggal berakhir dihitung otomatis dari tanggal langganan + durasi satu bulan (jika dipilih).</p>
    <p class="font-mono text-[11px] leading-[1.55] tracking-[-0.01em] text-[rgba(38,37,30,0.76)]">Endpoint cepat: /api/accounts · /api/subscriptions · /api/account-usages/{id}/update · /api/telegram/test</p>
</section>

<section class="mt-6 <?= $cardBase ?> bg-surface400 space-y-2">
    <h2>Subscription Terdekat Expired</h2>
    <p class="font-ui text-[13px] leading-[1.44] tracking-[0.01em] text-[rgba(38,37,30,0.55)]">5 data teratas berdasarkan tanggal berakhir otomatis paling dekat.</p>
    <div class="<?= $tableWrap ?>">
        <table>
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
        <table>
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
                        <?php $usageW = $subscription['usages']['weekly'] ?? null; ?>
                        <?php $pw = (int) ($usageW['remaining_percent'] ?? 0); ?>
                        <span class="font-ui text-[13px]"><?= esc((string) $pw) ?>%</span>
                        <?php $progressColorW = $pw > 60 ? 'bg-success' : ($pw > 30 ? 'bg-gold' : 'bg-danger'); ?>
                        <div class="mt-1.5 h-2.5 w-full overflow-hidden rounded-full border border-[rgba(38,37,30,0.1)] bg-surface200"><span class="block h-full rounded-full <?= $progressColorW ?>" style="width: <?= esc((string) $pw) ?>%"></span></div>
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
<?= $this->endSection() ?>
