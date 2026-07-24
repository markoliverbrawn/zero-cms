<?php
// src/Modules/Shop/Views/dashboard-widget.php

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Support\I18n;
use Zero\Support\Str;

$renderWidgetKey = $renderWidgetKey ?? '';
$activeSiteId = App::getCurrentSiteId();

// 1. WIDGET: SHOP SALES CHART
if ($renderWidgetKey === 'shop_orders_chart'):
    $orderHistory = DB::query("
        SELECT DATE(created_at) as order_date, COUNT(*) as order_count, SUM(total_price) as total_sales
        FROM shop_orders 
        WHERE site_id = ? AND deleted_at IS NULL
        GROUP BY DATE(created_at)
        ORDER BY order_date DESC 
        LIMIT 10
    ", [$activeSiteId])->fetchAll();

    $orderHistory = array_reverse($orderHistory);

    $maxSales = 0;
    foreach ($orderHistory as $day) {
        if ($day['total_sales'] > $maxSales) {
            $maxSales = floatval($day['total_sales']);
        }
    }
    $maxSales = max(1, $maxSales);
?>
    <div class="dashboard-card chart-span-2 draggable-widget" draggable="true" data-widget="shop_orders_chart">
      <h3>
        <span class="icon-svg">
          <?php echo App::svg('zap'); ?>
        </span>
        <span>Shop Orders Over Time</span>
      </h3>
      
      <?php if (empty($orderHistory)): ?>
        <p class="text-muted">No sales data logged yet.</p>
      <?php else: ?>
        <div class="shop-chart-wrapper">
            <svg viewBox="0 0 500 200" class="shop-sales-svg">
                <line x1="40" y1="20" x2="480" y2="20" class="chart-grid-line" />
                <line x1="40" y1="75" x2="480" y2="75" class="chart-grid-line" />
                <line x1="40" y1="130" x2="480" y2="130" class="chart-grid-line" />
                <line x1="40" y1="170" x2="480" y2="170" class="chart-axis-line" />
                
                <text x="35" y="24" class="chart-scale-text">$<?php echo number_format($maxSales, 0); ?></text>
                <text x="35" y="79" class="chart-scale-text">$<?php echo number_format($maxSales * 0.65, 0); ?></text>
                <text x="35" y="134" class="chart-scale-text">$<?php echo number_format($maxSales * 0.3, 0); ?></text>
                <text x="35" y="174" class="chart-scale-text">$0</text>
                
                <?php 
                $chartWidth = 440;
                $chartHeight = 150;
                $numDays = count($orderHistory);
                $barWidth = 24;
                $gap = ($chartWidth - ($barWidth * $numDays)) / ($numDays + 1);
                
                foreach ($orderHistory as $idx => $day):
                    $sales = floatval($day['total_sales']);
                    $pct = $sales / $maxSales;
                    $barHeight = $pct * $chartHeight;
                    $x = 40 + $gap + ($idx * ($barWidth + $gap));
                    $y = 170 - $barHeight;
                    $dateLabel = Str::escape(I18n::localizeDateTime($day['order_date'], 'd M'));
                ?>
                    <g class="chart-bar-group">
                        <rect x="<?php echo $x; ?>" y="<?php echo $y; ?>" width="<?php echo $barWidth; ?>" height="<?php echo $barHeight; ?>" class="chart-rect-bar" rx="4" />
                        <text x="<?php echo $x + ($barWidth / 2); ?>" y="<?php echo $y - 8; ?>" class="chart-tooltip-text">$<?php echo number_format($sales, 0); ?></text>
                        <text x="<?php echo $x + ($barWidth / 2); ?>" y="186" class="chart-xaxis-text"><?php echo $dateLabel; ?></text>
                    </g>
                <?php endforeach; ?>
            </svg>
        </div>
      <?php endif; ?>
    </div>
<?php endif; ?>

<!-- 2. WIDGET: SHOP SALES BY CATEGORY -->
<?php if ($renderWidgetKey === 'shop_category_pie'):
    $categoryStats = DB::query("
        SELECT sc.title as category_name, SUM(soi.quantity) as items_sold
        FROM shop_order_items soi
        JOIN shop_products sp ON soi.product_id = sp.id
        JOIN shop_categories sc ON sp.category_id = sc.id
        WHERE soi.site_id = ? AND soi.deleted_at IS NULL
        GROUP BY sc.id
    ", [$activeSiteId])->fetchAll();

    $totalItemsSold = 0;
    foreach ($categoryStats as $row) {
        $totalItemsSold += intval($row['items_sold']);
    }
    $totalItemsSold = max(1, $totalItemsSold);

    $colors = [
        'Apparel' => '#38bdf8',
        'Utility' => '#10b981',
        'Vessels' => '#f59e0b'
    ];
    $defaultColorPool = ['#38bdf8', '#10b981', '#f59e0b', '#a855f7', '#ec4899'];
?>
    <div class="dashboard-card chart-span-2 draggable-widget" draggable="true" data-widget="shop_category_pie">
      <h3>
        <span class="icon-svg">
          <?php echo App::svg('book-open'); ?>
        </span>
        <span>Sales by Category</span>
      </h3>
      
      <?php if (empty($categoryStats)): ?>
        <p class="text-muted">No category sales logged yet.</p>
      <?php else: ?>
        <div class="shop-pie-wrapper">
            <div class="shop-pie-svg-container">
                <svg viewBox="0 0 160 160" class="shop-pie-svg">
                    <?php
                    // Circumference for radius r=40 is 251.32741
                    $circumference = 251.32741;
                    $currentOffset = 0;
                    
                    foreach ($categoryStats as $idx => $day):
                        $name = $day['category_name'];
                        $color = $colors[$name] ?? $defaultColorPool[$idx % count($defaultColorPool)];
                        $pct = $day['items_sold'] / $totalItemsSold;
                        $dashLength = $pct * $circumference;
                        $dashOffset = $currentOffset;
                        
                        $currentOffset -= $dashLength;
                    ?>
                        <circle cx="80" cy="80" r="40" 
                                fill="transparent" 
                                stroke="<?php echo $color; ?>" 
                                stroke-width="80" 
                                stroke-dasharray="<?php echo $dashLength; ?> <?php echo $circumference; ?>" 
                                stroke-dashoffset="<?php echo $dashOffset; ?>" 
                                class="shop-pie-circle"
                                transform="rotate(-90 80 80)" />
                    <?php endforeach; ?>
                </svg>
            </div>
            
            <div class="shop-pie-legend">
                <?php foreach ($categoryStats as $idx => $day):
                    $name = $day['category_name'];
                    $dotClass = 'dot-' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name));
                    $pct = ($day['items_sold'] / $totalItemsSold) * 100;
                ?>
                    <div class="shop-pie-legend-item">
                        <div class="shop-pie-legend-label">
                            <span class="shop-pie-legend-dot <?php echo $dotClass; ?>"></span>
                            <span><?php echo Str::escape($name); ?></span>
                        </div>
                        <span class="shop-pie-legend-value"><?php echo number_format($pct, 1); ?>%</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
      <?php endif; ?>
    </div>
