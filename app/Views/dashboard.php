<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<h1>Dashboard</h1>
<p class="muted">Monitoring akun, status invite/subscription, dan usage 5H + Weekly.</p>

<div class="grid grid-4" style="margin-bottom: 16px;">
    <div class="card">
        <div class="muted">Total Accounts</div>
        <h2><?= esc((string) $summary['total_accounts']) ?></h2>
    </div>
    <div class="card">
        <div class="muted">Active Subscription</div>
        <h2><?= esc((string) $summary['active']) ?></h2>
    </div>
    <div class="card">
        <div class="muted">Expiring Soon</div>
        <h2><?= esc((string) $summary['expiring_soon']) ?></h2>
    </div>
    <div class="card">
        <div class="muted">Expired</div>
        <h2><?= esc((string) $summary['expired']) ?></h2>
    </div>
</div>

<h2>Table Accounts</h2>
<table>
    <thead>
    <tr>
        <th>Name</th>
        <th>Email</th>
        <th>Store</th>
        <th>Subscription</th>
        <th>Invite Expired</th>
        <th>Status</th>
        <th>5H</th>
        <th>Weekly</th>
        <th>Action</th>
    </tr>
    </thead>
    <tbody>
    <?php if ($subscriptions === []): ?>
        <tr>
            <td colspan="9" class="muted">Belum ada data.</td>
        </tr>
    <?php endif; ?>

    <?php foreach ($subscriptions as $subscription): ?>
        <?php $account = $accountMap[$subscription['account_id']] ?? null; ?>
        <tr>
            <td><?= esc($account['account_name'] ?? '-') ?></td>
            <td><?= esc($account['email'] ?? '-') ?></td>
            <td><?= esc($subscription['store_source']) ?></td>
            <td><?= esc($subscription['subscription_type']) ?></td>
            <td><?= esc($subscription['expired_at']) ?></td>
            <td>
                <span class="badge <?= esc($subscription['status']) ?>">
                    <?= esc(\App\Services\SubscriptionStatusService::humanize($subscription['status'])) ?>
                </span>
            </td>
            <td>
                <?php $usage5h = $subscription['usages']['5h'] ?? null; ?>
                <?php $p5 = (int) ($usage5h['remaining_percent'] ?? 0); ?>
                <?= esc((string) $p5) ?>%
                <div class="progress"><span class="<?= $p5 > 60 ? 'p-green' : ($p5 > 30 ? 'p-yellow' : 'p-red') ?>" style="width: <?= esc((string) $p5) ?>%"></span></div>
            </td>
            <td>
                <?php $usageW = $subscription['usages']['weekly'] ?? null; ?>
                <?php $pw = (int) ($usageW['remaining_percent'] ?? 0); ?>
                <?= esc((string) $pw) ?>%
                <div class="progress"><span class="<?= $pw > 60 ? 'p-green' : ($pw > 30 ? 'p-yellow' : 'p-red') ?>" style="width: <?= esc((string) $pw) ?>%"></span></div>
            </td>
            <td>
                <?php if ($account): ?>
                    <a class="btn btn-secondary" href="/accounts/<?= esc((string) $account['id']) ?>">Detail</a>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?= $this->endSection() ?>
