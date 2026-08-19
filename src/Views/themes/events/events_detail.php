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
