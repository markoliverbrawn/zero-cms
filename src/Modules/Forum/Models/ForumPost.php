<?php

namespace Zero\Modules\Forum\Models;

use Zero\Interfaces\Model as ModelInterface;
use Zero\Models\Traits\IsModel;
use Zero\Models\User;

class ForumPost implements ModelInterface
{
    use IsModel;

    protected static $tableName = 'forum_posts';
    protected static $modelType = null;
    protected static $fillable = ['thread_id', 'user_id', 'content', 'parent_id', 'status'];

    public $id;
    public $thread_id;
    public $user_id;
    public $content;
    public $parent_id;
    public $status;
    public $created_at;
    public $updated_at;

    public static function getConfig(): array
    {
        return [
            'id' => ['type' => 'text', 'label' => 'ID', 'editable' => false, 'listDisplay' => false],
            'thread_id' => [
                'type' => 'readonly', 
                'label' => 'Thread', 
                'editable' => true, 
                'required' => true, 
                'listDisplay' => true,
                'listView' => 'fields/forum_thread'
            ],
            'user_id' => [
                'type' => 'readonly', 
                'label' => 'Posted By', 
                'editable' => true, 
                'required' => true, 
                'listDisplay' => true,
                'listView' => 'fields/forum_user'
            ],
            'content' => ['type' => 'textarea', 'label' => 'Content', 'editable' => true, 'required' => true, 'listDisplay' => true],
            'parent_id' => [
                'type' => 'readonly', 
                'label' => 'In Reply To', 
                'editable' => true, 
                'required' => false, 
                'listDisplay' => false,
                'listView' => 'fields/forum_post_parent'
            ],
            'status' => [
                'type' => 'select',
                'label' => 'Status',
                'editable' => true,
                'required' => true,
                'listDisplay' => true,
                'options' => [
                    'approved' => 'Approved',
                    'pending' => 'Pending',
                    'flagged' => 'Flagged'
                ]
            ]
        ];
    }

    public function getUser(): ?User
    {
        if (empty($this->user_id)) {
            return null;
        }
        return User::find($this->user_id);
    }
}
