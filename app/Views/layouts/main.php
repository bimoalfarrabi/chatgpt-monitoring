<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle ?? 'ChatGPT Monitoring') ?></title>
    <?= vite_tags('resources/js/app.js') ?>
</head>
<body>
<?php
$path = service('uri')->getPath();
$isLoggedIn = (bool) session('logged_in');
$userName = (string) (session('user_name') ?? '');
?>

<header class="sticky top-0 z-30 border-b border-[rgba(38,37,30,0.1)] backdrop-blur bg-[color-mix(in_srgb,#f2f1ed_86%,white_14%)]">
    <div class="mx-auto max-w-[1200px] px-5 py-3 flex items-center justify-between gap-2.5 flex-wrap max-[1279px]:px-[18px] max-[900px]:px-[14px] max-[600px]:items-start max-[600px]:flex-col max-[600px]:gap-2">
        <a class="inline-flex items-center gap-2 no-underline font-display text-[22px] leading-[1.15] tracking-[-0.25px] font-medium text-ink max-[768px]:text-[20px]" href="<?= $isLoggedIn ? '/' : '/login' ?>">
            <span class="h-[9px] w-[9px] rounded-full bg-accent shadow-[0_0_0_4px_rgba(245,78,0,0.12)]"></span>
            ChatGPT Monitoring
        </a>

        <?php if ($isLoggedIn): ?>
            <nav class="flex items-center gap-2 flex-wrap max-[768px]:gap-1.5">
                <a href="/" class="<?= in_array($path, ['', '/'], true)
                    ? 'no-underline rounded-full border border-[rgba(38,37,30,0.1)] bg-surface500 px-[10px] py-[3px] font-display text-[13px] font-medium tracking-[0.035em] leading-[1.5] text-ink'
                    : 'no-underline rounded-full border border-[rgba(38,37,30,0.1)] bg-surface400 px-[10px] py-[3px] font-display text-[13px] font-medium tracking-[0.035em] leading-[1.5] text-[rgba(38,37,30,0.8)] hover:text-danger hover:border-[rgba(38,37,30,0.2)]' ?> max-[768px]:px-[9px]">Dasbor</a>
                <a href="/accounts" class="<?= str_starts_with($path, 'accounts')
                    ? 'no-underline rounded-full border border-[rgba(38,37,30,0.1)] bg-surface500 px-[10px] py-[3px] font-display text-[13px] font-medium tracking-[0.035em] leading-[1.5] text-ink'
                    : 'no-underline rounded-full border border-[rgba(38,37,30,0.1)] bg-surface400 px-[10px] py-[3px] font-display text-[13px] font-medium tracking-[0.035em] leading-[1.5] text-[rgba(38,37,30,0.8)] hover:text-danger hover:border-[rgba(38,37,30,0.2)]' ?> max-[768px]:px-[9px]">Akun</a>

                <details class="relative">
                    <summary class="list-none [&::-webkit-details-marker]:hidden inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-[rgba(38,37,30,0.1)] bg-surface300 px-[10px] py-[3px] font-display text-[13px] font-medium tracking-[0.025em] text-[rgba(38,37,30,0.7)] transition-colors duration-150 hover:text-danger hover:border-[rgba(38,37,30,0.2)]">
                        <?= esc($userName !== '' ? $userName : 'User') ?>
                        <span class="text-[11px] leading-none">▾</span>
                    </summary>

                    <div class="absolute right-0 mt-1 min-w-[220px] rounded-md border border-[rgba(38,37,30,0.1)] bg-surface400 p-1.5 shadow-[rgba(0,0,0,0.14)_0_28px_70px,rgba(0,0,0,0.1)_0_14px_32px]">
                        <a href="/profile" class="block rounded-md px-2.5 py-2 no-underline font-ui text-[13px] leading-[1.44] text-[rgba(38,37,30,0.82)] hover:bg-surface300">Profile</a>
                        <a href="/telegram" class="block rounded-md px-2.5 py-2 no-underline font-ui text-[13px] leading-[1.44] text-[rgba(38,37,30,0.82)] hover:bg-surface300">Pengaturan Telegram</a>
                        <form method="post" action="/logout" class="mt-1 border-t border-[rgba(38,37,30,0.1)] pt-1">
                            <button class="block w-full rounded-md px-2.5 py-2 text-left font-ui text-[13px] leading-[1.44] text-[rgba(38,37,30,0.82)] hover:bg-surface300 hover:text-danger" type="submit">Logout</button>
                        </form>
                    </div>
                </details>
            </nav>
        <?php else: ?>
            <nav class="flex items-center gap-2 flex-wrap max-[768px]:gap-1.5">
                <a href="/login" class="<?= str_starts_with($path, 'login')
                    ? 'no-underline rounded-full border border-[rgba(38,37,30,0.1)] bg-surface500 px-[10px] py-[3px] font-display text-[13px] font-medium tracking-[0.035em] leading-[1.5] text-ink'
                    : 'no-underline rounded-full border border-[rgba(38,37,30,0.1)] bg-surface400 px-[10px] py-[3px] font-display text-[13px] font-medium tracking-[0.035em] leading-[1.5] text-[rgba(38,37,30,0.8)] hover:text-danger hover:border-[rgba(38,37,30,0.2)]' ?>">Login</a>
                <a href="/register" class="<?= str_starts_with($path, 'register')
                    ? 'no-underline rounded-full border border-[rgba(38,37,30,0.1)] bg-surface500 px-[10px] py-[3px] font-display text-[13px] font-medium tracking-[0.035em] leading-[1.5] text-ink'
                    : 'no-underline rounded-full border border-[rgba(38,37,30,0.1)] bg-surface400 px-[10px] py-[3px] font-display text-[13px] font-medium tracking-[0.035em] leading-[1.5] text-[rgba(38,37,30,0.8)] hover:text-danger hover:border-[rgba(38,37,30,0.2)]' ?>">Register</a>
            </nav>
        <?php endif; ?>
    </div>
</header>

<main class="mx-auto max-w-[1200px] pt-6 px-5 pb-14 max-[1279px]:px-[18px] max-[1279px]:pb-[46px] max-[900px]:px-[14px] max-[900px]:pb-[38px] max-[600px]:px-[12px] max-[600px]:pb-[28px]">
    <?php if (session()->getFlashdata('success')): ?>
        <div class="mt-4 rounded-lg border border-[color-mix(in_srgb,#1f8a65_30%,transparent_70%)] bg-[color-mix(in_srgb,#1f8a65_14%,#f2f1ed_86%)] px-3 py-2.5 font-ui text-[13px] leading-[1.45] font-medium text-[#165a44]">
            <?= esc(session()->getFlashdata('success')) ?>
        </div>
    <?php endif; ?>

    <?php if (session()->getFlashdata('error')): ?>
        <div class="mt-4 rounded-lg border border-[color-mix(in_srgb,#cf2d56_30%,transparent_70%)] bg-[color-mix(in_srgb,#cf2d56_12%,#f2f1ed_88%)] px-3 py-2.5 font-ui text-[13px] leading-[1.45] font-medium text-[#8f1f3c]">
            <?= esc(session()->getFlashdata('error')) ?>
        </div>
    <?php endif; ?>

    <?= $this->renderSection('content') ?>
</main>
</body>
</html>
