<?php
// src/Modules/Admin/Views/setup-wizard.php

use Zero\Core\App;
use Zero\Support\Str;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zero CMS — Interactive Multi-Tenant Setup Wizard</title>
    <style>
        :root {
            --bg-color: #05070f;
            --card-bg: #0b0f19;
            --border-color: #1e293b;
            --accent-color: #00f0ff; /* Neon cyan */
            --accent-glow: rgba(0, 240, 255, 0.15);
            --text-color: #f8fafc;
            --text-muted: #64748b;
            --alert-error: #ef4444;
            --alert-error-bg: rgba(239, 68, 68, 0.1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            line-height: 1.5;
        }

        .wizard-container {
            width: 100%;
            max-width: 650px;
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 40px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
        }

        .wizard-header {
            text-align: center;
            margin-bottom: 30px;

            .logo {
                font-size: 2rem;
                font-weight: 800;
                color: var(--accent-color);
                letter-spacing: 0.1em;
                text-transform: uppercase;
                margin-bottom: 10px;
                display: inline-block;
                text-shadow: 0 0 20px var(--accent-glow);
            }

            .title {
                font-size: 1.25rem;
                font-weight: 600;
                margin-bottom: 6px;
            }

            .subtitle {
                font-size: 0.85rem;
                color: var(--text-muted);
            }
        }

        .dev-warning-banner {
            background-color: rgba(245, 158, 11, 0.08);
            border: 1px solid #f59e0b;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 25px;
            font-size: 0.82rem;
            color: #f59e0b;
            line-height: 1.4;

            strong {
                text-transform: uppercase;
                letter-spacing: 0.05em;
                display: block;
                margin-bottom: 4px;
            }
        }

        .alert-box {
            background-color: var(--alert-error-bg);
            border: 1px solid var(--alert-error);
            color: var(--alert-error);
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 25px;
            font-size: 0.85rem;
            line-height: 1.4;
        }

        .form-section-title {
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--accent-color);
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 6px;
            font-weight: 700;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            margin-bottom: 30px;

            @media (min-width: 600px) {
                grid-template-columns: 1fr 1fr;
                
                .grid-span-2 {
                    grid-column: span 2;
                }
            }
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;

            label {
                font-size: 0.8rem;
                font-weight: 600;
                color: var(--text-color);
            }

            input, select {
                background-color: var(--bg-color);
                border: 1px solid var(--border-color);
                border-radius: 4px;
                padding: 10px 14px;
                color: var(--text-color);
                font-size: 0.88rem;
                width: 100%;
                outline: none;
                transition: border-color 0.2s, box-shadow 0.2s;

                &:focus {
                    border-color: var(--accent-color);
                    box-shadow: 0 0 10px var(--accent-glow);
                }
            }

            .help-text {
                font-size: 0.75rem;
                color: var(--text-muted);
            }
        }

        .submit-btn {
            background-color: var(--accent-color);
            color: #05070f;
            border: none;
            border-radius: 4px;
            padding: 14px 20px;
            font-size: 0.92rem;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            transition: background-color 0.2s, transform 0.1s;
            box-shadow: 0 0 20px var(--accent-glow);

            &:hover {
                background-color: #33f4ff;
            }

            &:active {
                transform: scale(0.99);
            }
        }
    </style>
