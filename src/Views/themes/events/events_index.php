<?php
// src/Views/themes/events/events_index.php

use Zero\Support\Security;
use Zero\Support\Str;

$currentQuery = isset($q) ? $q : '';
$currentTime = isset($time) ? $time : 'upcoming';
$currentLocationType = isset($locationType) ? $locationType : 'all';
?>
<link rel="stylesheet" href="/assets/css/themes/events/events.css?v=1.1">

<div class="events-container">
    <h1>Upcoming Events</h1>
    <p class="events-subtitle">Discover interactive workshops, cyber summits, and brutalist design meetups in our multi-tenant community.</p>

    <!-- Interactive Search and Filter Bar -->
    <?php include APPLICATION_ROOT . '/src/Views/themes/events/partials/events_filter.php'; ?>

    <?php if (empty($events)): ?>
        <div class="no-events">
            <p>No matching events found. Try refining your search query or adjusting your filters!</p>
        </div>
    <?php else: ?>
        <div class="events-grid">
            <?php foreach ($events as $event): ?>
                <?php 
                $isPast = \strtotime($event->event_date) < \time();
                ?>
                <div class="event-card <?php echo $isPast ? 'is-past-event' : ''; ?>">
                    <div class="event-card-header">
                        <h2>
                            <a href="/events/<?php echo Str::escape($event->slug); ?>">
                                <?php echo Str::escape($event->title); ?>
                            </a>
                        </h2>
                        <span class="event-badge <?php echo $isPast ? 'badge-past' : ''; ?>">
                            <?php echo \date('M d, Y', \strtotime($event->event_date)); ?>
                            <?php echo $isPast ? ' (Past)' : ''; ?>
                        </span>
                    </div>

                    <p class="event-desc">
                        <?php echo Str::escape(Str::truncate(\strip_tags($event->description ?? ''), 180)); ?>
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
