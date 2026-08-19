<?php

declare(strict_types=1);

/**
 * File: src/Modules/Events/Controllers/EventDetailController.php
 * Architectural Purpose: Public detail view controller for the Events module.
 * Package: Zero\Modules\Events\Controllers
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Events\Controllers;

use Zero\Core\App;
use Zero\Interfaces\Controller;
use Zero\Modules\Events\Models\Event;

/**
 * Class EventDetailController
 *
 * Public controller rendering the single event details page.
 */
class EventDetailController implements Controller
{
    /**
     * Handles display details of a single event.
     *
     * @param mixed $slug Captured URL-friendly slug or matches array from the router
     * @return void
     */
    public function handle($slug): void
    {
        $resolvedSlug = \is_array($slug) ? ($slug[1] ?? '') : (string)$slug;
        $event = Event::findBySlug($resolvedSlug);

        if (!$event || $event->status !== 'published') {
            \http_response_code(404);
            echo "Event Not Found";
            exit;
        }

        App::render('events_detail', [
            'event' => $event
        ]);
    }
}
