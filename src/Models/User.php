<?php

declare(strict_types=1);

/**
 * File: src/Models/User.php
 * Architectural Purpose: Active Record data model or behavioral trait wrapping database schema representation with tenant-scoping.
 * Package: Zero\Models
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Models;

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Interfaces\Model;
use Zero\Models\Traits\HasSlug;
use Zero\Models\Traits\IsModel;
use Zero\Support\I18n;
use Zero\Support\Permissions;
use Zero\Support\Str;

/**
 * Class User
 *
 * Active Record model for accounts, covering credential storage and the per-user preferences blob.
 */
class User implements Model
{
    use IsModel, HasSlug {
        IsModel::save as traitSave;
        IsModel::delete as traitDelete;
    }

    /**
     * RBAC permission required to grant the super_admin role, or to modify/delete an account that
     * already holds it. Only super_admin holds it (via its '*' wildcard), so a site admin with
     * users.manage can never escalate themselves or anyone else to platform-wide access.
     */
    public const SUPER_ADMIN_PERMISSION = 'users.assign_super_admin';

    /**
     * Back-office roles offered in the admin role picker, keyed by stored value. Other roles (e.g.
     * 'member' from self-registration) exist but are not assigned from this form.
     */
    public const ROLES = [
        'super_admin' => 'Super Admin (Full Platform Access)',
        'admin' => 'Admin (Site Manager)',
        'editor' => 'Editor (Content Only)',
    ];

    protected static $tableName = 'users';
    protected static $fillable = ['username', 'email', 'role', 'site_id', 'password_hash'];
    protected static $modelType = 'User';
    protected static $prefsCache = [];

