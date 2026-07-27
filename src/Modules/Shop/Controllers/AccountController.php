<?php

namespace Zero\Modules\Shop\Controllers;

use Zero\Interfaces\Controller;
use Zero\Core\App;
use Zero\Models\User;
use Zero\Modules\Shop\Models\Order;
use Zero\Support\Security;

class AccountController implements Controller
{
    public function handle($param)
    {
        App::ensureSession();
        $user = App::getCurrentUser();

        // Strict auth guard: redirect to login if not authenticated
        if (!$user) {
            $_SESSION['redirect_to'] = '/shop/account';
            header('Location: /login');
            exit;
        }

        $userId = $user->id;
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'POST') {
            App::applyCsrfMiddleware();
            $action = $_POST['action'] ?? '';

            if ($action === 'update_profile') {
                $username = $_POST['username'] ?? '';
                $email = $_POST['email'] ?? '';

                if (!empty($username) && !empty($email)) {
                    $user->username = $username;
                    $user->email = $email;
                    $user->save();
                    $_SESSION['success_msg'] = 'Profile updated successfully!';
                } else {
                    $_SESSION['error_msg'] = 'All profile fields are required.';
                }
            } elseif ($action === 'add_address') {
                $label = $_POST['label'] ?? 'Home';
                $name = $_POST['name'] ?? '';
                $address = $_POST['address'] ?? '';

                if (!empty($name) && !empty($address)) {
                    $prefs = User::getPreferencesForUser($userId);
                    $addresses = $prefs['addresses'] ?? [];
                    
                    $addresses[] = [
                        'id' => Security::uuidv7(),
                        'label' => $label,
                        'name' => $name,
                        'address' => $address
                    ];
                    
                    $prefs['addresses'] = $addresses;
                    User::savePreferencesForUser($userId, $prefs);
                    $_SESSION['success_msg'] = 'New address registered!';
                } else {
                    $_SESSION['error_msg'] = 'All address fields are required.';
                }
            } elseif ($action === 'delete_address') {
                $addressId = $_POST['address_id'] ?? '';
                if (!empty($addressId)) {
                    $prefs = User::getPreferencesForUser($userId);
                    $addresses = $prefs['addresses'] ?? [];
                    
                    $addresses = array_filter($addresses, function ($addr) use ($addressId) {
                        return $addr['id'] !== $addressId;
                    });
                    
                    $prefs['addresses'] = array_values($addresses);
                    User::savePreferencesForUser($userId, $prefs);
                    $_SESSION['success_msg'] = 'Address removed.';
                }
            }

            header('Location: /shop/account');
            exit;
        }

        // Fetch past orders made under this customer's email address
        $orders = Order::where('customer_email', $user->email, 'ORDER BY created_at DESC');

        // Fetch current user preferences/addresses
        $prefs = User::getPreferencesForUser($userId);
        $addresses = $prefs['addresses'] ?? [];

        $success = $_SESSION['success_msg'] ?? '';
        $error = $_SESSION['error_msg'] ?? '';
        unset($_SESSION['success_msg'], $_SESSION['error_msg']);

        App::render('account', [
            'user' => $user,
            'orders' => $orders,
            'addresses' => $addresses,
            'success' => $success,
            'error' => $error
        ]);
        exit;
    }
}
