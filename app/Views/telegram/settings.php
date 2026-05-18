<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$cardBase = 'rounded-lg border border-[rgba(38,37,30,0.1)] p-4 shadow-[rgba(0,0,0,0.02)_0_0_16px,rgba(0,0,0,0.008)_0_0_8px] transition-[box-shadow,border-color] duration-200 hover:border-[rgba(38,37,30,0.2)] hover:shadow-[rgba(0,0,0,0.14)_0_28px_70px,rgba(0,0,0,0.1)_0_14px_32px]';

$inputClass = 'mt-1 w-full rounded-md border border-[rgba(38,37,30,0.1)] bg-surface400 px-3 py-2 font-ui text-[13px] leading-[1.45] text-[rgba(38,37,30,0.82)] outline-none transition-[border-color,box-shadow] duration-150 focus:border-[rgba(38,37,30,0.2)] focus:shadow-[rgba(0,0,0,0.1)_0_4px_12px]';
$labelClass = 'font-ui text-[12px] uppercase tracking-[0.05em] font-medium text-[rgba(38,37,30,0.62)]';

$buttonPrimary = 'inline-flex items-center justify-center gap-1.5 rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 px-3 py-2 font-display text-[13px] font-medium tracking-[0.025em] text-ink transition-colors duration-150 hover:text-danger hover:border-[rgba(38,37,30,0.2)]';
$buttonSecondary = 'inline-flex items-center justify-center gap-1.5 rounded-md border border-[rgba(38,37,30,0.1)] bg-surface400 px-3 py-2 font-display text-[13px] font-medium tracking-[0.025em] text-[rgba(38,37,30,0.75)] transition-colors duration-150 hover:text-danger hover:border-[rgba(38,37,30,0.2)]';
$buttonDanger = 'inline-flex items-center justify-center gap-1.5 rounded-md border border-[color-mix(in_srgb,#cf2d56_44%,transparent_56%)] bg-[color-mix(in_srgb,#cf2d56_14%,#f2f1ed_86%)] px-3 py-2 font-display text-[13px] font-medium tracking-[0.025em] text-[#8f1f3c] transition-[border-color,box-shadow] duration-150 hover:border-[color-mix(in_srgb,#cf2d56_55%,transparent_45%)] hover:shadow-[rgba(0,0,0,0.1)_0_4px_12px]';
$hardResetSeed = is_array($hardResetSeed ?? null) ? $hardResetSeed : [];
$hardResetEmails = is_array($hardResetSeed['emails'] ?? null) ? $hardResetSeed['emails'] : [];
$hardResetPreview = array_slice($hardResetEmails, 0, 6);
$hardResetTotal = (int) ($hardResetSeed['total_emails'] ?? 0);
$hardResetRouterAccounts = (int) ($hardResetSeed['total_router_accounts_with_email'] ?? 0);
$hardResetRouterSessions = (int) ($hardResetSeed['total_router_sessions_with_email'] ?? 0);
$envEditor = is_array($envEditor ?? null) ? $envEditor : [];
$envPath = (string) ($envEditor['path'] ?? ROOTPATH . '.env');
$envExists = (bool) ($envEditor['exists'] ?? false);
$envWritable = (bool) ($envEditor['writable'] ?? false);
$envSizeBytes = (int) ($envEditor['size_bytes'] ?? 0);
$envContent = (string) ($envEditor['content'] ?? '');
$envLatestBackupName = trim((string) ($envEditor['latest_backup_name'] ?? ''));
$envLatestBackupMtime = trim((string) ($envEditor['latest_backup_mtime'] ?? ''));
$envLatestBackupSize = (int) ($envEditor['latest_backup_size_bytes'] ?? 0);

