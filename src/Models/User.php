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
        IsModel::forceDelete as traitForceDelete;
        IsModel::restore as traitRestore;
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
     * Invalidate every session that signed in to the account before now. Sessions aren't stored
     * per user, so this works through password_changed_at, which bootstrapFetchSiteAndUser()
     * compares with each session's sign-in time.
     *
     * @param string $userId Account whose sessions are revoked.
     * @return void
     */
    public static function revokeSessions(string $userId): void
    {
        DB::query("UPDATE users SET password_changed_at = ? WHERE id = ?", [\gmdate('Y-m-d H:i:s'), $userId]);
    }

    /**
     * Save processing implementation helper.
     *
     * @return string Response output.
     */
    public function save(): string
    {
        // Roles are compared exactly in PHP but case- and trailing-space-insensitively by MySQL, so a
        // near-miss like "SUPER_ADMIN" or "super_admin " would match role = 'super_admin' in SQL
        // lookups. Only canonical role identifiers may be stored.
        if ($this->role !== null && $this->role !== '' && !\preg_match('/^[a-z][a-z0-9_]*$/', (string) $this->role)) {
            throw new \InvalidArgumentException('Invalid role.');
        }

        $this->guardSuperAdminChange($this->role === 'super_admin');

        // If this is a new user and no password_hash is set, generate a secure random password
        if (empty($this->id) && empty($this->password_hash)) {
            // Generate a secure, temporary random password (and hash it)
            $tempPassword = \bin2hex(\random_bytes(10));
            $this->password_hash = \password_hash($tempPassword, PASSWORD_BCRYPT);
        }

        // Read before saving: the identity-mapped instance may already carry the new hash.
        $storedHash = empty($this->id) ? null : DB::query(
            "SELECT password_hash FROM users WHERE id = ?",
            [$this->id]
        )->fetchColumn();

        $result = $this->traitSave();

        // A password set through any path (e.g. the admin model API) signs out the account's
        // existing sessions, the same as the change-password and reset flows.
        if (\is_string($storedHash) && $this->password_hash !== null && !\hash_equals($storedHash, (string) $this->password_hash)) {
            self::revokeSessions((string) $this->id);
        }

        return $result;
    }

    /**
     * Soft-delete the account, refusing to let a non-super_admin remove a super_admin.
     *
     * @return mixed Response output.
     */
    public function delete()
    {
        $this->guardSuperAdminChange(false);

        $result = $this->traitDelete();

        // Sign out every existing session now, so restoring the account later can't bring old
        // (possibly stolen) sessions back to life.
        self::revokeSessions((string) $this->id);

        return $result;
    }

    /**
     * Permanently delete the account, with the same admin guard as delete().
     *
     * @return mixed Response output.
     */
    public function forceDelete()
    {
        $this->guardSuperAdminChange(false);

        return $this->traitForceDelete();
    }

    /**
     * Restore a soft-deleted account, with the same admin guard as delete().
     *
     * @return mixed Response output.
     */
    public function restore()
    {
        $this->guardSuperAdminChange(false);

        return $this->traitRestore();
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
     * account that is currently super_admin, or another account that is currently admin (a site
     * admin can change their own account, but not a fellow admin's email, role or password, which
     * would let them take that account over). Guest flows that legitimately save a super_admin's own
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
        $touchesOtherAdmin = $storedRole === 'admin' && $actor !== null && (string) $actor->id !== (string) $this->id;

        if ($escalates || $touchesSuperAdmin || $touchesOtherAdmin) {
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
