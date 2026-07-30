<?php
// src/Modules/Admin/Views/setup-wizard.php

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
                    <input type="text" id="username" name="username" value="<?php echo Str::escape($inputs['username'] ?? ''); ?>" required autocomplete="off">
                    <span class="help-text">Unique name for back-office authentication.</span>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" value="<?php echo Str::escape($inputs['email'] ?? ''); ?>" required autocomplete="off">
                    <span class="help-text">Used for account recovery and SMTP logs.</span>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required minlength="8">
                    <span class="help-text">At least 8 characters.</span>
                </div>

                <div class="form-group">
                    <label for="password_confirmation">Confirm Password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required minlength="8">
                    <span class="help-text">Re-enter your admin password.</span>
                </div>
            </div>

            <!-- Section 2: Default Site Configurations -->
            <h2 class="form-section-title">2. Default Site Tenancy</h2>
            <div class="form-grid">
                <div class="form-group grid-span-2">
                    <label for="site_name">Site Title</label>
                    <input type="text" id="site_name" name="site_name" value="<?php echo Str::escape($inputs['site_name'] ?? 'Zero CMS Portal'); ?>" required autocomplete="off">
                    <span class="help-text">The display name of your main tenant website.</span>
                </div>

                <div class="form-group">
                    <label for="site_domain">Primary Domain Name</label>
                    <input type="text" id="site_domain" name="site_domain" value="<?php echo Str::escape($inputs['site_domain'] ?? 'localhost'); ?>" required autocomplete="off">
                    <span class="help-text">e.g. "localhost", "d6laptop.zero" or "myportal.local"</span>
                </div>

                <div class="form-group">
                    <label for="site_theme">Active Layout Theme</label>
                    <select id="site_theme" name="site_theme">
                        <option value="default" <?php echo $inputs['site_theme'] === 'default' ? 'selected' : ''; ?>>Default Theme</option>
                        <option value="guide" <?php echo $inputs['site_theme'] === 'guide' ? 'selected' : ''; ?>>Developer Guide Theme</option>
                        <option value="kitchensink" <?php echo $inputs['site_theme'] === 'kitchensink' ? 'selected' : ''; ?>>Kitchen Sink Showroom</option>
                    </select>
                    <span class="help-text">Governs view templates and stylesheets.</span>
                </div>

                <div class="form-group">
                    <label for="site_timezone">Site Timezone</label>
                    <select id="site_timezone" name="site_timezone">
                        <option value="Pacific/Auckland" <?php echo $inputs['site_timezone'] === 'Pacific/Auckland' ? 'selected' : ''; ?>>Auckland (UTC+12)</option>
                        <option value="UTC" <?php echo $inputs['site_timezone'] === 'UTC' ? 'selected' : ''; ?>>Coordinated Universal Time (UTC)</option>
                        <option value="Europe/London" <?php echo $inputs['site_timezone'] === 'Europe/London' ? 'selected' : ''; ?>>London (GMT/BST)</option>
                        <option value="America/New_York" <?php echo $inputs['site_timezone'] === 'America/New_York' ? 'selected' : ''; ?>>New York (EST/EDT)</option>
                    </select>
                    <span class="help-text">Determines canonical audit and content timestamps.</span>
                </div>

                <div class="form-group">
                    <label for="site_language">Default Language</label>
                    <select id="site_language" name="site_language">
                        <option value="en" <?php echo $inputs['site_language'] === 'en' ? 'selected' : ''; ?>>English (EN)</option>
                        <option value="es" <?php echo $inputs['site_language'] === 'es' ? 'selected' : ''; ?>>Español (ES)</option>
                        <option value="hr" <?php echo $inputs['site_language'] === 'hr' ? 'selected' : ''; ?>>Hrvatski (HR)</option>
                        <option value="mi" <?php echo $inputs['site_language'] === 'mi' ? 'selected' : ''; ?>>Māori (MI)</option>
                    </select>
                    <span class="help-text">Sets default fallback localized text mappings.</span>
                </div>
            </div>

            <button type="submit" class="submit-btn">Complete Installation & Launch</button>
        </form>
    </div>
</body>
</html>
