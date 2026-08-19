<?php
// src/Views/themes/events/events_index.php

use Zero\Support\Security;
use Zero\Support\Str;

?>
<link rel="stylesheet" href="/assets/css/themes/events/events.css?v=1.0">

<div class="events-container">
    <h1>Upcoming Events</h1>
    <p class="events-subtitle">Discover interactive workshops, cyber summits, and brutalist design meetups in our multi-tenant community.</p>

    <?php if (empty($events)): ?>
        <div class="no-events">
            <p>No upcoming events scheduled at this time. Check back soon!</p>
        </div>
    <?php else: ?>
        <div class="events-grid">
            <?php foreach ($events as $event): ?>
                <div class="event-card">
                    <div class="event-card-header">
                        <h2>
                            <a href="/events/<?php echo Str::escape($event->slug); ?>">
                                <?php echo Str::escape($event->title); ?>
                            </a>
                        </h2>
                        <span class="event-badge">
                            <?php echo \date('M d, Y', \strtotime($event->event_date)); ?>
                        </span>
                    </div>

                    <p class="event-desc">
                        <?php echo Str::escape($event->description ?? ''); ?>
                    </p>

                    <div class="event-card-footer">
                        <span class="event-meta">
                            <strong>Location:</strong> <?php echo Str::escape($event->location ?? ''); ?>
                        </span>
                        <a href="/events/<?php echo Str::escape($event->slug); ?>" class="btn-view-event">
                            View Event Details
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
