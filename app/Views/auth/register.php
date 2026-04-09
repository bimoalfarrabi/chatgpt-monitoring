<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$inputClass = 'mt-1 w-full rounded-md border border-[rgba(38,37,30,0.22)] bg-surface200 px-3 py-2 font-ui text-[13px] leading-[1.45] text-[rgba(38,37,30,0.9)] shadow-[inset_0_1px_0_rgba(255,255,255,0.45)] outline-none transition-[border-color,box-shadow,background-color] duration-150 placeholder:text-[rgba(38,37,30,0.45)] focus:border-[rgba(38,37,30,0.38)] focus:bg-[#f8f7f3] focus:shadow-[rgba(0,0,0,0.1)_0_4px_12px]';
$labelClass = 'font-ui text-[12px] uppercase tracking-[0.05em] font-medium text-[rgba(38,37,30,0.62)]';
$buttonPrimary = 'inline-flex items-center justify-center gap-1.5 rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 px-3 py-2 font-display text-[13px] font-medium tracking-[0.025em] text-ink transition-colors duration-150 hover:text-danger hover:border-[rgba(38,37,30,0.2)]';
?>

<section class="mx-auto mt-6 max-w-[520px] rounded-lg border border-[rgba(38,37,30,0.1)] bg-surface400 p-4 shadow-[rgba(0,0,0,0.02)_0_0_16px,rgba(0,0,0,0.008)_0_0_8px]">
    <div class="space-y-2">
        <h1 class="text-[clamp(30px,4.2vw,42px)]">Buat Akun Admin</h1>
        <p class="font-ui text-[13px] leading-[1.44] tracking-[0.01em] text-[rgba(38,37,30,0.55)]">Registrasi ini dipakai untuk membuat akun admin pertama aplikasi.</p>
    </div>

    <form method="post" action="/register" class="mt-4 space-y-3">
        <label class="<?= $labelClass ?>">
            Nama
            <input class="<?= $inputClass ?>" type="text" name="name" required value="<?= esc(old('name', '')) ?>" placeholder="Nama admin">
        </label>

        <label class="<?= $labelClass ?>">
            Username
            <input class="<?= $inputClass ?>" type="text" name="username" required value="<?= esc(old('username', '')) ?>" placeholder="huruf/angka/underscore/dash">
        </label>

        <label class="<?= $labelClass ?>">
            Email
            <input class="<?= $inputClass ?>" type="email" name="email" required value="<?= esc(old('email', '')) ?>" placeholder="nama@domain.com">
        </label>

        <div class="grid gap-3 [grid-template-columns:repeat(auto-fit,minmax(220px,1fr))]">
            <label class="<?= $labelClass ?>">
                Password
                <input class="<?= $inputClass ?>" type="password" name="password" required minlength="8" placeholder="Minimal 8 karakter">
            </label>

            <label class="<?= $labelClass ?>">
                Konfirmasi Password
                <input class="<?= $inputClass ?>" type="password" name="password_confirmation" required minlength="8" placeholder="Ulangi password">
            </label>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button class="<?= $buttonPrimary ?>" type="submit">Buat Akun</button>
            <a class="inline-flex items-center justify-center gap-1.5 rounded-md border border-[rgba(38,37,30,0.1)] bg-surface400 px-3 py-2 no-underline font-display text-[13px] font-medium tracking-[0.025em] text-[rgba(38,37,30,0.75)] transition-colors duration-150 hover:text-danger hover:border-[rgba(38,37,30,0.2)]" href="/login">Kembali ke Login</a>
        </div>
    </form>
</section>
<?= $this->endSection() ?>
