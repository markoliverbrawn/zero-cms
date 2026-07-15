<?php

namespace Zero\Modules\Admin\Controllers;

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Support\Logger;
use Zero\Support\Security;
use Zero\Http\Middleware\AuthThrottlingMiddleware;
use Zero\Interfaces\Controller;

class ResetController implements Controller
{
    public function handle($param)
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $token = $_GET['token'] ?? ($_POST['token'] ?? '');
        if ($method === 'POST') {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

            // Enforce centralized rate limiting and progressive lockout protection via Middleware
            AuthThrottlingMiddleware::handle('password_reset', 'admin/reset', ['token' => $token], function() {});

            $new = $_POST['password'] ?? '';
            $row = DB::query('SELECT * FROM password_resets WHERE token = ? LIMIT 1', [$token])->fetch();
            if (!$row || strtotime($row['expires_at']) < time()) {
                // Log failed attempt to increment rate limit counter
                Logger::log(null, 'password_reset_failed', 'user', null, [
                    'ip_address' => $ip,
                    'token' => $token
                ]);
                App::render('admin/reset', ['error' => 'Invalid or expired token']);
                exit;
            }
            $hash = password_hash($new, PASSWORD_DEFAULT);
            DB::query('UPDATE users SET password_hash = ? WHERE id = ?', [$hash, $row['user_id']]);
            DB::query('DELETE FROM password_resets WHERE id = ?', [$row['id']]);
            Logger::log($row['user_id'], 'password_reset_success', 'user', $row['user_id'], ['ip_address' => $_SERVER['REMOTE_ADDR']]);
            App::render('admin/reset', ['success' => true]);
            exit;
        }
        App::render('admin/reset', ['token' => $token]);
        exit;
    }
}
