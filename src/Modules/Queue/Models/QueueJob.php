<?php

namespace Zero\Modules\Queue\Models;

use Zero\Database\DB;
use Zero\Interfaces\Model as ModelInterface;
use Zero\Models\Traits\IsModel;

class QueueJob implements ModelInterface
{
    use IsModel {
        IsModel::save as traitSave;
    }

    protected static $tableName = 'queue_jobs';
    protected static $fillable = [
        'site_id', 
        'job_class', 
        'payload', 
        'status', 
        'attempts', 
        'reserved_at', 
        'failed_at', 
        'error_message'
    ];
    protected static $modelType = 'queue_job';

    public $id;
    public $site_id;
    public $job_class;
    public $payload;
    public $status;
    public $attempts;
    public $reserved_at;
    public $failed_at;
    public $error_message;
    public $created_at;
    public $updated_at;
    public $deleted_at;

    public function save()
    {
        // When status is manually reset to pending, clear error trace, attempts, and locks
        if ($this->status === 'pending') {
            $this->attempts = 0;
            $this->failed_at = null;
            $this->error_message = null;
            $this->reserved_at = null;
        }
        return $this->traitSave();
    }

    protected function update()
    {
        $set = [];
        $values = [];

        foreach (static::$fillable as $field) {
            if (property_exists($this, $field)) {
                $set[] = "$field = ?";
                $values[] = $this->$field;
            }
        }

        $set[] = 'updated_at = ?';
        $values[] = gmdate('Y-m-d H:i:s');
        $values[] = $this->id; // for the WHERE clause

        $sql = "UPDATE " . static::$tableName . " SET " . implode(', ', $set) . " WHERE id = ?";
        DB::query($sql, $values);
        return true;
    }

    public static function getConfig(): array
    {
        return [
            'id' => [
                'type' => 'text', 
                'label' => 'ID', 
                'editable' => false, 
                'listDisplay' => false
            ],
            'job_class' => [
                'type' => 'readonly', 
                'label' => 'Job Type', 
                'editable' => true, 
                'listDisplay' => true, 
                'searchable' => true
            ],
            'status' => [
                'type' => 'readonly', 
                'label' => 'Status', 
                'editable' => true, 
                'required' => true, 
                'listDisplay' => true, 
                'listView' => 'fields/status',
                'options' => [
                    'pending' => 'Pending',
                    'reserved' => 'Reserved',
                    'failed' => 'Failed',
                    'completed' => 'Completed'
                ]
            ],
            'attempts' => [
                'type' => 'readonly', 
                'label' => 'Attempts', 
                'editable' => true, 
                'listDisplay' => true
            ],
            'payload' => [
                'type' => 'readonly', 
                'label' => 'Payload JSON', 
                'editable' => true, 
                'listDisplay' => false,
                'listView' => 'fields/queue_payload'
            ],
            'error_message' => [
                'type' => 'readonly', 
                'label' => 'Error Backtrace', 
                'editable' => true, 
                'listDisplay' => false,
                'listView' => 'fields/queue_error'
            ],
            'created_at' => [
                'type' => 'readonly', 
                'label' => 'Dispatched At', 
                'editable' => true, 
                'listDisplay' => true
            ],
        ];
    }

    public static function getEditLabel(): string
    {
        return 'View';
    }
}
