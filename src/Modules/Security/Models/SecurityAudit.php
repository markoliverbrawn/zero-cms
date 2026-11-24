<?php

declare(strict_types=1);

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
 * Active Record model for one deployment health check run: its overall status and per-check
 * results. Rows from the retired security audit are kept with status 'legacy' and their report.
 */
class SecurityAudit implements ModelInterface
{
    use IsModel;

    protected static $tableName = 'security_audits';
    protected static $modelType = null;
    protected static $fillable = ['user_id', 'status', 'environment', 'results', 'report'];

    public $id;
    public $site_id;
    public $user_id;
    public $status;
    public $environment;
    public $results;
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
                'label' => 'Run by',
                'editable' => false,
                'listDisplay' => true
            ],
            'status' => ['type' => 'text', 'label' => 'Status', 'editable' => false, 'listDisplay' => true],
            'environment' => ['type' => 'text', 'label' => 'Environment', 'editable' => false, 'listDisplay' => true],
            'results' => ['type' => 'textarea', 'label' => 'Check results', 'editable' => false, 'listDisplay' => false],
            'report' => ['type' => 'textarea', 'label' => 'Legacy audit report', 'editable' => false, 'listDisplay' => false]
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
