<?php
$workspaceHistory = is_array($workspaceHistory ?? null) ? $workspaceHistory : [];
$pagination = is_array($pagination ?? null) ? $pagination : [];

$currentPage = max(1, (int) ($pagination['current_page'] ?? 1));
$perPage = max(1, (int) ($pagination['per_page'] ?? 10));
$totalItems = max(0, (int) ($pagination['total_items'] ?? count($workspaceHistory)));
$totalPages = max(1, (int) ($pagination['total_pages'] ?? 1));
$hasPrev = (bool) ($pagination['has_prev'] ?? ($currentPage > 1));
$hasNext = (bool) ($pagination['has_next'] ?? ($currentPage < $totalPages));
$fromItem = $totalItems === 0 ? 0 : (($currentPage - 1) * $perPage) + 1;
$toItem = $totalItems === 0 ? 0 : min($totalItems, $fromItem + count($workspaceHistory) - 1);

$statusClasses = [
    'active' => 'inline-flex items-center gap-1.5 rounded-full px-2 py-[3px] font-display text-[14px] leading-[1.5] border border-[color-mix(in_srgb,#1f8a65_35%,transparent_65%)] text-[#165a44] bg-[color-mix(in_srgb,#1f8a65_18%,#f2f1ed_82%)]',
    'expiring_soon' => 'inline-flex items-center gap-1.5 rounded-full px-2 py-[3px] font-display text-[14px] leading-[1.5] border border-[color-mix(in_srgb,#c08532_40%,transparent_60%)] text-[#8f4d10] bg-[color-mix(in_srgb,#c08532_22%,#f2f1ed_78%)]',
    'expired' => 'inline-flex items-center gap-1.5 rounded-full px-2 py-[3px] font-display text-[14px] leading-[1.5] border border-[color-mix(in_srgb,#cf2d56_40%,transparent_60%)] text-[#8f1f3c] bg-[color-mix(in_srgb,#cf2d56_18%,#f2f1ed_82%)]',
    'deactivated' => 'inline-flex items-center gap-1.5 rounded-full px-2 py-[3px] font-display text-[14px] leading-[1.5] border border-[color-mix(in_srgb,#444444_34%,transparent_66%)] text-[#2d2d2d] bg-[color-mix(in_srgb,#444444_14%,#f2f1ed_86%)]',
];
$paginationButtonClass = 'inline-flex items-center justify-center gap-1.5 rounded-md border border-[rgba(38,37,30,0.1)] bg-surface300 px-3 py-2 font-display text-[13px] font-medium tracking-[0.025em] text-ink transition-colors duration-150 hover:text-danger hover:border-[rgba(38,37,30,0.2)] disabled:cursor-not-allowed disabled:opacity-45 disabled:hover:text-ink disabled:hover:border-[rgba(38,37,30,0.1)]';
?>
<div class="overflow-visible">
    <table class="data-table-cards">
        <thead>
        <tr>
            <th>Workspace Seller (Pro)</th>
            <th>Workspace Personal (Free)</th>
            <th>Jenis Akun Pro</th>
            <th>Status Workspace</th>
            <th>Status Lifecycle</th>
            <th>Tanggal Langganan</th>
            <th>Berakhir (Otomatis)</th>
            <th>Dibuat Pada</th>
        </tr>
        </thead>
        <tbody>
        <?php if ($workspaceHistory === []): ?>
            <tr>
                <td colspan="8" class="font-ui text-[13px] text-[rgba(38,37,30,0.55)]">Belum ada histori workspace.</td>
            </tr>
        <?php endif; ?>

        <?php foreach ($workspaceHistory as $row): ?>
            <?php
            $historyStatusClass = $statusClasses[$row['status']] ?? $statusClasses['active'];
            $historyProType = (string) ($row['pro_account_type'] ?? '');
            $historyProTypeLabel = $historyProType === 'personal_invite'
                ? 'Invite Akun Pribadi'
                : ($historyProType === 'seller_account' ? 'Akun dari Seller' : '-');
            ?>
            <tr>
                <td><?= esc((string) ($row['workspace_name'] ?? '-')) ?></td>
                <td><?= esc($historyProType === 'personal_invite' ? ((string) ($row['personal_workspace_name'] ?? '-')) : '-') ?></td>
                <td><?= esc($historyProTypeLabel) ?></td>
                <td><?= ((int) ($row['is_workspace_deactivated'] ?? 0)) === 1 ? 'Deactivated' : 'Aktif' ?></td>
                <td><span class="<?= $historyStatusClass ?>"><?= esc(\App\Services\SubscriptionStatusService::humanize((string) ($row['status'] ?? 'active'))) ?></span></td>
                <td class="font-mono text-[11px] leading-[1.55] tracking-[-0.01em] text-[rgba(38,37,30,0.76)]"><?= esc((string) ($row['subscribed_at'] ?? '-')) ?></td>
                <td class="font-mono text-[11px] leading-[1.55] tracking-[-0.01em] text-[rgba(38,37,30,0.76)]"><?= esc((string) ($row['expired_at'] ?? '-')) ?></td>
                <td class="font-mono text-[11px] leading-[1.55] tracking-[-0.01em] text-[rgba(38,37,30,0.76)]"><?= esc((string) ($row['created_at'] ?? '-')) ?></td>
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
