<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<h1>Account List</h1>
<p class="muted">Invite Expired mengacu ke masa akses langganan (bukan akun utama).</p>

<div class="card" style="margin-bottom: 16px;">
    <h2>Create Account + Subscription</h2>
    <form method="post" action="/accounts/create">
        <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
            <div>
                <label>Account Name</label>
                <input type="text" name="account_name" required value="">
            </div>
            <div>
                <label>Email</label>
                <input type="email" name="email" required value="">
            </div>
            <div>
                <label>Password Hint</label>
                <input type="text" name="password_hint" value="">
            </div>
            <div>
                <label>Store Source</label>
                <input type="text" name="store_source" required value="">
            </div>
            <div>
                <label>Subscription Type</label>
                <input type="text" name="subscription_type" required value="">
            </div>
            <div>
                <label>Invite Expired</label>
                <input type="datetime-local" name="expired_at" required value="">
            </div>
        </div>

        <label>Notes</label>
        <textarea name="notes" rows="3"></textarea>

        <button type="submit">Simpan</button>
    </form>
</div>

<table>
    <thead>
    <tr>
        <th>Name</th>
        <th>Email</th>
        <th>Store Source</th>
        <th>Subscription Type</th>
        <th>Invite Expired</th>
        <th>Status</th>
        <th>5H</th>
        <th>Weekly</th>
        <th>Actions</th>
    </tr>
    </thead>
    <tbody>
    <?php if ($accounts === []): ?>
        <tr><td colspan="9" class="muted">Belum ada data account.</td></tr>
    <?php endif; ?>

    <?php foreach ($accounts as $account): ?>
        <?php foreach ($account['subscriptions'] as $subscription): ?>
            <tr>
                <td><?= esc($account['account_name']) ?></td>
                <td><?= esc($account['email']) ?></td>
                <td><?= esc($subscription['store_source']) ?></td>
                <td><?= esc($subscription['subscription_type']) ?></td>
                <td><?= esc($subscription['expired_at']) ?></td>
                <td>
                    <span class="badge <?= esc($subscription['status']) ?>">
                        <?= esc(\App\Services\SubscriptionStatusService::humanize($subscription['status'])) ?>
                    </span>
                </td>
                <td>
                    <?php $p5 = (int) ($subscription['usages']['5h']['remaining_percent'] ?? 0); ?>
                    <?= esc((string) $p5) ?>%
                    <div class="progress"><span class="<?= $p5 > 60 ? 'p-green' : ($p5 > 30 ? 'p-yellow' : 'p-red') ?>" style="width: <?= esc((string) $p5) ?>%"></span></div>
                </td>
                <td>
                    <?php $pw = (int) ($subscription['usages']['weekly']['remaining_percent'] ?? 0); ?>
                    <?= esc((string) $pw) ?>%
                    <div class="progress"><span class="<?= $pw > 60 ? 'p-green' : ($pw > 30 ? 'p-yellow' : 'p-red') ?>" style="width: <?= esc((string) $pw) ?>%"></span></div>
                </td>
                <td>
                    <a class="btn btn-secondary" href="/accounts/<?= esc((string) $account['id']) ?>">Detail</a>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endforeach; ?>
    </tbody>
</table>
<?= $this->endSection() ?>
