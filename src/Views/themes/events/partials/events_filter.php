<?php
// src/Views/themes/events/partials/events_filter.php

use Zero\Support\Str;

$currentQuery = isset($q) ? $q : '';
$currentTime = isset($time) ? $time : 'upcoming';
$currentLocationType = isset($locationType) ? $locationType : 'all';
?>
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