</head>
<body>
    <div class="wizard-container">
        <header class="wizard-header">
            <div class="logo">Zero CMS</div>
            <h1 class="title">System Setup Wizard</h1>
            <p class="subtitle">Initialize your Multi-Tenant Portal & Handshake Schemas</p>
        </header>

        <!-- Development Mode Notice Block -->
        <div class="dev-warning-banner">
            <strong>Development Environment Active</strong>
            This Setup Wizard is strictly running in "dev" mode (ENVIRONMENT=dev). In production settings, this wizard deactivates completely to protect server configuration boundaries.
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert-box">
                <?php foreach ($errors as $err): ?>
                    <div>⚠️ <?php echo Str::escape($err); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST">
            <!-- Section 1: Super Admin Credentials -->
            <h2 class="form-section-title">1. Administrator Account</h2>
            <div class="form-grid">
                <div class="form-group">
                    <label for="username">Username</label>
                    <?php echo App::makeFormField('text', 'username', [
                        'value' => $inputs['username'] ?? '',
                        'required' => true,
                        'attributes' => ['id' => 'username', 'autocomplete' => 'off'],
                        'showLabel' => false,
                        'guessHelperTextKey' => false,
                    ])->render(); ?>
                    <span class="help-text">Unique name for back-office authentication.</span>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <?php echo App::makeFormField('email', 'email', [
                        'value' => $inputs['email'] ?? '',
                        'required' => true,
                        'attributes' => ['id' => 'email', 'autocomplete' => 'off'],
                        'showLabel' => false,
                        'guessHelperTextKey' => false,
                    ])->render(); ?>
                    <span class="help-text">Used for account recovery and SMTP logs.</span>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <?php echo App::makeFormField('password', 'password', [
                        'required' => true,
                        'attributes' => ['id' => 'password', 'minlength' => 8],
                        'showLabel' => false,
                        'guessHelperTextKey' => false,
                    ])->render(); ?>
                    <span class="help-text">At least 8 characters.</span>
                </div>

                <div class="form-group">
                    <label for="password_confirmation">Confirm Password</label>
                    <?php echo App::makeFormField('password', 'password_confirmation', [
                        'required' => true,
                        'attributes' => ['id' => 'password_confirmation', 'minlength' => 8],
                        'showLabel' => false,
                        'guessHelperTextKey' => false,
                    ])->render(); ?>
                    <span class="help-text">Re-enter your admin password.</span>
                </div>
            </div>

            <!-- Section 2: Default Site Configurations -->
            <h2 class="form-section-title">2. Default Site Tenancy</h2>
            <div class="form-grid">
                <div class="form-group grid-span-2">
                    <label for="site_name">Site Title</label>
                    <?php echo App::makeFormField('text', 'site_name', [
                        'value' => $inputs['site_name'] ?? 'Zero CMS Portal',
                        'required' => true,
                        'attributes' => ['id' => 'site_name', 'autocomplete' => 'off'],
                        'showLabel' => false,
                        'guessHelperTextKey' => false,
                    ])->render(); ?>
                    <span class="help-text">The display name of your main tenant website.</span>
                </div>

                <div class="form-group">
                    <label for="site_domain">Primary Domain Name</label>
                    <?php echo App::makeFormField('text', 'site_domain', [
                        'value' => $inputs['site_domain'] ?? 'localhost',
                        'required' => true,
                        'attributes' => ['id' => 'site_domain', 'autocomplete' => 'off'],
                        'showLabel' => false,
                        'guessHelperTextKey' => false,
                    ])->render(); ?>
                    <span class="help-text">e.g. "localhost", "d6laptop.zero" or "myportal.local"</span>
                </div>

                <div class="form-group">
                    <label for="site_theme">Active Layout Theme</label>
                    <?php echo App::makeFormField('select', 'site_theme', [
                        'value' => $inputs['site_theme'],
                        'options' => ['default' => 'Default Theme', 'kitchensink' => 'Kitchen Sink Showroom'],
                        'attributes' => ['id' => 'site_theme'],
                        'showLabel' => false,
                        'guessHelperTextKey' => false,
                    ])->render(); ?>
                    <span class="help-text">Governs view templates and stylesheets.</span>
                </div>

                <div class="form-group">
                    <label for="site_timezone">Site Timezone</label>
                    <?php echo App::makeFormField('select', 'site_timezone', [
                        'value' => $inputs['site_timezone'],
                        'options' => [
                            'Pacific/Auckland' => 'Auckland (UTC+12)',
                            'UTC' => 'Coordinated Universal Time (UTC)',
                            'Europe/London' => 'London (GMT/BST)',
                            'America/New_York' => 'New York (EST/EDT)',
                        ],
                        'attributes' => ['id' => 'site_timezone'],
                        'showLabel' => false,
                        'guessHelperTextKey' => false,
                    ])->render(); ?>
                    <span class="help-text">Determines canonical audit and content timestamps.</span>
                </div>

                <div class="form-group">
                    <label for="site_language">Default Language</label>
                    <?php echo App::makeFormField('select', 'site_language', [
                        'value' => $inputs['site_language'],
                        'options' => ['en' => 'English (EN)', 'es' => 'Español (ES)', 'hr' => 'Hrvatski (HR)', 'mi' => 'Māori (MI)'],
                        'attributes' => ['id' => 'site_language'],
                        'showLabel' => false,
                        'guessHelperTextKey' => false,
                    ])->render(); ?>
                    <span class="help-text">Sets default fallback localized text mappings.</span>
                </div>
            </div>

            <button type="submit" class="submit-btn">Complete Installation & Launch</button>
        </form>
    </div>
</body>
</html>
