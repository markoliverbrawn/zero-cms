<?php

namespace Zero\Models;

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Interfaces\Model;
use Zero\Models\Traits\HasSlug;
use Zero\Models\Traits\IsModel;
use Zero\Support\I18n;
use Zero\Support\Str;

class User implements Model
{
    use IsModel, HasSlug {
        IsModel::save as traitSave;
    }

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

    public static function getConfig(): array
    {
        return [
            'id' => ['type' => 'int', 'label' => I18n::t('id'), 'editable' => false, 'listDisplay' => false],
            'username' => ['type' => 'text', 'label' => I18n::t('username'), 'editable' => true, 'required' => true, 'listDisplay' => true, 'searchable' => true],
            'email' => ['type' => 'email', 'label' => I18n::t('email'), 'editable' => true, 'listDisplay' => true],
            'role' => [
                'type' => 'select',
                'label' => 'User Role',
                'options' => [
                    'super_admin' => 'Super Admin (Full Platform Access)',
                    'admin' => 'Admin (Site Manager)',
                    'editor' => 'Editor (Content Only)'
                ],
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
                $decoded = json_decode($preferencesJson, true);
                if (json_last_error() === JSON_ERROR_NONE) {
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
                    $decoded = json_decode($preferencesJson, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
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

    public function save(): string
    {
        // If this is a new user and no password_hash is set, generate a secure random password
        if (empty($this->id) && empty($this->password_hash)) {
            // Generate a secure, temporary random password (and hash it)
            $tempPassword = bin2hex(random_bytes(10));
            $this->password_hash = password_hash($tempPassword, PASSWORD_BCRYPT);
        }
        
        return $this->traitSave();
    }

    /**
     * Save user preferences.
     */
    public static function savePreferencesForUser(string $userId, array $prefs): bool
    {
        $json = json_encode($prefs);
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
