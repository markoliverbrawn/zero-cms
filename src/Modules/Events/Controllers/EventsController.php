<?php

declare(strict_types=1);

/**
 * File: src/Modules/Events/Controllers/EventsController.php
 * Architectural Purpose: Public list view controller with search & filter enhancements.
 * Package: Zero\Modules\Events\Controllers
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Events\Controllers;

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Interfaces\Controller;
use Zero\Modules\Events\Models\Event;

/**
 * Class EventsController
 *
 * Public controller rendering the list of tenant events with full text search and timeline/location filters.
 */
class EventsController implements Controller
{
    /**
     * Handles display listing of all active multi-tenant events, supporting dynamic filters.
     *
     * @param mixed $param Context parameter
     * @return void
     */
    public function handle($param): void
    {
        $siteId = App::getCurrentSiteId();
        
        $sql = "SELECT * FROM events WHERE site_id = ? AND status = 'published' AND deleted_at IS NULL";
        $params = [$siteId];

        // 1. Text Search Filter (Matches Title, Location, and Description)
        $q = isset($_GET['q']) ? \trim((string)$_GET['q']) : '';
        if ($q !== '') {
            $sql .= " AND (title LIKE ? OR description LIKE ? OR location LIKE ?)";
            $params[] = "%{$q}%";
            $params[] = "%{$q}%";
            $params[] = "%{$q}%";
        }

        // 2. Chronological Timeline Filter (Upcoming, Past, or All)
        $time = isset($_GET['time']) ? \trim((string)$_GET['time']) : 'upcoming';
        $now = \gmdate('Y-m-d H:i:s');
        if ($time === 'upcoming') {
            $sql .= " AND event_date >= ?";
            $params[] = $now;
        } elseif ($time === 'past') {
            $sql .= " AND event_date < ?";
            $params[] = $now;
        }

        // 3. Location Type Filter (Matches online indicators like zoom/link/online or negates them)
        $locationType = isset($_GET['location_type']) ? \trim((string)$_GET['location_type']) : 'all';
        if ($locationType === 'online') {
            $sql .= " AND (location LIKE ? OR location LIKE ? OR location LIKE ? OR location LIKE ?)";
            $params[] = '%online%';
            $params[] = '%zoom%';
            $params[] = '%link%';
            $params[] = '%decentralized%';
        } elseif ($locationType === 'physical') {
            $sql .= " AND (location NOT LIKE ? AND location NOT LIKE ? AND location NOT LIKE ? AND location NOT LIKE ?)";
            $params[] = '%online%';
            $params[] = '%zoom%';
            $params[] = '%link%';
            $params[] = '%decentralized%';
        }

        // Apply chronological ordering based on timeline filter
        if ($time === 'past') {
            $sql .= " ORDER BY event_date DESC"; // Newest completed events first
        } else {
            $sql .= " ORDER BY event_date ASC";  // Soonest upcoming events first
        }

        $stmt = DB::query($sql, $params);
        $events = [];
        while ($row = $stmt->fetch()) {
            $events[] = new Event($row);
        }

        App::render('events_index', [
            'events' => $events,
            'q' => $q,
            'time' => $time,
            'locationType' => $locationType
        ]);
    }
}
