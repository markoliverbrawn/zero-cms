<?php

declare(strict_types=1);

/**
 * File: src/Modules/Forum/Models/ForumBoard.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Forum\Models
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Forum\Models;

use Zero\Interfaces\Model as ModelInterface;
use Zero\Models\Traits\CascadesDeletes;
use Zero\Models\Traits\HasSlug;
use Zero\Models\Traits\IsModel;
use Zero\Models\Traits\IsOrderable;
use Zero\Modules\Forum\Models\ForumThread;

/**
 * Class ForumBoard
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class ForumBoard implements ModelInterface
{
    use IsModel, HasSlug, IsOrderable, CascadesDeletes {
        CascadesDeletes::delete insteadof IsModel;
        CascadesDeletes::forceDelete insteadof IsModel;
        IsModel::delete as traitDelete;
        IsModel::forceDelete as traitForceDelete;
    }

    protected static $tableName = 'forum_boards';
    protected static $fillable = ['site_id', 'title', 'slug', 'description', 'precedence'];
    protected static $modelType = 'forum_board';
    protected static array $cascadeDeletes = [
        ForumThread::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class => 'board_id'
    ];

    public $id;
    public $site_id;
    public $title;
    public $slug;
    public $description;
    public $precedence;
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
            'title' => ['type' => 'text', 'label' => 'Board Title', 'editable' => true, 'required' => true, 'listDisplay' => true, 'searchable' => true],
            'slug' => ['type' => 'text', 'label' => 'Slug', 'editable' => false, 'listDisplay' => true],
            'description' => ['type' => 'textarea', 'label' => 'Description', 'editable' => true, 'required' => false, 'listDisplay' => true],
            'precedence' => ['type' => 'number', 'label' => 'Precedence', 'editable' => true, 'required' => true, 'listDisplay' => true]
        ];
    }
}
