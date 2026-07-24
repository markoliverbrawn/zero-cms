<?php
use Zero\Support\Str; if ($pages && $pages > 1): ?>
    <p>Pages:
        <?php foreach ($range as $i): ?>
        <a href="<?php echo $_PHP_SELF;?>?page=<?php echo Str::escape($i ?? ''); ?>&type=<?php echo Str::escape($type ?? ''); ?>&q=<?php echo Str::escape($q ?? ''); ?>&sort=<?php echo Str::escape($sort ?? ''); ?>&order=<?php echo Str::escape($order ?? ''); ?>&status=<?php echo Str::escape($status ?? 'active'); ?>"><?php echo Str::escape($i ?? ''); ?></a>
        <?php endforeach; ?>
    </p>
<?php endif; ?>
