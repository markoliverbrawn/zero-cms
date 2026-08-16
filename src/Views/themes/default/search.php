<?php
// src/Views/themes/default/search.php

use Zero\Core\App;
use Zero\Support\I18n;
use Zero\Support\Str;
?>
<link rel="stylesheet" href="/assets/css/search.css?v=1.0">

<div class="search-container">
    <div class="search-header">
        <h2>Search Results</h2>
        <p class="search-meta">
            <?php if (!empty($q)): ?>
                Showing matches for "<strong><?php echo Str::escape($q); ?></strong>"
            <?php else: ?>
                Please enter a search query.
            <?php endif; ?>
        </p>
    </div>

    <form method="get" action="/search" class="search-form-inline">
        <?php echo App::makeFormField('text', 'q', [
            'value' => $q ?? '',
            'required' => true,
            'attributes' => ['placeholder' => 'Search the site...'],
            'showLabel' => false,
            'guessHelperTextKey' => false,
        ])->render(); ?>
        <button type="submit">Search</button>
    </form>

    <?php if (!empty($results)): ?>
        <div class="search-results-list">
            <?php foreach ($results as $res): ?>
                <div class="search-result-card" onclick="window.location.href='<?php echo Str::escape($res['url']); ?>'">
                    <span class="result-type-badge"><?php echo Str::escape($res['type_label']); ?></span>
                    <h3>
                        <a href="<?php echo Str::escape($res['url']); ?>">
                            <?php echo Str::escape($res['title']); ?>
                        </a>
                    </h3>
                    <p class="result-snippet">
                        <?php
                        $preview = '';
                        $content = $res['content'] ?? '';
                        $cleanText = strip_tags($content);
                        if (!empty($q) && ($pos = stripos($cleanText, $q)) !== false) {
                            $start = max(0, $pos - 80);
                            $length = 180;
                            $snippet = substr($cleanText, $start, $length);
                            $preview = ($start > 0 ? '...' : '') . $snippet . (strlen($cleanText) > ($start + $length) ? '...' : '');
                        } else {
                            $preview = substr($cleanText, 0, 180) . (strlen($cleanText) > 180 ? '...' : '');
                        }
                        if (empty($preview)) {
                            $preview = 'Click to view this item and read the detailed specifications...';
                        }
                        echo Str::escape($preview);
                        ?>
                    </p>
                    <a href="<?php echo Str::escape($res['url']); ?>" class="view-link">Read More ➔</a>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination Controls -->
        <?php echo App::renderPagination($pagination ?? [], '/search', $_GET); ?>
    <?php else: ?>
        <?php if (!empty($q)): ?>
            <div class="no-results">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <p>No matching search results found.</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
