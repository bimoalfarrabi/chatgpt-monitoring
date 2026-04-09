<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<h1>Telegram Settings</h1>
<p class="muted">Simpan kredensial Telegram bot untuk reminder subscription expired.</p>

<div class="card">
    <form method="post" action="/telegram/settings">
        <label>Bot Token</label>
        <input type="text" name="bot_token" value="<?= esc($settings['bot_token'] ?? '') ?>" placeholder="123456:ABCDEF...">

        <label>Chat ID</label>
        <input type="text" name="chat_id" value="<?= esc($settings['chat_id'] ?? '') ?>" placeholder="-100xxxxxxxxxx">

        <label>
            <input type="checkbox" name="is_active" value="1" style="width:auto; margin-right:8px;" <?= (int) ($settings['is_active'] ?? 0) === 1 ? 'checked' : '' ?>>
            Active
        </label>

        <div style="margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap;">
            <button type="submit">Save Settings</button>
        </div>
    </form>

    <form method="post" action="/telegram/test" style="margin-top: 12px;">
        <button class="btn-secondary" type="submit">Test Message</button>
    </form>
</div>
<?= $this->endSection() ?>
