<?php
/**
 * File: src/Modules/Security/Models/AuditLog.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Security\Models
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */



namespace Zero\Modules\Security\Models;

use Zero\Interfaces\Model as ModelInterface;
use Zero\Models\Traits\IsModel;

/**
 * Class AuditLog
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class AuditLog implements ModelInterface
{
    use IsModel;

    protected static $tableName = 'audit_logs';
    protected static $modelType = null;
    protected static $fillable = ['user_id', 'action', 'object_type', 'object_id', 'meta'];

    public $id;
    public $site_id;
    public $user_id;
    public $action;
    public $object_type;
    public $object_id;
    public $meta;
    public $created_at;
    public $updated_at;
    public $deleted_at;

    /**
     * Retrieves the config attribute value.
     *
     * @return mixed Response output.
     */
    public static function getConfig(): array
    {
        return [
            'id' => ['type' => 'text', 'label' => 'ID', 'editable' => false, 'listDisplay' => false],
            'created_at' => ['type' => 'datetime', 'label' => 'Timestamp', 'editable' => false, 'listDisplay' => true],
            'user_id' => [
                'type' => 'readonly',
                'label' => 'Actor',
                'editable' => false,
                'listDisplay' => true,
                'listView' => 'fields/forum_user'
            ],
            'action' => [
                'type' => 'text',
                'label' => 'Security Action',
                'editable' => false,
                'listDisplay' => true,
                'searchable' => true,
                'listView' => 'fields/audit_log_action'
            ],
            'object_type' => ['type' => 'text', 'label' => 'Target Object', 'editable' => false, 'listDisplay' => true],
            'object_id' => ['type' => 'text', 'label' => 'Target ID', 'editable' => false, 'listDisplay' => false],
            'meta' => [
                'type' => 'textarea',
                'label' => 'Metadata Details',
                'editable' => false,
                'listDisplay' => true,
                'listView' => 'fields/audit_log_meta'
            ]
        ];
    }

    /**
     * Retrieves the edit label attribute value.
     *
     * @return string Response output.
     */
    public static function getEditLabel(): string
    {
        return 'View';
    }
}
