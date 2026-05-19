<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$totalSubscription = count($subscriptions);
$freeSubscriptions = is_array($freeSubscriptions ?? null) ? $freeSubscriptions : [];
$routerUsageByEmail = is_array($routerUsageByEmail ?? null) ? $routerUsageByEmail : [];
$totalFreeAccount = count($freeSubscriptions);
$butuhTindakan = 0;
$routerActive24h = 0;
$routerTotalRequests24h = 0;
$routerTotalTokens24h = 0;
$routerTopAccount7d = ['email' => '-', 'tokens_7d' => 0];
$routerDigestLogPath = trim((string) env('router.logPath', ''));
$routerDigestProvider = trim((string) env('router.provider', '9router'));
$routerDigestConfigured = $routerDigestLogPath !== '';
$routerShipperLogPath = trim((string) env('ROUTER_SHIPPER_LOG_PATH', ''));
$routerShipperProvider = trim((string) env('ROUTER_SHIPPER_PROVIDER', '9router'));
$routerShipperEndpoint = trim((string) env('ROUTER_SHIPPER_ENDPOINT', ''));
$routerEffectiveLogPath = $routerDigestLogPath !== '' ? $routerDigestLogPath : $routerShipperLogPath;
$routerCommandExposeLog = trim($routerEffectiveLogPath) !== ''
    ? '9router --log | tee -a ' . $routerEffectiveLogPath
    : '9router --log | tee -a /path/to/9router.log';
$routerCommandShipper = '/opt/lampp/bin/php scripts/router_log_shipper.php';
$routerCommandShipperFull = 'php scripts/router_log_shipper.php --log='
    . ($routerShipperLogPath !== '' ? $routerShipperLogPath : '/path/to/9router.log')
    . ' --endpoint='
    . ($routerShipperEndpoint !== '' ? $routerShipperEndpoint : 'https://domainkamu.com/api/router/ingest')
    . ' --provider='
    . ($routerShipperProvider !== '' ? $routerShipperProvider : '9router');

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
usort($urutFreeWeekly, static function (array $a, array $b) use ($accountMap, $routerUsageByEmail): int {
    $emailA = strtolower(trim((string) ($accountMap[$a['account_id']]['email'] ?? '')));
    $emailB = strtolower(trim((string) ($accountMap[$b['account_id']]['email'] ?? '')));
    $tokensA = (int) ($routerUsageByEmail[$emailA]['tokens_7d'] ?? 0);
    $tokensB = (int) ($routerUsageByEmail[$emailB]['tokens_7d'] ?? 0);

    return $tokensB <=> $tokensA;
});

foreach ($subscriptions as $subscription) {
    if (in_array($subscription['status'], ['expiring_soon', 'expired', 'deactivated'], true)) {
        $butuhTindakan++;
    }
}

