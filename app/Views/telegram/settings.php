<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$cardBase = 'rounded-lg border border-[rgba(38,37,30,0.1)] p-4 shadow-[rgba(0,0,0,0.02)_0_0_16px,rgba(0,0,0,0.008)_0_0_8px] transition-[box-shadow,border-color] duration-200 hover:border-[rgba(38,37,30,0.2)] hover:shadow-[rgba(0,0,0,0.14)_0_28px_70px,rgba(0,0,0,0.1)_0_14px_32px]';

$inputClass = 'mt-1 w-full rounded-md border border-[rgba(38,37,30,0.1)] bg-surface400 px-3 py-2 font-ui text-[13px] leading-[1.45] text-[rgba(38,37,30,0.82)] outline-none transition-[border-color,box-shadow] duration-150 focus:border-[rgba(38,37,30,0.2)] focus:shadow-[rgba(0,0,0,0.1)_0_4px_12px]';
$labelClass = 'font-ui text-[12px] uppercase tracking-[0.05em] font-medium text-[rgba(38,37,30,0.62)]';

$buttonPrimary = 'inline-flex items-center justify-center gap-1.5 rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 px-3 py-2 font-display text-[13px] font-medium tracking-[0.025em] text-ink transition-colors duration-150 hover:text-danger hover:border-[rgba(38,37,30,0.2)]';
$buttonSecondary = 'inline-flex items-center justify-center gap-1.5 rounded-md border border-[rgba(38,37,30,0.1)] bg-surface400 px-3 py-2 font-display text-[13px] font-medium tracking-[0.025em] text-[rgba(38,37,30,0.75)] transition-colors duration-150 hover:text-danger hover:border-[rgba(38,37,30,0.2)]';

$timelineItems = [
    ['label' => 'Baca Data', 'desc' => 'Ambil daftar subscription yang akan habis atau sudah expired.', 'dot' => '#9fbbe0'],
    ['label' => 'Saring', 'desc' => 'Lewati reminder yang sudah pernah dikirim di hari yang sama.', 'dot' => '#9fc9a2'],
    ['label' => 'Kirim', 'desc' => 'Kirim notifikasi ke Telegram dan simpan ke reminder_logs.', 'dot' => '#c0a8dd'],
];
?>

<section class="space-y-2">
    <h1>Pengaturan Telegram</h1>
    <p class="max-w-[760px] font-serif text-[clamp(18px,1.35vw,20px)] leading-[1.45] text-[rgba(38,37,30,0.64)]">Atur bot untuk pengiriman reminder subscription dan lakukan pengujian kirim pesan langsung dari panel ini.</p>
</section>

<section class="mt-6 grid gap-3.5 [grid-template-columns:repeat(auto-fit,minmax(320px,1fr))]">
    <article class="<?= $cardBase ?> bg-surface400 space-y-3">
        <div class="space-y-1">
            <h3>Konfigurasi Bot</h3>
            <p class="font-ui text-[13px] leading-[1.44] tracking-[0.01em] text-[rgba(38,37,30,0.55)]">Kosongkan token/chat ID jika ingin menonaktifkan sementara tanpa menghapus data lama.</p>
        </div>

        <form method="post" action="/telegram/settings" class="space-y-3">
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
                <button class="<?= $buttonSecondary ?>" formaction="/telegram/test" type="submit">Kirim Test Message</button>
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
</section>
<?= $this->endSection() ?>
