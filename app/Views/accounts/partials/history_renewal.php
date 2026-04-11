<?php
$renewalHistory = is_array($renewalHistory ?? null) ? $renewalHistory : [];
$pagination = is_array($pagination ?? null) ? $pagination : [];

$currentPage = max(1, (int) ($pagination['current_page'] ?? 1));
$perPage = max(1, (int) ($pagination['per_page'] ?? 10));
$totalItems = max(0, (int) ($pagination['total_items'] ?? count($renewalHistory)));
$totalPages = max(1, (int) ($pagination['total_pages'] ?? 1));
$hasPrev = (bool) ($pagination['has_prev'] ?? ($currentPage > 1));
$hasNext = (bool) ($pagination['has_next'] ?? ($currentPage < $totalPages));
$fromItem = $totalItems === 0 ? 0 : (($currentPage - 1) * $perPage) + 1;
$toItem = $totalItems === 0 ? 0 : min($totalItems, $fromItem + count($renewalHistory) - 1);

$paginationButtonClass = 'inline-flex items-center justify-center gap-1.5 rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 px-3 py-2 font-display text-[13px] font-medium tracking-[0.025em] text-ink transition-colors duration-150 hover:text-danger hover:border-[rgba(38,37,30,0.2)] disabled:cursor-not-allowed disabled:opacity-45 disabled:hover:text-ink disabled:hover:border-[rgba(38,37,30,0.1)]';
?>
<div class="overflow-visible">
    <table class="data-table-cards">
        <thead>
        <tr>
            <th>Workspace Seller (Pro)</th>
            <th>Workspace Personal (Free)</th>
            <th>Tipe Subscription</th>
            <th>Expired Lama</th>
            <th>Expired Baru</th>
            <th>Diperpanjang Pada</th>
        </tr>
        </thead>
        <tbody>
        <?php if ($renewalHistory === []): ?>
            <tr>
                <td colspan="6" class="font-ui text-[13px] text-[rgba(38,37,30,0.55)]">Belum ada riwayat perpanjangan subscription.</td>
            </tr>
        <?php endif; ?>

        <?php foreach ($renewalHistory as $row): ?>
            <tr>
                <td><?= esc((string) ($row['workspace_name'] ?? '-')) ?></td>
                <td><?= esc(((string) ($row['pro_account_type'] ?? '')) === 'personal_invite' ? ((string) ($row['personal_workspace_name'] ?? '-')) : '-') ?></td>
                <td><?= esc((string) ($row['subscription_type'] ?? '-')) ?></td>
                <td class="font-mono text-[11px] leading-[1.55] tracking-[-0.01em] text-[rgba(38,37,30,0.76)]"><?= esc((string) ($row['old_expired_at'] ?? '-')) ?></td>
                <td class="font-mono text-[11px] leading-[1.55] tracking-[-0.01em] text-[rgba(38,37,30,0.76)]"><?= esc((string) ($row['new_expired_at'] ?? '-')) ?></td>
                <td class="font-mono text-[11px] leading-[1.55] tracking-[-0.01em] text-[rgba(38,37,30,0.76)]"><?= esc((string) ($row['renewed_at'] ?? '-')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($totalItems > 0): ?>
    <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
        <p class="font-ui text-[12px] leading-[1.45] tracking-[0.01em] text-[rgba(38,37,30,0.55)]">
            Menampilkan <?= esc((string) $fromItem) ?>-<?= esc((string) $toItem) ?> dari <?= esc((string) $totalItems) ?> data.
        </p>
        <?php if ($totalPages > 1): ?>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="<?= $paginationButtonClass ?>" data-history-page="<?= esc((string) max(1, $currentPage - 1)) ?>" <?= $hasPrev ? '' : 'disabled' ?>>
                    Sebelumnya
                </button>
                <span class="font-ui text-[12px] leading-[1.45] tracking-[0.01em] text-[rgba(38,37,30,0.62)]">
                    Halaman <?= esc((string) $currentPage) ?> / <?= esc((string) $totalPages) ?>
                </span>
                <button type="button" class="<?= $paginationButtonClass ?>" data-history-page="<?= esc((string) min($totalPages, $currentPage + 1)) ?>" <?= $hasNext ? '' : 'disabled' ?>>
                    Berikutnya
                </button>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