foreach ($accountMap as $account) {
    $email = strtolower(trim((string) ($account['email'] ?? '')));
    if ($email === '') {
        continue;
    }

    $usage = $routerUsageByEmail[$email] ?? null;
    if (! is_array($usage)) {
        continue;
    }

    $requests24h = (int) ($usage['requests_24h'] ?? 0);
    $tokens24h = (int) ($usage['tokens_24h'] ?? 0);
    $tokens7d = (int) ($usage['tokens_7d'] ?? 0);

    if ($requests24h > 0) {
        $routerActive24h++;
    }

    $routerTotalRequests24h += $requests24h;
    $routerTotalTokens24h += $tokens24h;

    if ($tokens7d > (int) $routerTopAccount7d['tokens_7d']) {
        $routerTopAccount7d = [
            'email' => $email,
            'tokens_7d' => $tokens7d,
        ];
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
?>

<section class="<?= $sectionTitle ?>">
    <h1>Dasbor Monitoring Subscription</h1>
    <p class="max-w-[760px] font-serif text-[clamp(18px,1.35vw,20px)] leading-[1.45] text-[rgba(38,37,30,0.64)]">Pantau kesehatan akun, status akses workspace, dan sisa kuota pemakaian dalam satu tampilan. Tanggal berakhir dihitung otomatis dari tanggal langganan + durasi satu bulan (jika dipilih).</p>
    <p class="font-mono text-[11px] leading-[1.55] tracking-[-0.01em] text-[rgba(38,37,30,0.76)]">Endpoint cepat: /api/accounts · /api/subscriptions · /api/router/analytics/summary · /api/router/analytics/charts · /api/telegram/test</p>
</section>

<section class="mt-6 <?= $cardBase ?> bg-surface400 space-y-2">
    <h2>Command Cepat 9router</h2>
    <p class="font-ui text-[13px] leading-[1.44] tracking-[0.01em] text-[rgba(38,37,30,0.55)]">
        Shortcut operasional untuk menjalankan router dengan expose log dan mengirim log ke endpoint ingest.
    </p>
    <div class="grid gap-3 [grid-template-columns:repeat(auto-fit,minmax(320px,1fr))]">
        <article class="rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 p-3 space-y-2">
            <div class="flex flex-wrap items-center justify-between gap-2">
            <h3>1) Jalankan Router + Tulis Log</h3>
                <button
                    class="inline-flex items-center justify-center gap-1.5 rounded-full border border-[rgba(38,37,30,0.14)] bg-surface400 px-3 py-[5px] font-display text-[13px] font-medium tracking-[0.02em] text-[rgba(38,37,30,0.8)] transition-colors duration-150 hover:text-danger hover:border-[rgba(38,37,30,0.22)]"
                    type="button"
                    data-copy-text="<?= esc($routerCommandExposeLog, 'attr') ?>"
                    data-copy-default-label="Copy"
                >Copy</button>
            </div>
            <pre class="overflow-x-auto rounded-md border border-[rgba(38,37,30,0.12)] bg-surface400 px-3 py-2 font-mono text-[11px] leading-[1.5] tracking-[-0.01em] text-[rgba(38,37,30,0.78)]"><code><?= esc($routerCommandExposeLog) ?></code></pre>
            <p class="font-ui text-[12px] leading-[1.4] text-[rgba(38,37,30,0.6)]">
                Tujuan: menulis baris log usage (`[USAGE]`, `[REQUEST]`, dll) ke file log untuk diproses collector/shipper.
            </p>
        </article>
        <article class="rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 p-3 space-y-2">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3>2) Jalankan Log Shipper</h3>
                <button
                    class="inline-flex items-center justify-center gap-1.5 rounded-full border border-[rgba(38,37,30,0.14)] bg-surface400 px-3 py-[5px] font-display text-[13px] font-medium tracking-[0.02em] text-[rgba(38,37,30,0.8)] transition-colors duration-150 hover:text-danger hover:border-[rgba(38,37,30,0.22)]"
                    type="button"
                    data-copy-text="<?= esc($routerCommandShipper, 'attr') ?>"
                    data-copy-default-label="Copy"
                >Copy</button>
            </div>
            <pre class="overflow-x-auto rounded-md border border-[rgba(38,37,30,0.12)] bg-surface400 px-3 py-2 font-mono text-[11px] leading-[1.5] tracking-[-0.01em] text-[rgba(38,37,30,0.78)]"><code><?= esc($routerCommandShipper) ?></code></pre>
            <p class="font-ui text-[12px] leading-[1.4] text-[rgba(38,37,30,0.6)]">
                Versi env-based. Jika `ROUTER_SHIPPER_*` di `.env` sudah lengkap, command ini cukup.
            </p>
            <details class="rounded-md border border-[rgba(38,37,30,0.1)] bg-surface400 px-2.5 py-2">
                <summary class="cursor-pointer font-ui text-[12px] text-[rgba(38,37,30,0.72)]">Lihat versi command lengkap (dengan argumen)</summary>
                <div class="mt-2 space-y-2">
                    <button
                        class="inline-flex items-center justify-center gap-1.5 rounded-full border border-[rgba(38,37,30,0.14)] bg-surface300 px-2 py-[4px] font-display text-[12px] font-medium tracking-[0.02em] text-[rgba(38,37,30,0.8)] transition-colors duration-150 hover:text-danger hover:border-[rgba(38,37,30,0.22)]"
                        type="button"
                        data-copy-text="<?= esc($routerCommandShipperFull, 'attr') ?>"
                        data-copy-default-label="Copy Full"
                    >Copy Full</button>
                    <pre class="overflow-x-auto rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 px-2.5 py-2 font-mono text-[11px] leading-[1.5] tracking-[-0.01em] text-[rgba(38,37,30,0.78)]"><code><?= esc($routerCommandShipperFull) ?></code></pre>
                </div>
            </details>
        </article>
    </div>
</section>