    public $username;
    public $email;
    public $role;
    public $site_id;
    public $preferences;
    public $password_hash;
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
            'id' => ['type' => 'int', 'label' => I18n::t('id'), 'editable' => false, 'listDisplay' => false],
            'username' => ['type' => 'text', 'label' => I18n::t('username'), 'editable' => true, 'required' => true, 'listDisplay' => true, 'searchable' => true],
            'email' => ['type' => 'email', 'label' => I18n::t('email'), 'editable' => true, 'listDisplay' => true],
            'role' => [
                'type' => 'select',
                'label' => 'User Role',
                'options' => self::assignableRoles(),
                'editable' => true,
                'listDisplay' => true,
                'required' => true
            ],
            'created_at' => ['type' => 'datetime', 'label' => I18n::t('created_at'), 'editable' => false, 'listDisplay' => true],
        ];
    }

    /**
     * Get user preferences, merging with default preferences.
     */
    public static function getPreferencesForUser(string $userId): array
    {
        if (isset(self::$prefsCache[$userId])) {
            return self::$prefsCache[$userId];
        }

        // Directly reference our pre-bootstrapped App static user object!
        $currentUser = App::getCurrentUser();
        if ($currentUser instanceof User && $currentUser->id === $userId) {
            $preferencesJson = $currentUser->preferences ?? '';
            if (!empty($preferencesJson)) {
                $decoded = \json_decode($preferencesJson, true);
                if (\json_last_error() === JSON_ERROR_NONE) {
                    self::$prefsCache[$userId] = $decoded;
                    return $decoded;
                }
            }
            self::$prefsCache[$userId] = [];
            return [];
        }

        // Bypassing model hydration to query raw preferences in database safely (avoids circular dependency on boot)
        try {
            $stmt = DB::query("SELECT preferences FROM users WHERE id = ? LIMIT 1", [$userId]);
            $row = $stmt->fetch();
            if ($row) {
                $preferencesJson = $row['preferences'] ?? '';
                if (!empty($preferencesJson)) {
                    $decoded = \json_decode($preferencesJson, true);
                    if (\json_last_error() === JSON_ERROR_NONE) {
                        self::$prefsCache[$userId] = $decoded;
                        return $decoded;
                    }
                }
                self::$prefsCache[$userId] = [];
                return [];
            }
        } catch (\Exception $e) {
            // Safe fallback during database bootstrapping or installation phases
        }

        // Strict Exception: If User is not found, throw a strict Exception on web requests
        if (App::isCli()) {
            return [];
        }
        
        throw new \Exception("User preferences requested for non-existent or inactive user: " . Str::escape($userId));
    }

    /**
     * Save processing implementation helper.
     *
     * @return string Response output.
     */
    public function save(): string
    {
        $this->guardSuperAdminChange($this->role === 'super_admin');

        // If this is a new user and no password_hash is set, generate a secure random password
        if (empty($this->id) && empty($this->password_hash)) {
            // Generate a secure, temporary random password (and hash it)
            $tempPassword = \bin2hex(\random_bytes(10));
            $this->password_hash = \password_hash($tempPassword, PASSWORD_BCRYPT);
        }
        
        return $this->traitSave();
    }

    /**
     * Soft-delete the account, refusing to let a non-super_admin remove a super_admin.
     *
     * @return mixed Response output.
     */
    public function delete()
    {
        $this->guardSuperAdminChange(false);

        return $this->traitDelete();
    }

    /**
     * Roles the signed-in user may assign. super_admin is offered only to those who may grant it.
     *
     * @return array<string, string>
     */
    public static function assignableRoles(): array
    {
        $roles = self::ROLES;
        if (App::getCurrentUser() !== null && !App::authorize(self::SUPER_ADMIN_PERMISSION)) {
            unset($roles['super_admin']);
        }

        return $roles;
    }

    /**
     * Halt with a 403 when an HTTP request without SUPER_ADMIN_PERMISSION would grant super_admin
     * (to anyone, including the caller), or when a signed-in user without it modifies or deletes an
     * account that is currently super_admin. Guest flows that legitimately save a super_admin's own
     * row (password reset) are not role changes and stay allowed. The stored role is read straight
     * from the database because the identity-mapped instance may already carry submitted values.
     *
     * Non-HTTP contexts (bin/seed, migrations, other CLI tools) are not gated.
     *
     * @param bool $grantsSuperAdmin Whether the pending write sets role to super_admin.
     * @return void
     */
    private function guardSuperAdminChange(bool $grantsSuperAdmin): void
    {
        if (!isset($_SERVER['REQUEST_METHOD'])) {
            return;
        }

        // Authorize against the actor's role as stored, not App::getCurrentUser()->role: find()
        // returns the identity-mapped instance, so when an admin edits their own row the submitted
        // role has already been written onto the very object that represents the signed-in user.
        $actor = App::getCurrentUser();
        $actorRole = 'guest';
        if ($actor !== null) {
            $actorRow = DB::query('SELECT role FROM users WHERE id = ? LIMIT 1', [$actor->id])->fetch();
            $actorRole = $actorRow['role'] ?? 'guest';
        }

        if (Permissions::roleHas($actorRole, self::SUPER_ADMIN_PERMISSION)) {
            return;
        }

        $storedRole = null;
        if (!empty($this->id)) {
            $row = DB::query('SELECT role FROM users WHERE id = ? LIMIT 1', [$this->id])->fetch();
            $storedRole = $row['role'] ?? null;
        }

        $escalates = $grantsSuperAdmin && $storedRole !== 'super_admin';
        $touchesSuperAdmin = $storedRole === 'super_admin' && $actor !== null;

        if ($escalates || $touchesSuperAdmin) {
            App::denyAccess(self::SUPER_ADMIN_PERMISSION, $actorRole);
        }
    }

    /**
     * Save user preferences.
     */
    public static function savePreferencesForUser(string $userId, array $prefs): bool
    {
        $json = \json_encode($prefs);
        DB::query("UPDATE users SET preferences = ? WHERE id = ?", [$json, $userId]);
        
        // Invalidate / update cache
        self::$prefsCache[$userId] = $prefs;
        
        // Also update the loaded user model property if present in identity map
        $cachedUser = DB::getIdentity('users', $userId);
        if ($cachedUser instanceof User) {
            $cachedUser->preferences = $json;
        }
        
        return true;
    }
}
