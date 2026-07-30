<?php
use Zero\Support\Str;
/**
 * src/Views/errors/site-not-found.php
 * Polished diagnostic/error template for unconfigured multi-tenant site requests.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Site Not Found</title>
    <style>
        :root {
            --bg-color: #0b0c10;
            --card-bg: #141722;
            --border-color: #222636;
            --text-color: #f8fafc;
            --text-muted: #94a3b8;
            --accent-color: #06b6d4;
            --error-color: #f43f5e;
            --font-family: <?= $isDev ? "'Courier New', Courier, monospace" : "system-ui, -apple-system, sans-serif" ?>;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: var(--font-family);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
            line-height: 1.6;
        }

        .error-container {
            width: 100%;
            max-width: <?= $isDev ? "800px" : "500px" ?>;
            background-color: var(--card-bg);
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            <?= !$isDev ? "text-align: center;" : "" ?>
        }

        .error-header {
            display: flex;
            align-items: center;
            gap: 16px;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 20px;
            margin-bottom: 30px;
            <?= !$isDev ? "flex-direction: column; text-align: center; border-bottom: none; margin-bottom: 10px; padding-bottom: 0;" : "" ?>
        }

        .error-icon {
            color: var(--error-color);
            flex-shrink: 0;
            <?= !$isDev ? "margin-bottom: 10px;" : "" ?>
        }

        .error-title {
            font-size: <?= $isDev ? "1.8rem" : "1.5rem" ?>;
            font-weight: bold;
            color: <?= $isDev ? "var(--error-color)" : "var(--text-color)" ?>;
            text-transform: uppercase;
            letter-spacing: 1px;
            <?= !$isDev ? "text-transform: none; margin-bottom: 12px;" : "" ?>
        }

        .error-subtitle {
            font-size: 1rem;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .requested-domain {
            background-color: rgba(244, 63, 94, 0.1);
            border: 1px solid rgba(244, 63, 94, 0.3);
            color: var(--error-color);
            padding: 12px 18px;
            border-radius: 4px;
            font-size: 1.1rem;
            font-weight: bold;
            word-break: break-all;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .description {
            font-size: 1rem;
            color: <?= $isDev ? "var(--text-color)" : "var(--text-muted)" ?>;
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: bold;
            color: var(--accent-color);
            text-transform: uppercase;
            margin-bottom: 16px;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tips-list {
            list-style: none;
            margin-bottom: 30px;
        }

        .tips-list li {
            position: relative;
            padding-left: 24px;
            margin-bottom: 16px;
            color: var(--text-color);
        }

        .tips-list li::before {
            content: "➔";
            position: absolute;
            left: 0;
            color: var(--accent-color);
        }

        .tips-list strong {
            color: var(--accent-color);
        }

        .command-block {
            background-color: #0b0f19;
            border: 1px solid var(--border-color);
            color: #00ffcc;
            padding: 10px 14px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 0.85rem;
            margin-top: 8px;
            overflow-x: auto;
            display: block;
        }

        .tenants-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 12px;
            margin-bottom: 30px;
        }

        .tenant-card {
            background-color: #0b0f19;
            border: 1px solid var(--border-color);
            padding: 12px;
            border-radius: 4px;
            font-size: 0.85rem;
            transition: border-color 0.2s, background-color 0.2s;
        }

        .tenant-card:hover {
            border-color: var(--accent-color);
            background-color: #0d1222;
        }

        .tenant-name {
            font-weight: bold;
            color: var(--text-color);
            margin-bottom: 4px;
        }

        .tenant-domain {
            color: var(--accent-color);
            text-decoration: none;
            word-break: break-all;
            display: block;
        }

        .tenant-domain:hover {
            text-decoration: underline;
        }

        .no-tenants-warning {
            color: var(--error-color);
            font-weight: bold;
            border: 1px dashed var(--error-color);
            padding: 12px;
            border-radius: 4px;
            background-color: rgba(244, 63, 94, 0.05);
        }

        .footer {
            border-top: 2px solid var(--border-color);
            padding-top: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            color: var(--text-muted);
            <?= !$isDev ? "border-top: 1px solid var(--border-color); justify-content: center;" : "" ?>
        }

        .footer a {
            color: var(--accent-color);
            text-decoration: none;
        }

        .footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <?php if ($isDev): ?>
            <!-- DEVELOPMENT / DIAGNOSTIC MODE VIEW -->
            <header class="error-header">
                <svg class="error-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                    <line x1="12" y1="9" x2="12" y2="13"></line>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
                <div>
                    <h1 class="error-title">Domain Not Configured</h1>
                    <p class="error-subtitle">404 Site Not Found</p>
                </div>
            </header>

            <div class="requested-domain">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="2" y1="12" x2="22" y2="12"></line>
                    <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                </svg>
                <span>Requested Host: <?= Str::escape($host) ?></span>
            </div>

            <p class="description">
                The domain name requested in your browser's host headers does not match any active multi-tenant tenant website in the Zero CMS database.
            </p>

            <h2 class="section-title">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                </svg>
                How to Resolve & Troubleshoot
            </h2>
            <ul class="tips-list">
                <li>
                    <strong>Run Database Seeders & Migrations:</strong> If this is a fresh setup or the database is unpopulated, you must run the migrations and seeders to initialize the default sites:
                    <code class="command-block">docker exec -w /data/misc/zero php83 php seeders/seeder.php</code>
                </li>
                <li>
                    <strong>Map Domain in Local Hosts:</strong> Ensure that your local hosts mapping configuration redirects this domain to your server's IP address (typically <code>127.0.0.1</code>) inside <code>/etc/hosts</code>:
                    <code class="command-block">127.0.0.1  d6laptop.zero d6laptop.zero.guide d6laptop.zero.kitchensink</code>
                </li>
                <li>
                    <strong>Register Tenant inside Back-office:</strong> If you are a platform Super Administrator, log in via a valid configured domain to manage and register new site configurations in the central administrative panel.
                </li>
            </ul>

            <h2 class="section-title">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="2" width="20" height="20" rx="2.18" ry="2.18"></rect>
                    <line x1="7" y1="2" x2="7" y2="22"></line>
                    <line x1="17" y1="2" x2="17" y2="22"></line>
                    <line x1="2" y1="12" x2="22" y2="12"></line>
                    <line x1="2" y1="7" x2="22" y2="7"></line>
                    <line x1="2" y1="17" x2="22" y2="17"></line>
                </svg>
                Available Configured Domains
            </h2>
            <?php if (!empty($activeSites)): ?>
                <div class="tenants-grid">
                    <?php foreach ($activeSites as $site): ?>
                        <div class="tenant-card">
                            <div class="tenant-name"><?= Str::escape($site['name']) ?></div>
                            <a class="tenant-domain" href="http://<?= Str::escape($site['domain']) . $portSuffix ?>"><?= Str::escape($site['domain']) . $portSuffix ?></a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="description no-tenants-warning">
                    ⚠️ No active tenant sites detected in the database. Please run the seeding scripts to populate default site records.
                </p>
            <?php endif; ?>

            <footer class="footer">
                <span>Powered by <strong>Zero CMS Core</strong> (Zero Dependency Multisite)</span>
                <a href="https://github.com" target="_blank" rel="noopener noreferrer">System Documentation</a>
            </footer>

        <?php else: ?>
            <!-- SECURE PRODUCTION FALLBACK VIEW -->
            <svg class="error-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                <line x1="12" y1="9" x2="12" y2="13"></line>
                <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
            <h1 class="error-title">Site Not Found</h1>
            <p class="description">
                The requested website is not configured or active on this server. Please contact the administrator.
            </p>
            <footer class="footer">
                <span>Zero CMS Core</span>
            </footer>
        <?php endif; ?>
    </div>
</body>
</html>
