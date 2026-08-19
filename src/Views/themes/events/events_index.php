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
    <div class="events-filter-bar">
        <form method="get" action="/events">
            <div class="filter-group">
                <label for="search-input">Search Events</label>
                <input type="text" id="search-input" name="q" value="<?php echo Str::escape($currentQuery); ?>" placeholder="Search titles, locations, descriptions...">
            </div>

            <div class="filter-group">
                <label for="time-select">Timeline</label>
                <select id="time-select" name="time">
                    <option value="upcoming" <?php echo $currentTime === 'upcoming' ? 'selected' : ''; ?>>Upcoming Events</option>
                    <option value="past" <?php echo $currentTime === 'past' ? 'selected' : ''; ?>>Past Events</option>
                    <option value="all" <?php echo $currentTime === 'all' ? 'selected' : ''; ?>>All Events</option>
                </select>
            </div>

            <div class="filter-group">
                <label for="location-select">Location Type</label>
                <select id="location-select" name="location_type">
                    <option value="all" <?php echo $currentLocationType === 'all' ? 'selected' : ''; ?>>All Locations</option>
                    <option value="online" <?php echo $currentLocationType === 'online' ? 'selected' : ''; ?>>Online / Digital</option>
                    <option value="physical" <?php echo $currentLocationType === 'physical' ? 'selected' : ''; ?>>Physical / On-Site</option>
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit">Filter</button>
                <a href="/events" class="btn-reset">Reset</a>
            </div>
        </form>
    </div>

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
