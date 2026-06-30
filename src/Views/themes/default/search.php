<?php
// src/Views/themes/default/search.php
use Zero\Support\I18n;
?>
<link rel="stylesheet" href="/assets/css/search.css?v=1.0">

<div class="search-container">
    <div class="search-header">
        <h2>Search Results</h2>
        <p class="search-meta">
            <?php if (!empty($q)): ?>
                Showing matches for "<strong><?php echo htmlspecialchars($q); ?></strong>"
            <?php else: ?>
                Please enter a search query.
            <?php endif; ?>
        </p>
    </div>

    <form method="get" action="/search" class="search-form-inline">
        <input type="text" name="q" placeholder="Search the site..." value="<?php echo htmlspecialchars($q ?? ''); ?>" required>
        <button type="submit">Search</button>
    </form>

    <?php if (!empty($results)): ?>
        <div class="search-results-list">
            <?php foreach ($results as $res): ?>
                <div class="search-result-card" onclick="window.location.href='<?php echo htmlspecialchars($res['url']); ?>'">
                    <span class="result-type-badge"><?php echo htmlspecialchars($res['type_label']); ?></span>
                    <h3>
                        <a href="<?php echo htmlspecialchars($res['url']); ?>">
                            <?php echo htmlspecialchars($res['title']); ?>
                        </a>
                    </h3>
                    <p class="result-snippet">
                        <?php
                        $preview = '';
                        $content = $res['content'] ?? '';
                        $decoded = json_decode($content, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            foreach ($decoded as $block) {
                                if (!empty($block['content'])) {
                                    $cleanText = strip_tags($block['content']);
                                    if (!empty($q) && ($pos = stripos($cleanText, $q)) !== false) {
                                        $start = max(0, $pos - 80);
                                        $length = 180;
                                        $snippet = substr($cleanText, $start, $length);
                                        $preview = ($start > 0 ? '...' : '') . $snippet . (strlen($cleanText) > ($start + $length) ? '...' : '');
                                        break;
                                    }
                                    if (empty($preview)) {
                                        $preview = substr($cleanText, 0, 180) . (strlen($cleanText) > 180 ? '...' : '');
                                    }
                                }
                            }
                        } else {
                            $cleanText = strip_tags($content);
                            if (!empty($q) && ($pos = stripos($cleanText, $q)) !== false) {
                                $start = max(0, $pos - 80);
                                $length = 180;
                                $snippet = substr($cleanText, $start, $length);
                                $preview = ($start > 0 ? '...' : '') . $snippet . (strlen($cleanText) > ($start + $length) ? '...' : '');
                            } else {
                                $preview = substr($cleanText, 0, 180) . (strlen($cleanText) > 180 ? '...' : '');
                            }
                        }
                        if (empty($preview)) {
                            $preview = 'Click to view this item and read the detailed specifications...';
                        }
                        echo htmlspecialchars($preview, ENT_QUOTES, 'UTF-8');
                        ?>
                    </p>
                    <a href="<?php echo htmlspecialchars($res['url']); ?>" class="view-link">Read More ➔</a>
                </div>
            <?php endforeach; ?>
        </div>
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
