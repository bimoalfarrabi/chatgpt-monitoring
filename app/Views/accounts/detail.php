<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<h1>Account Detail</h1>

<div class="card" style="margin-bottom: 16px;">
    <h2>Account Info</h2>
    <p><strong>Name:</strong> <?= esc($account['account_name']) ?></p>
    <p><strong>Email:</strong> <?= esc($account['email']) ?></p>
    <p><strong>Password Hint:</strong> <?= esc($account['password_hint'] ?? '-') ?></p>
    <p><strong>Notes:</strong> <?= esc($account['notes'] ?? '-') ?></p>

    <form class="inline" method="post" action="/accounts/<?= esc((string) $account['id']) ?>/delete" onsubmit="return confirm('Hapus account ini?')">
        <button class="btn btn-danger" type="submit">Delete Account</button>
    </form>
</div>

<?php if ($subscriptions === []): ?>
    <div class="card muted">Belum ada subscription untuk account ini.</div>
<?php endif; ?>

<?php foreach ($subscriptions as $subscription): ?>
    <div class="card" style="margin-bottom: 16px;">
        <h3>
            <?= esc($subscription['subscription_type']) ?>
            <span class="badge <?= esc($subscription['status']) ?>">
                <?= esc(\App\Services\SubscriptionStatusService::humanize($subscription['status'])) ?>
            </span>
        </h3>

        <form method="post" action="/subscriptions/<?= esc((string) $subscription['id']) ?>/update">
            <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
                <div>
                    <label>Store Source</label>
                    <input type="text" name="store_source" required value="<?= esc($subscription['store_source']) ?>">
                </div>
                <div>
                    <label>Subscription Type</label>
                    <input type="text" name="subscription_type" required value="<?= esc($subscription['subscription_type']) ?>">
                </div>
                <div>
                    <label>Invite Expired Date</label>
                    <input type="datetime-local" name="expired_at" required value="<?= esc(date('Y-m-d\TH:i', strtotime($subscription['expired_at']))) ?>">
                </div>
            </div>
            <button type="submit">Update Subscription</button>
        </form>

        <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-top: 14px;">
            <?php foreach (['5h' => '5 Hour Usage', 'weekly' => 'Weekly Usage'] as $type => $label): ?>
                <?php $usage = $subscription['usages'][$type] ?? null; ?>
                <div class="card" style="background: #f8fafc;">
                    <h4 style="margin-bottom: 8px;"><?= esc($label) ?></h4>
                    <?php if (! $usage): ?>
                        <div class="muted">Data usage belum tersedia.</div>
                    <?php else: ?>
                        <?php $percent = (int) $usage['remaining_percent']; ?>
                        <div><?= esc((string) $percent) ?>% remaining</div>
                        <div class="progress"><span class="<?= $percent > 60 ? 'p-green' : ($percent > 30 ? 'p-yellow' : 'p-red') ?>" style="width: <?= esc((string) $percent) ?>%"></span></div>
                        <div class="muted">Reset: <?= esc($usage['reset_at'] ?? '-') ?></div>
                        <button type="button" onclick="document.getElementById('usage-modal-<?= esc((string) $usage['id']) ?>').showModal()">Update Usage</button>

                        <dialog id="usage-modal-<?= esc((string) $usage['id']) ?>">
                            <h3>Update Usage (<?= esc($label) ?>)</h3>
                            <form method="post" action="/usages/<?= esc((string) $usage['id']) ?>/update">
                                <label>Remaining %</label>
                                <input type="number" min="0" max="100" name="remaining_percent" required value="<?= esc((string) $usage['remaining_percent']) ?>">

                                <label>Reset Time</label>
                                <input type="datetime-local" name="reset_at" required value="<?= esc(date('Y-m-d\TH:i', strtotime((string) $usage['reset_at']))) ?>">

                                <button type="submit">Simpan</button>
                                <button class="btn-secondary" type="button" onclick="document.getElementById('usage-modal-<?= esc((string) $usage['id']) ?>').close()">Batal</button>
                            </form>
                        </dialog>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>

<div class="card">
    <h2>History Table</h2>
    <table>
        <thead>
        <tr>
            <th>Subscription ID</th>
            <th>Usage Type</th>
            <th>Old %</th>
            <th>New %</th>
            <th>Updated At</th>
        </tr>
        </thead>
        <tbody>
        <?php if ($history === []): ?>
            <tr><td colspan="5" class="muted">Belum ada history usage.</td></tr>
        <?php endif; ?>

        <?php foreach ($history as $row): ?>
            <tr>
                <td><?= esc((string) $row['subscription_id']) ?></td>
                <td><?= esc($row['usage_type']) ?></td>
                <td><?= esc((string) ($row['old_percent'] ?? '-')) ?></td>
                <td><?= esc((string) $row['new_percent']) ?></td>
                <td><?= esc($row['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?= $this->endSection() ?>