$timelineItems = [
    ['label' => 'Baca Data', 'desc' => 'Ambil daftar subscription yang akan habis atau sudah expired.', 'dot' => '#9fbbe0'],
    ['label' => 'Saring', 'desc' => 'Lewati reminder yang sudah pernah dikirim di hari yang sama.', 'dot' => '#9fc9a2'],
    ['label' => 'Kirim', 'desc' => 'Kirim notifikasi ke Telegram dan simpan ke reminder_logs.', 'dot' => '#c0a8dd'],
];
?>

<section class="space-y-2">
    <h1>Settings</h1>
    <p class="max-w-[760px] font-serif text-[clamp(18px,1.35vw,20px)] leading-[1.45] text-[rgba(38,37,30,0.64)]">Panel pengaturan sistem: konfigurasi Telegram reminder dan aksi hard reset akun lokal berbasis data primer dari 9router.</p>
</section>

<section class="mt-6 grid gap-3.5 [grid-template-columns:repeat(auto-fit,minmax(340px,1fr))]">
    <article class="<?= $cardBase ?> bg-surface400 space-y-3">
        <div class="space-y-1">
            <h3>Telegram</h3>
            <p class="font-ui text-[13px] leading-[1.44] tracking-[0.01em] text-[rgba(38,37,30,0.55)]">Kosongkan token/chat ID jika ingin menonaktifkan sementara tanpa menghapus data lama.</p>
        </div>

        <form method="post" action="/settings/telegram" class="space-y-3">
            <label class="<?= $labelClass ?>">
                Bot Token
                <input class="<?= $inputClass ?>" type="text" name="bot_token" value="<?= esc($settings['bot_token'] ?? '') ?>" placeholder="123456:ABCDEF...">
            </label>

            <label class="<?= $labelClass ?>">
                Chat ID
                <input class="<?= $inputClass ?>" type="text" name="chat_id" value="<?= esc($settings['chat_id'] ?? '') ?>" placeholder="-100xxxxxxxxxx">
            </label>

            <label class="inline-flex items-center gap-2 rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 px-3 py-2 font-ui text-[13px] leading-[1.44] tracking-[0.01em] text-[rgba(38,37,30,0.75)]">
                <input type="checkbox" name="is_active" value="1" class="h-4 w-4 accent-danger" <?= (int) ($settings['is_active'] ?? 0) === 1 ? 'checked' : '' ?>>
                Aktifkan Pengiriman Reminder
            </label>

            <div class="flex flex-wrap gap-2 pt-1">
                <button class="<?= $buttonPrimary ?>" type="submit">Simpan Pengaturan</button>
                <button class="<?= $buttonSecondary ?>" formaction="/settings/telegram/test" type="submit">Kirim Test Message</button>
            </div>
        </form>
    </article>

    <article class="<?= $cardBase ?> bg-surface400 space-y-3">
        <div class="space-y-1">
            <h3>Catatan Operasional</h3>
            <p class="font-ui text-[13px] leading-[1.44] tracking-[0.01em] text-[rgba(38,37,30,0.55)]">Perintah cron/manual yang digunakan untuk proses reminder:</p>
            <p class="rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 px-3 py-2 font-mono text-[11px] leading-[1.55] tracking-[-0.01em] text-[rgba(38,37,30,0.76)]">/opt/lampp/bin/php spark reminders:subscriptions</p>
        </div>

        <div class="relative pl-[18px] before:content-[''] before:absolute before:left-[7px] before:top-1 before:bottom-1 before:w-px before:bg-[rgba(38,37,30,0.1)]">
            <?php foreach ($timelineItems as $item): ?>
                <article class="relative pl-[18px] pt-[3px] pb-[10px] before:content-[''] before:absolute before:left-[-1px] before:top-2 before:w-2 before:h-2 before:rounded-full before:border before:border-[rgba(38,37,30,0.1)] before:bg-[var(--dot-color)]" style="--dot-color: <?= esc($item['dot']) ?>">
                    <div class="font-ui text-[12px] uppercase tracking-[0.06em] font-medium text-[rgba(38,37,30,0.65)]"><?= esc($item['label']) ?></div>
                    <div class="font-display text-[15px] leading-[1.52] text-[rgba(38,37,30,0.82)]"><?= esc($item['desc']) ?></div>
                </article>
            <?php endforeach; ?>
        </div>
    </article>

    <article class="<?= $cardBase ?> bg-surface400 space-y-3">
        <div class="space-y-1">
            <h3>Hard Reset Akun Lokal</h3>
            <p class="font-ui text-[13px] leading-[1.44] tracking-[0.01em] text-[rgba(38,37,30,0.55)]">
                Hapus seluruh akun/subscription lokal lalu rebuild dari email yang terdeteksi di data 9router. Event usage 9router tidak dihapus.
            </p>
        </div>

        <div class="grid gap-2 [grid-template-columns:repeat(auto-fit,minmax(150px,1fr))]">
            <div class="rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 px-3 py-2">
                <div class="font-ui text-[11px] uppercase tracking-[0.06em] text-[rgba(38,37,30,0.62)]">Email Unik</div>
                <div class="font-display text-[22px] text-[rgba(38,37,30,0.86)]"><?= esc(number_format($hardResetTotal)) ?></div>
            </div>
            <div class="rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 px-3 py-2">
                <div class="font-ui text-[11px] uppercase tracking-[0.06em] text-[rgba(38,37,30,0.62)]">Router Accounts</div>
                <div class="font-display text-[22px] text-[rgba(38,37,30,0.86)]"><?= esc(number_format($hardResetRouterAccounts)) ?></div>
            </div>
            <div class="rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 px-3 py-2">
                <div class="font-ui text-[11px] uppercase tracking-[0.06em] text-[rgba(38,37,30,0.62)]">Router Sessions</div>
                <div class="font-display text-[22px] text-[rgba(38,37,30,0.86)]"><?= esc(number_format($hardResetRouterSessions)) ?></div>
            </div>
        </div>

        <?php if ($hardResetPreview !== []): ?>
            <div class="rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 px-3 py-2">
                <div class="font-ui text-[11px] uppercase tracking-[0.06em] text-[rgba(38,37,30,0.62)]">Preview Email (6 pertama)</div>
                <div class="mt-1 space-y-1 font-mono text-[11px] leading-[1.5] tracking-[-0.01em] text-[rgba(38,37,30,0.76)]">
                    <?php foreach ($hardResetPreview as $email): ?>
                        <div><?= esc($email) ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <form method="post" action="/settings/hard-reset" class="space-y-2" data-hard-reset-form onsubmit="return confirm('Yakin hard reset? Semua akun/subscription lokal akan dihapus lalu diimport ulang dari 9router.');">
            <label class="<?= $labelClass ?>">
                Konfirmasi
                <input class="<?= $inputClass ?>" type="text" name="confirm_phrase" required placeholder="Ketik: HARD RESET" data-hard-reset-phrase>
            </label>
            <label class="inline-flex items-center gap-2 rounded-md border border-[color-mix(in_srgb,#cf2d56_30%,transparent_70%)] bg-[color-mix(in_srgb,#cf2d56_10%,#f2f1ed_90%)] px-3 py-2 font-ui text-[13px] leading-[1.44] tracking-[0.01em] text-[rgba(38,37,30,0.75)]">
                <input type="checkbox" name="ack_irreversible" value="1" class="h-4 w-4 accent-danger" data-hard-reset-ack>
                Saya paham hard reset ini irreversibel.
            </label>
            <p class="font-ui text-[12px] leading-[1.4] text-[rgba(38,37,30,0.55)]">
                Ketik tepat <strong class="font-medium text-[rgba(38,37,30,0.82)]">HARD RESET</strong> untuk mengeksekusi.
            </p>
            <button class="<?= $buttonDanger ?> disabled:cursor-not-allowed disabled:opacity-55" type="submit" data-hard-reset-submit disabled>Jalankan Hard Reset</button>
        </form>
    </article>
