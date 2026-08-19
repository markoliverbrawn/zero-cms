<?php

declare(strict_types=1);

/**
 * File: src/Modules/Events/Controllers/EventsController.php
 * Architectural Purpose: Public list view controller for the Events module.
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
 * Public controller rendering the list of upcoming tenant events.
 */
class EventsController implements Controller
{
    /**
     * Handles display listing of all active multi-tenant events.
     *
     * @param mixed $param Context parameter
     * @return void
     */
    public function handle($param): void
    {
        $siteId = App::getCurrentSiteId();
        
        // Retrieve published events securely isolated by site ID and sorted chronologically
        $stmt = DB::query("
            SELECT * FROM events 
            WHERE site_id = ? 
              AND status = 'published' 
              AND deleted_at IS NULL 
            ORDER BY event_date ASC
        ", [$siteId]);

        $events = [];
        while ($row = $stmt->fetch()) {
            $events[] = new Event($row);
        }

        App::render('events_index', [
            'events' => $events
        ]);
    }
}
