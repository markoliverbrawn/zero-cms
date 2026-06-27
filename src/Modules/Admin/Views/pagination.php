<?php if ($pages && $pages > 1): ?>
    <p>Pages:
        <?php foreach ($range as $i): ?>
        <a href="<?php echo $_PHP_SELF;?>?page=<?php echo htmlspecialchars($i ?? '', ENT_QUOTES, "UTF-8"); ?>&type=<?php echo htmlspecialchars($type ?? '', ENT_QUOTES, "UTF-8"); ?>&q=<?php echo htmlspecialchars($q ?? '', ENT_QUOTES, "UTF-8"); ?>&sort=<?php echo htmlspecialchars($sort ?? '', ENT_QUOTES, "UTF-8"); ?>&order=<?php echo htmlspecialchars($order ?? '', ENT_QUOTES, "UTF-8"); ?>&status=<?php echo htmlspecialchars($status ?? 'active', ENT_QUOTES, "UTF-8"); ?>"><?php echo htmlspecialchars($i ?? '', ENT_QUOTES, "UTF-8"); ?></a>
        <?php endforeach; ?>
    </p>
<?php endif; ?>