</section>

<section class="mt-3">
    <article class="<?= $cardBase ?> bg-surface400 space-y-3">
        <div class="space-y-1">
            <h3>Editor .env</h3>
            <p class="font-ui text-[13px] leading-[1.44] tracking-[0.01em] text-[rgba(38,37,30,0.55)]">
                Edit dan simpan file konfigurasi `.env` langsung dari web. Simpan akan membuat backup otomatis sebelum overwrite.
            </p>
        </div>

        <div class="rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 px-3 py-2 font-mono text-[11px] leading-[1.55] tracking-[-0.01em] text-[rgba(38,37,30,0.76)]">
            Path: <?= esc($envPath) ?><br>
            Exists: <?= $envExists ? 'yes' : 'no' ?> · Writable: <?= $envWritable ? 'yes' : 'no' ?> · Size: <?= esc(number_format($envSizeBytes)) ?> bytes
        </div>

        <div class="rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 px-3 py-2 font-mono text-[11px] leading-[1.55] tracking-[-0.01em] text-[rgba(38,37,30,0.76)]">
            Latest backup:
            <?php if ($envLatestBackupName === ''): ?>
                -
            <?php else: ?>
                <?= esc($envLatestBackupName) ?> · <?= esc($envLatestBackupMtime !== '' ? $envLatestBackupMtime : '-') ?> · <?= esc(number_format($envLatestBackupSize)) ?> bytes
            <?php endif; ?>
        </div>

        <form method="post" action="/settings/env/save" class="space-y-2">
            <label class="<?= $labelClass ?>">
                Isi .env
                <textarea
                    class="<?= $inputClass ?> font-mono text-[12px] leading-[1.45] min-h-[320px]"
                    name="env_content"
                    spellcheck="false"
                    autocomplete="off"
                    <?= $envWritable ? '' : 'disabled' ?>
                ><?= esc($envContent) ?></textarea>
            </label>
            <p class="font-ui text-[12px] leading-[1.4] text-[rgba(38,37,30,0.55)]">
                Hanya line komentar (`# ...`) atau line format `KEY=VALUE` yang diizinkan.
            </p>
            <div class="flex flex-wrap gap-2">
                <button class="<?= $buttonPrimary ?> disabled:cursor-not-allowed disabled:opacity-55" type="submit" <?= ($envExists && $envWritable) ? '' : 'disabled' ?>>Simpan .env</button>
                <button
                    class="<?= $buttonSecondary ?> disabled:cursor-not-allowed disabled:opacity-55"
                    type="submit"
                    formaction="/settings/env/restore-latest"
                    formnovalidate
                    <?= ($envExists && $envWritable && $envLatestBackupName !== '') ? '' : 'disabled' ?>
                    onclick="return confirm('Restore backup .env terbaru? Isi .env saat ini akan dioverwrite.');"
                >
                    Restore Backup Terakhir
                </button>
            </div>
        </form>
    </article>
</section>
<script>
(() => {
    const form = document.querySelector('[data-hard-reset-form]');
    if (!form) {
        return;
    }

    const phraseInput = form.querySelector('[data-hard-reset-phrase]');
    const ackInput = form.querySelector('[data-hard-reset-ack]');
    const submitButton = form.querySelector('[data-hard-reset-submit]');

    if (!phraseInput || !ackInput || !submitButton) {
        return;
    }

    const syncSubmitState = () => {
        const phraseValid = String(phraseInput.value || '').trim().toUpperCase() === 'HARD RESET';
        submitButton.disabled = !(phraseValid && ackInput.checked);
    };

    phraseInput.addEventListener('input', syncSubmitState);
    ackInput.addEventListener('change', syncSubmitState);
    syncSubmitState();
})();
</script>
<?= $this->endSection() ?>
