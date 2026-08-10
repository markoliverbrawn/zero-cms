<?php
/**
 * File: src/Modules/Security/Models/SecurityAudit.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Security\Models
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Security\Models;

use Zero\Interfaces\Model as ModelInterface;
use Zero\Models\Traits\IsModel;

/**
 * Class SecurityAudit
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class SecurityAudit implements ModelInterface
{
    use IsModel;

    protected static $tableName = 'security_audits';
    protected static $modelType = null;
    protected static $fillable = ['user_id', 'score', 'environment', 'telemetry', 'report'];

    public $id;
    public $site_id;
    public $user_id;
    public $score;
    public $environment;
    public $telemetry;
    public $report;
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
                'label' => 'Auditor',
                'editable' => false,
                'listDisplay' => true,
                'listView' => 'fields/forum_user'
            ],
            'score' => ['type' => 'number', 'label' => 'Audit Score', 'editable' => false, 'listDisplay' => true],
            'environment' => ['type' => 'text', 'label' => 'Environment', 'editable' => false, 'listDisplay' => true],
            'telemetry' => ['type' => 'textarea', 'label' => 'Telemetry details', 'editable' => false, 'listDisplay' => false],
            'report' => ['type' => 'textarea', 'label' => 'Markdown report', 'editable' => false, 'listDisplay' => false]
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