<?php endif; ?>

<!-- 3. WIDGET: RECENT SHOP ORDERS -->
<?php if ($renderWidgetKey === 'recent_orders'):
    $recentOrders = DB::query("SELECT * FROM shop_orders WHERE site_id = ? AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 5", [$activeSiteId])->fetchAll();
?>
    <div class="dashboard-card draggable-widget" draggable="true" data-widget="recent_orders">
      <h3>
        <span class="icon-svg">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="9" cy="21" r="1"></circle>
            <circle cx="20" cy="21" r="1"></circle>
            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
          </svg>
        </span>
        <span>Recent Shop Orders</span>
      </h3>
      <?php if (empty($recentOrders)): ?>
        <p class="text-muted">No sales transactions logged yet.</p>
      <?php else: ?>
        <ul class="dashboard-list-items">
          <?php foreach ($recentOrders as $ord): ?>
            <li>
              <div>
                <a href="/admin/edit/orders/<?php echo $ord['id']; ?>" class="dashboard-order-customer-link" title="Customer: <?php echo Str::escape($ord['customer_name'] ?? ''); ?>">
                  <?php echo Str::escape($ord['customer_name'] ?? ''); ?>
                </a>
                <span class="dashboard-order-badge status-<?php echo strtolower($ord['status'] ?? ''); ?>">
                    <?php echo Str::escape($ord['status'] ?? ''); ?>
                </span>
                <div class="dashboard-order-date"><?php echo Str::escape(I18n::localizeDateTime($ord['created_at'], 'M d, H:i')); ?></div>
              </div>
              <span class="dashboard-order-price">
                $<?php echo number_format($ord['total_price'], 2); ?>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
<?php endif; ?>