<section class="mt-6 <?= $cardBase ?> bg-surface400 space-y-2">
    <h2>Grafik Observability 9router</h2>
    <p class="font-ui text-[13px] leading-[1.44] tracking-[0.01em] text-[rgba(38,37,30,0.55)]">
        Grafik ini membaca event usage 9router (input/output token, cache, reasoning, latency, dan distribusi akun/model).
    </p>
    <div class="rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 px-3 py-2.5">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="space-y-1">
                <div class="font-display text-[14px] leading-[1.45] text-[rgba(38,37,30,0.82)]">Digest Data 9router dari Log Lokal</div>
                <p class="font-ui text-[12px] leading-[1.4] text-[rgba(38,37,30,0.6)]">
                    Mode ini membaca file log lokal (`router.logPath`) dengan provider default `<?= esc($routerDigestProvider !== '' ? $routerDigestProvider : '9router') ?>`.
                </p>
            </div>
            <form method="post" action="/dashboard/router/digest" class="flex flex-wrap items-center gap-2">
                <label class="inline-flex items-center gap-1.5 rounded-full border border-[rgba(38,37,30,0.14)] bg-surface400 px-2 py-[4px] font-ui text-[12px] leading-[1.4] text-[rgba(38,37,30,0.7)]">
                    <input type="checkbox" name="reset_cursor" value="1" class="accent-[rgba(38,37,30,0.75)]">
                    Reset cursor
                </label>
                <button
                    type="submit"
                    class="inline-flex items-center justify-center gap-1.5 rounded-full border border-[rgba(38,37,30,0.14)] bg-surface400 px-3 py-[5px] font-display text-[13px] font-medium tracking-[0.02em] text-[rgba(38,37,30,0.8)] transition-colors duration-150 hover:text-danger hover:border-[rgba(38,37,30,0.22)] disabled:cursor-not-allowed disabled:opacity-55"
                    <?= $routerDigestConfigured ? '' : 'disabled' ?>
                >
                    Digest Sekarang
                </button>
            </form>
        </div>
        <?php if (! $routerDigestConfigured): ?>
            <p class="mt-2 font-ui text-[12px] leading-[1.4] text-[#8f1f3c]">
                `router.logPath` belum diisi di `.env`. Isi dulu di halaman Settings agar tombol digest bisa dipakai.
            </p>
        <?php else: ?>
            <p class="mt-2 font-mono text-[11px] leading-[1.5] tracking-[-0.01em] text-[rgba(38,37,30,0.62)]">
                Log path: <?= esc($routerDigestLogPath) ?>
            </p>
        <?php endif; ?>
    </div>
    <div class="flex flex-wrap items-end gap-2">
        <label class="font-ui text-[12px] uppercase tracking-[0.05em] font-medium text-[rgba(38,37,30,0.62)]">
            Provider
            <select
                data-router-provider
                class="mt-1 w-[160px] rounded-md border border-[rgba(38,37,30,0.22)] bg-surface200 px-3 py-2 font-ui text-[13px] leading-[1.45] text-[rgba(38,37,30,0.9)] shadow-[inset_0_1px_0_rgba(255,255,255,0.45)] outline-none transition-[border-color,box-shadow,background-color,color] duration-150 focus:border-[rgba(38,37,30,0.38)] focus:bg-[#f8f7f3] focus:shadow-[rgba(0,0,0,0.1)_0_4px_12px]"
            >
                <option value="">All</option>
                <option value="codex">codex</option>
                <option value="openai">openai</option>
                <option value="9router">9router</option>
            </select>
        </label>
        <label class="font-ui text-[12px] uppercase tracking-[0.05em] font-medium text-[rgba(38,37,30,0.62)]">
            Rentang
            <select
                data-router-days
                class="mt-1 w-[140px] rounded-md border border-[rgba(38,37,30,0.22)] bg-surface200 px-3 py-2 font-ui text-[13px] leading-[1.45] text-[rgba(38,37,30,0.9)] shadow-[inset_0_1px_0_rgba(255,255,255,0.45)] outline-none transition-[border-color,box-shadow,background-color,color] duration-150 focus:border-[rgba(38,37,30,0.38)] focus:bg-[#f8f7f3] focus:shadow-[rgba(0,0,0,0.1)_0_4px_12px]"
            >
                <option value="7">7 hari</option>
                <option value="14">14 hari</option>
                <option value="30" selected>30 hari</option>
                <option value="60">60 hari</option>
                <option value="90">90 hari</option>
            </select>
        </label>
        <p class="font-ui text-[12px] leading-[1.4] text-[rgba(38,37,30,0.55)]" data-router-chart-caption>
            Memuat observability 9router...
        </p>
    </div>
    <div class="grid gap-3 [grid-template-columns:repeat(auto-fit,minmax(320px,1fr))]">
        <article class="rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 p-3 space-y-2">
            <h3>Total Token Harian (Input + Output)</h3>
            <div data-router-daily-chart class="rounded-md border border-[rgba(38,37,30,0.1)] bg-surface400 p-2"></div>
        </article>
        <article class="rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 p-3 space-y-2">
            <h3>Aktivitas Request per Jam</h3>
            <div data-router-hourly-chart class="rounded-md border border-[rgba(38,37,30,0.1)] bg-surface400 p-2"></div>
        </article>
    </div>
    <div class="grid gap-3 [grid-template-columns:repeat(auto-fit,minmax(300px,1fr))]">
        <article class="rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 p-3 space-y-2">
            <h3 class="flex items-center justify-between gap-2">
                <span>Cache Ratio Harian (%)</span>
                <span
                    data-router-cache-badge
                    class="inline-flex items-center rounded-full border px-2 py-[3px] font-display text-[12px] leading-[1.4] border-[rgba(38,37,30,0.14)] text-[rgba(38,37,30,0.72)] bg-[rgba(38,37,30,0.06)]"
                >Loading</span>
            </h3>
            <div data-router-cache-chart class="rounded-md border border-[rgba(38,37,30,0.1)] bg-surface400 p-2"></div>
        </article>
        <article class="rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 p-3 space-y-2">
            <h3 class="flex items-center justify-between gap-2">
                <span>Latency Harian (Avg ms)</span>
                <span
                    data-router-latency-badge
                    class="inline-flex items-center rounded-full border px-2 py-[3px] font-display text-[12px] leading-[1.4] border-[rgba(38,37,30,0.14)] text-[rgba(38,37,30,0.72)] bg-[rgba(38,37,30,0.06)]"
                >Loading</span>
            </h3>
            <div data-router-latency-chart class="rounded-md border border-[rgba(38,37,30,0.1)] bg-surface400 p-2"></div>
        </article>
        <article class="rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 p-3 space-y-2">
            <h3 class="flex items-center justify-between gap-2">
                <span>Success Rate Stream (%)</span>
                <span
                    data-router-success-badge
                    class="inline-flex items-center rounded-full border px-2 py-[3px] font-display text-[12px] leading-[1.4] border-[rgba(38,37,30,0.14)] text-[rgba(38,37,30,0.72)] bg-[rgba(38,37,30,0.06)]"
                >Loading</span>
            </h3>
            <div data-router-status-chart class="rounded-md border border-[rgba(38,37,30,0.1)] bg-surface400 p-2"></div>
        </article>
    </div>
    <div class="grid gap-3 [grid-template-columns:repeat(auto-fit,minmax(320px,1fr))]">
        <article class="rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 p-3 space-y-2">
            <h3>Usage per Akun</h3>
            <div data-router-account-chart class="rounded-md border border-[rgba(38,37,30,0.1)] bg-surface400 p-2"></div>
        </article>
        <article class="rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 p-3 space-y-2">
            <h3>Distribusi per Model</h3>
            <div data-router-model-chart class="rounded-md border border-[rgba(38,37,30,0.1)] bg-surface400 p-2"></div>
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
    <p class="font-ui text-[13px] leading-[1.44] tracking-[0.01em] text-[rgba(38,37,30,0.55)]">Tampilan lengkap untuk evaluasi status invite dan usage 9router per akun (berdasarkan email).</p>
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
                <th>Usage 9router 24 Jam</th>
                <th>Usage 9router 7 Hari</th>
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
                <?php $emailKey = strtolower(trim((string) ($account['email'] ?? ''))); ?>
                <?php $routerUsage = $routerUsageByEmail[$emailKey] ?? []; ?>
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
                        <?php $tokens24h = (int) ($routerUsage['tokens_24h'] ?? 0); ?>
                        <?php $requests24h = (int) ($routerUsage['requests_24h'] ?? 0); ?>
                        <?php if ($tokens24h <= 0 && $requests24h <= 0): ?>
                            <span class="font-ui text-[13px] text-[rgba(38,37,30,0.55)]">Belum ada data</span>
                        <?php else: ?>
                            <div class="font-ui text-[13px] text-[rgba(38,37,30,0.82)]"><?= esc(number_format($tokens24h)) ?> token</div>
                            <div class="font-mono text-[11px] leading-[1.55] tracking-[-0.01em] text-[rgba(38,37,30,0.62)]"><?= esc(number_format($requests24h)) ?> request</div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php $tokens7d = (int) ($routerUsage['tokens_7d'] ?? 0); ?>
                        <?php $requests7d = (int) ($routerUsage['requests_7d'] ?? 0); ?>
                        <?php $cacheRatio7d = (float) ($routerUsage['cache_ratio_7d'] ?? 0); ?>
                        <?php if ($tokens7d <= 0 && $requests7d <= 0): ?>
                            <span class="font-ui text-[13px] text-[rgba(38,37,30,0.55)]">Belum ada data</span>
                        <?php else: ?>
                            <div class="font-ui text-[13px] text-[rgba(38,37,30,0.82)]"><?= esc(number_format($tokens7d)) ?> token</div>
                            <div class="font-mono text-[11px] leading-[1.55] tracking-[-0.01em] text-[rgba(38,37,30,0.62)]"><?= esc(number_format($requests7d)) ?> request · cache <?= esc(number_format($cacheRatio7d, 1)) ?>%</div>
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
    <p class="font-ui text-[13px] leading-[1.44] tracking-[0.01em] text-[rgba(38,37,30,0.55)]">Khusus akun free dengan fokus utama pada usage 9router 7 hari.</p>
    <div class="<?= $tableWrap ?>">
        <table class="data-table-cards">
            <thead>
            <tr>
                <th>Nama</th>
                <th>Email</th>
                <th>Tipe Subscription</th>
                <th>Status</th>
                <th>Usage 9router 7 Hari</th>
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
                <?php $emailKey = strtolower(trim((string) ($account['email'] ?? ''))); ?>
                <?php $routerUsage = $routerUsageByEmail[$emailKey] ?? []; ?>
                <?php $tokens7d = (int) ($routerUsage['tokens_7d'] ?? 0); ?>
                <?php $requests7d = (int) ($routerUsage['requests_7d'] ?? 0); ?>
                <?php $cacheRatio7d = (float) ($routerUsage['cache_ratio_7d'] ?? 0); ?>
                <tr>
                    <td><?= esc($account['account_name'] ?? '-') ?></td>
                    <td><?= esc($account['email'] ?? '-') ?></td>
                    <td><?= esc($subscription['subscription_type'] ?? 'Free Weekly') ?></td>
                    <td><span class="<?= $statusClass ?>"><?= esc(\App\Services\SubscriptionStatusService::humanize((string) ($subscription['status'] ?? 'active'))) ?></span></td>
                    <td>
                        <?php if ($tokens7d <= 0 && $requests7d <= 0): ?>
                            <span class="font-ui text-[13px] text-[rgba(38,37,30,0.55)]">Belum ada data</span>
                        <?php else: ?>
                            <div class="font-ui text-[13px] text-[rgba(38,37,30,0.82)]"><?= esc(number_format($tokens7d)) ?> token</div>
                            <div class="font-mono text-[11px] leading-[1.55] tracking-[-0.01em] text-[rgba(38,37,30,0.62)]"><?= esc(number_format($requests7d)) ?> request · cache <?= esc(number_format($cacheRatio7d, 1)) ?>%</div>
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
                <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-[3px] border border-[color-mix(in_srgb,#9fbbe0_40%,transparent_60%)] text-[#2d4f7d] bg-[color-mix(in_srgb,#9fbbe0_20%,#f2f1ed_80%)] font-display text-[14px] leading-[1.5]">9router Aktif 24 Jam</span>
                <span class="font-ui text-[13px] text-[rgba(38,37,30,0.64)]"><strong class="font-semibold text-[rgba(38,37,30,0.82)]"><?= esc(number_format($routerActive24h)) ?></strong> akun mengirim request dalam 24 jam terakhir.</span>
            </div>

            <div class="flex flex-wrap items-center gap-2 rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 px-2.5 py-2">
                <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-[3px] border border-[color-mix(in_srgb,#c0a8dd_42%,transparent_58%)] text-[#5f4a83] bg-[color-mix(in_srgb,#c0a8dd_20%,#f2f1ed_80%)] font-display text-[14px] leading-[1.5]">Traffic 24 Jam</span>
                <span class="font-ui text-[13px] text-[rgba(38,37,30,0.64)]"><strong class="font-semibold text-[rgba(38,37,30,0.82)]"><?= esc(number_format($routerTotalTokens24h)) ?></strong> token dari <strong class="font-semibold text-[rgba(38,37,30,0.82)]"><?= esc(number_format($routerTotalRequests24h)) ?></strong> request.</span>
            </div>

            <div class="flex flex-wrap items-center gap-2 rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 px-2.5 py-2">
                <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-[3px] border border-[color-mix(in_srgb,#8fb8aa_42%,transparent_58%)] text-[#3f6357] bg-[color-mix(in_srgb,#8fb8aa_20%,#f2f1ed_80%)] font-display text-[14px] leading-[1.5]">Akun Tertinggi 7 Hari</span>
                <span class="font-ui text-[13px] text-[rgba(38,37,30,0.64)]"><strong class="font-semibold text-[rgba(38,37,30,0.82)]"><?= esc((string) $routerTopAccount7d['email']) ?></strong> dengan <strong class="font-semibold text-[rgba(38,37,30,0.82)]"><?= esc(number_format((int) ($routerTopAccount7d['tokens_7d'] ?? 0))) ?></strong> token.</span>
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
                <div class="font-display text-[15px] leading-[1.52]">Cek usage 9router 24 jam dan 7 hari per akun.</div>
            </div>
            <div class="relative pl-[18px] pt-[3px] pb-[10px] before:content-[''] before:absolute before:left-[-1px] before:top-2 before:w-2 before:h-2 before:rounded-full before:border before:border-[rgba(38,37,30,0.1)] before:bg-[#c0a8dd]">
                <div class="font-ui text-[12px] uppercase tracking-[0.06em] font-medium text-[rgba(38,37,30,0.65)]">Tindakan</div>
                <div class="font-display text-[15px] leading-[1.52]">Perbarui data subscription bila perlu, lalu kirim reminder Telegram.</div>
            </div>
        </div>
    </article>
</section>
<script>
(() => {
    const endpoint = '/api/router/analytics/charts';
    const providerInput = document.querySelector('[data-router-provider]');
    const daysInput = document.querySelector('[data-router-days]');
    const caption = document.querySelector('[data-router-chart-caption]');
    const dailyRoot = document.querySelector('[data-router-daily-chart]');
    const hourlyRoot = document.querySelector('[data-router-hourly-chart]');
    const cacheRoot = document.querySelector('[data-router-cache-chart]');
    const cacheBadge = document.querySelector('[data-router-cache-badge]');
    const latencyRoot = document.querySelector('[data-router-latency-chart]');
    const latencyBadge = document.querySelector('[data-router-latency-badge]');
    const statusRoot = document.querySelector('[data-router-status-chart]');
    const successBadge = document.querySelector('[data-router-success-badge]');
    const accountRoot = document.querySelector('[data-router-account-chart]');
    const modelRoot = document.querySelector('[data-router-model-chart]');

    if (!providerInput || !daysInput || !dailyRoot || !hourlyRoot || !cacheRoot || !latencyRoot || !statusRoot || !accountRoot || !modelRoot) {
        return;
    }

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

    const formatNumber = (value) => {
        const numeric = Number(value ?? 0);
        if (!Number.isFinite(numeric)) {
            return '0';
        }

        return new Intl.NumberFormat('id-ID').format(Math.round(numeric));
    };

    const formatCompact = (value) => {
        const numeric = Number(value ?? 0);
        if (!Number.isFinite(numeric)) {
            return '0';
        }

        return new Intl.NumberFormat('id-ID', { notation: 'compact', maximumFractionDigits: 1 }).format(numeric);
    };

    const formatDecimal = (value, digits = 2) => {
        const numeric = Number(value ?? 0);
        if (!Number.isFinite(numeric)) {
            return '0';
        }

        return new Intl.NumberFormat('id-ID', {
            minimumFractionDigits: 0,
            maximumFractionDigits: digits,
        }).format(numeric);
    };

    const formatLatencyCompact = (value) => {
        const numeric = Number(value ?? 0);
        if (!Number.isFinite(numeric) || numeric <= 0) {
            return '0ms';
        }

        if (numeric >= 1000) {
            return `${formatDecimal(numeric / 1000, 1)}s`;
        }

        return `${formatDecimal(numeric, 0)}ms`;
    };

    const averageSeriesValue = (rows, key, ignoreZero = false) => {
        const data = Array.isArray(rows) ? rows : [];
        const values = data
            .map((row) => Number(row?.[key] ?? 0))
            .filter((value) => Number.isFinite(value) && value >= 0 && (!ignoreZero || value > 0));

        if (values.length === 0) {
            return 0;
        }

        const total = values.reduce((sum, value) => sum + value, 0);
        return total / values.length;
    };

    const cacheVisual = (avgRatio) => {
        if (avgRatio >= 80) {
            return { color: '#1f8a65', note: `Healthy (${formatDecimal(avgRatio, 2)}%)` };
        }
        if (avgRatio >= 50) {
            return { color: '#c08532', note: `Moderate (${formatDecimal(avgRatio, 2)}%)` };
        }

        return { color: '#cf2d56', note: `Low (${formatDecimal(avgRatio, 2)}%)` };
    };

    const latencyVisual = (avgLatencyMs) => {
        if (avgLatencyMs <= 0) {
            return { color: '#2f6db5', note: 'No latency data', label: 'No Data', tone: 'neutral' };
        }
        if (avgLatencyMs <= 8000) {
            return { color: '#1f8a65', note: `Fast (${formatDecimal(avgLatencyMs, 0)}ms)`, label: 'Fast', tone: 'good' };
        }
        if (avgLatencyMs <= 15000) {
            return { color: '#c08532', note: `Moderate (${formatDecimal(avgLatencyMs, 0)}ms)`, label: 'Moderate', tone: 'warn' };
        }

        return { color: '#cf2d56', note: `Slow (${formatDecimal(avgLatencyMs, 0)}ms)`, label: 'Slow', tone: 'bad' };
    };

    const cacheVisualTone = (avgRatio) => {
        if (avgRatio >= 80) {
            return { label: 'Healthy', tone: 'good' };
        }
        if (avgRatio >= 50) {
            return { label: 'Moderate', tone: 'warn' };
        }

        return { label: 'Low', tone: 'bad' };
    };

    const successRateVisual = (successRate) => {
        if (successRate >= 80) {
            return { label: 'Healthy', tone: 'good' };
        }
        if (successRate >= 50) {
            return { label: 'Moderate', tone: 'warn' };
        }

        return { label: 'Low', tone: 'bad' };
    };

    const badgeToneClass = (tone) => {
        if (tone === 'good') {
            return 'border-[color-mix(in_srgb,#1f8a65_40%,transparent_60%)] text-[#165a44] bg-[color-mix(in_srgb,#1f8a65_16%,#f2f1ed_84%)]';
        }
        if (tone === 'warn') {
            return 'border-[color-mix(in_srgb,#c08532_42%,transparent_58%)] text-[#8f4d10] bg-[color-mix(in_srgb,#c08532_22%,#f2f1ed_78%)]';
        }
        if (tone === 'bad') {
            return 'border-[color-mix(in_srgb,#cf2d56_42%,transparent_58%)] text-[#8f1f3c] bg-[color-mix(in_srgb,#cf2d56_16%,#f2f1ed_84%)]';
        }

        return 'border-[rgba(38,37,30,0.14)] text-[rgba(38,37,30,0.72)] bg-[rgba(38,37,30,0.06)]';
    };

    const setBadge = (badgeNode, label, tone = 'neutral') => {
        if (!badgeNode) {
            return;
        }

        const tones = ['good', 'warn', 'bad', 'neutral'];
        tones.forEach((item) => {
            const classes = badgeToneClass(item).split(' ');
            classes.forEach((className) => badgeNode.classList.remove(className));
        });

        badgeToneClass(tone).split(' ').forEach((className) => badgeNode.classList.add(className));
        badgeNode.textContent = label;
    };

    const renderBarList = (root, rows, valueKey, labelKey, emptyMessage, noteBuilder = null) => {
        const data = Array.isArray(rows) ? rows : [];
        if (data.length === 0) {
            root.innerHTML = `<p class="font-ui text-[13px] text-[rgba(38,37,30,0.55)]">${escapeHtml(emptyMessage)}</p>`;
            return;
        }

        const maxValue = Math.max(...data.map((item) => Number(item?.[valueKey] ?? 0)), 1);
        const items = data.map((item) => {
            const label = String(item?.[labelKey] ?? '-');
            const value = Number(item?.[valueKey] ?? 0);
            const width = Math.max(2, Math.round((value / maxValue) * 100));
            const note = typeof noteBuilder === 'function' ? noteBuilder(item) : '';

            return `<li class="space-y-1 rounded-md border border-[rgba(38,37,30,0.1)] bg-surface400 p-2">
                <div class="flex items-center justify-between gap-2">
                    <span class="truncate font-ui text-[12px] text-[rgba(38,37,30,0.82)]">${escapeHtml(label)}</span>
                    <span class="font-mono text-[11px] text-[rgba(38,37,30,0.76)]">${formatCompact(value)}</span>
                </div>
                <div class="h-2 rounded-full border border-[rgba(38,37,30,0.1)] bg-surface200 overflow-hidden">
                    <span class="block h-full rounded-full bg-[color-mix(in_srgb,#2f6db5_72%,#9fbbe0_28%)]" style="width:${width}%"></span>
                </div>
                ${note !== '' ? `<p class="font-mono text-[11px] text-[rgba(38,37,30,0.62)]">${escapeHtml(note)}</p>` : ''}
            </li>`;
        }).join('');

        root.innerHTML = `<ul class="space-y-2">${items}</ul>`;
    };

    const renderSimpleLineChart = (root, rows, xKey, yKey, emptyMessage, color = '#2f6db5', valueFormatter = formatNumber, suffix = '', noteText = '') => {
        const data = Array.isArray(rows) ? rows : [];
        if (data.length === 0) {
            root.innerHTML = `<p class="font-ui text-[13px] text-[rgba(38,37,30,0.55)]">${escapeHtml(emptyMessage)}</p>`;
            return;
        }

        const width = 900;
        const height = 280;
        const margin = { top: 18, right: 16, bottom: 38, left: 44 };
        const plotWidth = width - margin.left - margin.right;
        const plotHeight = height - margin.top - margin.bottom;

        const values = data.map((row) => Number(row?.[yKey] ?? 0)).filter((n) => Number.isFinite(n) && n >= 0);
        const maxValue = Math.max(...values, 1);

        const xFromIndex = (index) => margin.left + (index / Math.max(data.length - 1, 1)) * plotWidth;
        const yFromValue = (value) => margin.top + (1 - (value / maxValue)) * plotHeight;

        const path = data.map((row, index) => {
            const value = Math.max(0, Number(row?.[yKey] ?? 0));
            const x = xFromIndex(index);
            const y = yFromValue(value);
            return `${index === 0 ? 'M' : 'L'} ${x} ${y}`;
        }).join(' ');

        const circles = data.map((row, index) => {
            const value = Math.max(0, Number(row?.[yKey] ?? 0));
            const x = xFromIndex(index);
            const y = yFromValue(value);
            const label = String(row?.[xKey] ?? '');
            return `<circle cx="${x}" cy="${y}" r="3" fill="${color}" stroke="white" stroke-width="1.2">
                <title>${escapeHtml(label)} · ${valueFormatter(value)}${escapeHtml(suffix)}</title>
            </circle>`;
        }).join('');

        const xTicks = data
            .filter((_, index) => index === 0 || index === data.length - 1 || index % Math.max(1, Math.floor(data.length / 4)) === 0)
            .map((row, index) => {
                const sourceIndex = data.indexOf(row);
                const x = xFromIndex(sourceIndex);
                return `<text x="${x}" y="${height - 14}" text-anchor="middle" font-size="11" fill="rgba(38,37,30,0.62)">${escapeHtml(String(row?.[xKey] ?? ''))}</text>`;
            }).join('');

        root.innerHTML = `
            <div class="space-y-2">
                <div class="overflow-x-auto">
                    <svg viewBox="0 0 ${width} ${height}" class="min-w-[700px] w-full h-auto rounded-md border border-[rgba(38,37,30,0.1)] bg-[color-mix(in_srgb,#f2f1ed_85%,white_15%)] p-2">
                        <line x1="${margin.left}" y1="${height - margin.bottom}" x2="${width - margin.right}" y2="${height - margin.bottom}" stroke="rgba(38,37,30,0.2)" />
                        <line x1="${margin.left}" y1="${margin.top}" x2="${margin.left}" y2="${height - margin.bottom}" stroke="rgba(38,37,30,0.2)" />
                        <path d="${path}" fill="none" stroke="${color}" stroke-width="2.4" stroke-linecap="round" />
                        ${circles}
                        ${xTicks}
                        <text x="${margin.left - 10}" y="${margin.top + 10}" text-anchor="end" font-size="11" fill="rgba(38,37,30,0.62)">${valueFormatter(maxValue)}${escapeHtml(suffix)}</text>
                        <text x="${margin.left - 10}" y="${height - margin.bottom + 4}" text-anchor="end" font-size="11" fill="rgba(38,37,30,0.62)">0</text>
                    </svg>
                </div>
                ${noteText !== '' ? `<p class="font-mono text-[11px] leading-[1.45] text-[rgba(38,37,30,0.62)]">${escapeHtml(noteText)}</p>` : ''}
            </div>
        `;
    };

    const renderStatusBreakdown = (root, rows) => {
        const data = Array.isArray(rows) ? rows : [];
        if (data.length === 0) {
            root.innerHTML = '<p class="font-ui text-[13px] text-[rgba(38,37,30,0.55)]">Belum ada data status stream pada rentang ini.</p>';
            return;
        }

        const toneByStatus = {
            complete: 'bg-[color-mix(in_srgb,#1f8a65_70%,#9fc9a2_30%)]',
            disconnect: 'bg-[color-mix(in_srgb,#cf2d56_72%,#dfa88f_28%)]',
            other: 'bg-[color-mix(in_srgb,#c08532_70%,#f2c073_30%)]',
        };

        const items = data.map((row) => {
            const status = String(row?.status ?? 'other').toLowerCase();
            const count = Number(row?.total_requests ?? 0);
            const percentage = Number(row?.percentage ?? 0);
            const width = Math.max(2, Math.round(Math.max(0, Math.min(100, percentage))));
            const colorClass = toneByStatus[status] ?? toneByStatus.other;

            return `<li class="space-y-1 rounded-md border border-[rgba(38,37,30,0.1)] bg-surface400 p-2">
                <div class="flex items-center justify-between gap-2">
                    <span class="font-ui text-[12px] uppercase tracking-[0.05em] text-[rgba(38,37,30,0.78)]">${escapeHtml(status)}</span>
                    <span class="font-mono text-[11px] text-[rgba(38,37,30,0.76)]">${formatDecimal(percentage, 2)}%</span>
                </div>
                <div class="h-2 rounded-full border border-[rgba(38,37,30,0.1)] bg-surface200 overflow-hidden">
                    <span class="block h-full rounded-full ${colorClass}" style="width:${width}%"></span>
                </div>
                <p class="font-mono text-[11px] text-[rgba(38,37,30,0.62)]">${formatNumber(count)} request</p>
            </li>`;
        }).join('');

        root.innerHTML = `<ul class="space-y-2">${items}</ul>`;
    };

    const setLoading = (state) => {
        [dailyRoot, hourlyRoot, cacheRoot, latencyRoot, statusRoot, accountRoot, modelRoot].forEach((node) => {
            node.classList.toggle('opacity-70', state);
            node.classList.toggle('pointer-events-none', state);
        });
    };

    const updateCaption = (filters, dailyRows) => {
        if (!caption) {
            return;
        }

        const provider = String(filters?.provider ?? 'all');
        const days = Number(filters?.days ?? 30);
        const points = Array.isArray(dailyRows) ? dailyRows.length : 0;
        caption.textContent = `Provider: ${provider} · rentang ${days} hari · titik harian ${points}`;
    };

    const loadRouterCharts = async () => {
        const provider = String(providerInput.value || '');
        const days = String(daysInput.value || '30');
        const url = `${endpoint}?provider=${encodeURIComponent(provider)}&days=${encodeURIComponent(days)}&top=10`;

        setLoading(true);
        try {
            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const payload = await response.json();
            if (payload?.status !== 'success' || typeof payload.data !== 'object' || payload.data === null) {
                throw new Error('Invalid router analytics response');
            }

            const data = payload.data;
            const dailyRows = Array.isArray(data.daily_tokens) ? data.daily_tokens : [];
            const hourlyRows = Array.isArray(data.activity_by_hour) ? data.activity_by_hour : [];
            const accountRows = Array.isArray(data.usage_by_account) ? data.usage_by_account : [];
            const modelRows = Array.isArray(data.usage_by_model) ? data.usage_by_model : [];
            const statusRows = Array.isArray(data.status_breakdown) ? data.status_breakdown : [];
            const successRate = Number(data.success_rate_percent ?? 0);
            const avgCacheRatio = averageSeriesValue(dailyRows, 'cache_ratio_percent');
            const avgLatency = averageSeriesValue(dailyRows, 'avg_latency_ms', true);
            const cacheTone = cacheVisual(avgCacheRatio);
            const latencyTone = latencyVisual(avgLatency);
            const cacheBadgeState = cacheVisualTone(avgCacheRatio);
            const successBadgeState = successRateVisual(successRate);

            renderSimpleLineChart(dailyRoot, dailyRows, 'day', 'total_tokens', 'Belum ada data token harian pada rentang ini.', '#2f6db5');
            renderSimpleLineChart(hourlyRoot, hourlyRows, 'label', 'total_requests', 'Belum ada data aktivitas request pada rentang ini.', '#1f8a65');
            renderSimpleLineChart(cacheRoot, dailyRows, 'day', 'cache_ratio_percent', 'Belum ada data cache ratio harian pada rentang ini.', cacheTone.color, (value) => formatDecimal(value, 2), '%', `Threshold: >=80 healthy · 50-79 moderate · <50 low | avg ${formatDecimal(avgCacheRatio, 2)}%`);
            renderSimpleLineChart(latencyRoot, dailyRows, 'day', 'avg_latency_ms', 'Belum ada data latency harian pada rentang ini.', latencyTone.color, (value) => formatDecimal(value, 0), 'ms', `Threshold: <=8000 fast · 8001-15000 moderate · >15000 slow | avg ${formatDecimal(avgLatency, 0)}ms`);
            renderStatusBreakdown(statusRoot, statusRows);

            renderBarList(
                accountRoot,
                accountRows,
                'total_tokens',
                'display',
                'Belum ada data usage per akun pada rentang ini.',
                (row) => `${formatDecimal(row?.usage_percent ?? 0, 2)}% dari total · req=${formatNumber(row?.total_requests)} · cache=${row?.cache_ratio_percent ?? 0}%`
            );

            renderBarList(
                modelRoot,
                modelRows,
                'total_tokens',
                'model',
                'Belum ada data usage per model pada rentang ini.',
                (row) => `${formatDecimal(row?.usage_percent ?? 0, 2)}% dari total · req=${formatNumber(row?.total_requests)} · cache=${row?.cache_ratio_percent ?? 0}%`
            );

            updateCaption(data.filters ?? {}, dailyRows);
            if (caption) {
                caption.textContent = `${caption.textContent} · cache ${cacheTone.note} · latency ${latencyTone.note} · success ${formatDecimal(successRate, 2)}%`;
            }
            setBadge(cacheBadge, `${cacheBadgeState.label} ${formatDecimal(avgCacheRatio, 1)}%`, cacheBadgeState.tone);
            setBadge(latencyBadge, `${latencyTone.label ?? 'No Data'} ${formatLatencyCompact(avgLatency)}`, latencyTone.tone ?? 'neutral');
            setBadge(successBadge, `${successBadgeState.label} ${formatDecimal(successRate, 1)}%`, successBadgeState.tone);
        } catch (error) {
            const message = '<p class="font-ui text-[13px] text-[rgba(38,37,30,0.55)]">Gagal memuat data observability 9router.</p>';
            dailyRoot.innerHTML = message;
            hourlyRoot.innerHTML = message;
            cacheRoot.innerHTML = message;
            latencyRoot.innerHTML = message;
            statusRoot.innerHTML = message;
            accountRoot.innerHTML = message;
            modelRoot.innerHTML = message;
            setBadge(cacheBadge, 'No Data', 'neutral');
            setBadge(latencyBadge, 'No Data', 'neutral');
            setBadge(successBadge, 'No Data', 'neutral');
            if (caption) {
                caption.textContent = 'Gagal memuat data observability 9router.';
            }
        } finally {
            setLoading(false);
        }
    };

    providerInput.addEventListener('change', loadRouterCharts);
    daysInput.addEventListener('change', loadRouterCharts);
    loadRouterCharts();

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
