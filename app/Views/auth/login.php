<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$inputClass = 'mt-1 w-full rounded-md border border-[rgba(38,37,30,0.22)] bg-surface200 px-3 py-2 font-ui text-[13px] leading-[1.45] text-[rgba(38,37,30,0.9)] shadow-[inset_0_1px_0_rgba(255,255,255,0.45)] outline-none transition-[border-color,box-shadow,background-color] duration-150 placeholder:text-[rgba(38,37,30,0.45)] focus:border-[rgba(38,37,30,0.38)] focus:bg-[#f8f7f3] focus:shadow-[rgba(0,0,0,0.1)_0_4px_12px]';
$labelClass = 'font-ui text-[12px] uppercase tracking-[0.05em] font-medium text-[rgba(38,37,30,0.62)]';
$buttonPrimary = 'inline-flex items-center justify-center gap-1.5 rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 px-3 py-2 font-display text-[13px] font-medium tracking-[0.025em] text-ink transition-colors duration-150 hover:text-danger hover:border-[rgba(38,37,30,0.2)]';
?>

<section class="mx-auto mt-6 max-w-[460px] rounded-lg border border-[rgba(38,37,30,0.1)] bg-surface400 p-4 shadow-[rgba(0,0,0,0.02)_0_0_16px,rgba(0,0,0,0.008)_0_0_8px]">
    <div class="space-y-2">
        <h1 class="text-[clamp(30px,4.2vw,42px)]">Login</h1>
        <p class="font-ui text-[13px] leading-[1.44] tracking-[0.01em] text-[rgba(38,37,30,0.55)]">Masuk ke dashboard monitoring untuk mengelola akun, subscription, dan reminder.</p>
    </div>

    <form method="post" action="/login" class="mt-4 space-y-3">
        <label class="<?= $labelClass ?>">
            Email
            <input class="<?= $inputClass ?>" type="email" name="email" required value="<?= esc(old('email', '')) ?>" placeholder="nama@domain.com">
        </label>

        <label class="<?= $labelClass ?>">
            Password
            <input class="<?= $inputClass ?>" type="password" name="password" required minlength="8" placeholder="Minimal 8 karakter">
        </label>

        <button class="<?= $buttonPrimary ?>" type="submit">Masuk</button>
    </form>

    <?php if ($allowRegister ?? false): ?>
        <p class="mt-3 font-ui text-[13px] leading-[1.44] tracking-[0.01em] text-[rgba(38,37,30,0.62)]">
            Belum ada akun admin awal? <a class="font-medium text-[rgba(38,37,30,0.82)] hover:text-danger" href="/register">Buat akun pertama</a>
        </p>
    <?php endif; ?>
</section>
<?= $this->endSection() ?>
