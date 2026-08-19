<?php

declare(strict_types=1);

/**
 * File: src/Modules/Events/Models/Event.php
 * Architectural Purpose: Active Record model representing a multi-tenant Event.
 * Package: Zero\Modules\Events\Models
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Events\Models;

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Interfaces\Model;
use Zero\Models\Traits\IsModel;

/**
 * Class Event
 *
 * Active Record model for representing events under strict multi-tenant isolation.
 */
class Event implements Model
{
    use IsModel;

    protected static string $tableName = 'events';
    protected static array $fillable = ['title', 'slug', 'description', 'event_date', 'location', 'status', 'featured_image'];

    public $id;
    public $site_id;
    public $title;
    public $slug;
    public $description;
    public $event_date;
    public $location;
    public $status = 'published';
    public $featured_image;
    public $created_at;
    public $updated_at;
    public $deleted_at;

    /**
     * Event constructor.
     *
     * @param array $data Raw row data
     */
    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (\property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    /**
     * Find an active event by its slug, enforcing strict multi-tenant isolation.
     *
     * @param string $slug The URL-friendly slug
     * @return Event|null Hydrated model instance or null if not found
     */
    public static function findBySlug(string $slug): ?self
    {
        $siteId = App::getCurrentSiteId();
        $stmt = DB::query("
            SELECT * FROM events 
            WHERE slug = ? 
              AND site_id = ? 
              AND deleted_at IS NULL 
            LIMIT 1
        ", [$slug, $siteId]);

        $data = $stmt->fetch();
        return $data ? new self($data) : null;
    }

    /**
     * Retrieve the fields mapping configuration for list display and form editing in the Admin panel.
     *
     * @return array
     */
    public static function getConfig(): array
    {
        return [
            'id' => ['type' => 'text', 'label' => 'ID', 'editable' => false, 'listDisplay' => false],
            'title' => ['type' => 'text', 'label' => 'Event Title', 'editable' => true, 'listDisplay' => true, 'required' => true, 'searchable' => true],
            'slug' => ['type' => 'text', 'label' => 'Slug', 'editable' => true, 'listDisplay' => true, 'required' => true, 'searchable' => true],
            'description' => ['type' => 'textarea', 'label' => 'Description', 'editable' => true, 'listDisplay' => false],
            'event_date' => ['type' => 'datetime', 'label' => 'Event Date & Time (UTC)', 'editable' => true, 'listDisplay' => true, 'required' => true],
            'location' => ['type' => 'text', 'label' => 'Location', 'editable' => true, 'listDisplay' => true, 'searchable' => true],
            'featured_image' => [
                'type' => 'image',
                'label' => 'Featured Image',
                'editable' => true,
                'required' => false,
                'listDisplay' => true,
                'helper_text' => 'The primary display thumbnail or hero image.'
            ],
            'status' => [
                'type' => 'select', 
                'label' => 'Status', 
                'editable' => true, 
                'listDisplay' => true,
                'options' => [
                    'published' => 'Published',
                    'draft' => 'Draft'
                ]
            ],
            'created_at' => ['type' => 'datetime', 'label' => 'Created At', 'editable' => false, 'listDisplay' => false],
            'updated_at' => ['type' => 'datetime', 'label' => 'Updated At', 'editable' => false, 'listDisplay' => false]
        ];
    }

    /**
     * Retrieve the visual action button label in admin records grids.
     *
     * @return string
     */
    public static function getEditLabel(): string
    {
        return 'Edit';
    }
}
