<?php
// src/Views/themes/events/events_detail.php

use Zero\Support\Security;
use Zero\Support\Str;

?>
<link rel="stylesheet" href="/assets/css/themes/events/events.css?v=1.0">

<div class="event-detail-container">
    <a href="/events" class="back-link">
        &larr; Back to all events
    </a>

    <article class="event-detail-article">
        <?php if (!empty($event->featured_image_path)): ?>
            <?php 
            $focusY = isset($event->featured_image_focus_y) ? (int)$event->featured_image_focus_y : 50;
            ?>
            <div class="event-detail-hero" style="--focus-y: <?php echo $focusY; ?>%;">
                <img src="<?php echo Str::escape($event->featured_image_path); ?>" alt="<?php echo Str::escape($event->title); ?>">
            </div>
        <?php endif; ?>

        <header class="event-detail-header">
            <div>
                <span class="event-badge">
                    Upcoming Event
                </span>
            </div>
            <h1><?php echo Str::escape($event->title); ?></h1>
        </header>

        <section class="event-meta-banner">
            <div class="meta-block">
                <span>Date & Time</span>
                <strong><?php echo \date('l, F d, Y @ h:i A', \strtotime($event->event_date)); ?> UTC</strong>
            </div>
            <div class="meta-block">
                <span>Venue / Location</span>
                <strong><?php echo Str::escape($event->location ?? 'Online / Decentralized'); ?></strong>
            </div>
        </section>

        <section class="event-body-content">
            <?php echo Security::sanitizeHtml($event->description ?? ''); ?>
        </section>
    </article>
</div>
