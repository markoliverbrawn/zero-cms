<?php
use Zero\Support\Str;
// src/Views/themes/default/partials/pagination.php
// Unified, highly-scalable sliding window pagination partial
?>
<nav class="unified-pagination-wrapper">
    <!-- Prev Link -->
    <?php if ($currentPage > 1): ?>
        <a href="<?php echo Str::escape($buildUrl($currentPage - 1)); ?>" class="pagination-btn page-nav-prev">Prev</a>
    <?php else: ?>
        <span class="pagination-btn page-nav-prev disabled">Prev</span>
    <?php endif; ?>

    <!-- First Page Anchor -->
    <?php if ($showFirst): ?>
        <a href="<?php echo Str::escape($buildUrl(1)); ?>" class="pagination-btn">1</a>
        <?php if ($startPage > 2): ?>
            <span class="pagination-ellipsis">...</span>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Sliding Page Numbers -->
    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
        <?php if ($i === $currentPage): ?>
            <span class="pagination-btn active"><?php echo $i; ?></span>
        <?php else: ?>
            <a href="<?php echo Str::escape($buildUrl($i)); ?>" class="pagination-btn"><?php echo $i; ?></a>
        <?php endif; ?>
    <?php endfor; ?>

    <!-- Last Page Anchor -->
    <?php if ($showLast): ?>
        <?php if ($endPage < $totalPages - 1): ?>
            <span class="pagination-ellipsis">...</span>
        <?php endif; ?>
        <a href="<?php echo Str::escape($buildUrl($totalPages)); ?>" class="pagination-btn"><?php echo $totalPages; ?></a>
    <?php endif; ?>

    <!-- Next Link -->
    <?php if ($currentPage < $totalPages): ?>
        <a href="<?php echo Str::escape($buildUrl($currentPage + 1)); ?>" class="pagination-btn page-nav-next">Next</a>
    <?php else: ?>
        <span class="pagination-btn page-nav-next disabled">Next</span>
    <?php endif; ?>
</nav>

<style>
.unified-pagination-wrapper {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 50px;
    padding-top: 20px;
    border-top: 1px solid var(--border-color, #e2e8f0);

    .pagination-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 8px 16px;
        font-size: 0.8rem;
        font-weight: 600;
        border-radius: var(--border-radius, 8px);
        border: 1px solid var(--border-color, #cbd5e1);
        background: var(--card-bg, #ffffff);
        color: var(--text-color, #0f172a);
        text-decoration: none;
        transition: all 0.15s ease;
        cursor: pointer;

        &:hover:not(.disabled) {
            border-color: var(--accent-color, #2563eb);
            color: var(--accent-color, #2563eb);
            box-shadow: 0 0 10px rgba(37, 99, 235, 0.15);
        }

        &.active {
            background: var(--accent-color, #2563eb);
            color: #ffffff;
            border-color: var(--accent-color, #2563eb);
            cursor: default;
        }

        &.disabled {
            opacity: 0.4;
            cursor: default;
            color: var(--text-muted, #94a3b8);
        }
    }

    .pagination-ellipsis {
        color: var(--text-muted, #64748b);
        padding: 0 4px;
        font-weight: bold;
        font-family: monospace;
        cursor: default;
    }
}
</style>
