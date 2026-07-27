<?php
// seeders/generate_ai_images.php
// Script to generate featured images for the Guide site blog entries using Google Imagen 4.0.

define('APPLICATION_ROOT', dirname(__DIR__));

// Register PSR-4 Namespace Autoloading
spl_autoload_register(function ($class) {
    $prefix = 'Zero\\';
    $base_dir = APPLICATION_ROOT . '/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

use Zero\Core\Env;
use Zero\Core\App;
use Zero\Services\AiService;

Env::load(APPLICATION_ROOT);
App::bootstrap();

echo "==================================================\n";
echo "ZERO CMS AI FEATURED IMAGE GENERATOR (ALL 8 IMAGES)\n";
echo "==================================================\n";

if (!AiService::isAvailable()) {
    echo "Error: AiService is not available. Please verify GEMINI_API_KEY in your .env file.\n";
    exit(1);
}

$outputDir = APPLICATION_ROOT . '/seeders/data/generated-images';
if (!file_exists($outputDir)) {
    mkdir($outputDir, 0755, true);
}

// All 8 target blog entries needing JPEGs
$targets = [
    // --- Original 3 Images ---
    [
        'slug' => 'managing-supply-chain-risks-web-apps',
        'title' => 'Managing Supply Chain Risks in Modern Web Applications',
        'filename' => 'supply-chain-security.jpg',
        'prompt' => 'A futuristic, dark cyber-tech visualization of software supply chain security. A central glowing node representing a secured core application is connected by thin digital cyan wireframe lines to an outer web of dependencies. Some outer dependency nodes are highlighted with red warning icons representing vulnerable packages, while glowing security shields scan the connections. The background is a very dark navy (#051424) with faint terminal grid lines. Abstract high-contrast minimalist tech art, glowing cyan (#00f0ff) and deep blue accents, no text, highly polished, sleek developer guide aesthetic.'
    ],
    [
        'slug' => 'comparing-orms-to-raw-sql-prepared-queries',
        'title' => 'Comparing ORMs to Raw SQL Prepared Queries',
        'filename' => 'database-performance.jpg',
        'prompt' => 'A high-speed, high-performance database optimization visualization. Glowing 3D neon database table grids and columns compile raw SQL query streams on a dark terminal screen. Rapid stream lines of light representing data transfer flow directly into an optimized query engine with glowing cyan and blue energy. High-contrast cyber-tech aesthetic, very dark navy (#051424) background, glowing neon cyan (#00f0ff) accents, minimalist abstract representation of database speed, no text, sleek and modern.'
    ],
    [
        'slug' => 'sending-transactional-emails-php-tcp-sockets',
        'title' => 'Sending Transactional Emails via Native PHP TCP Sockets',
        'filename' => 'tcp-socket-emailer.jpg',
        'prompt' => 'A low-level networking and communication visual. Glowing digital electronic mail envelope icons transmitting rapidly along a series of fiber-optic socket connection lines through a dark cyberspace environment. Representation of raw network stream packets moving through a direct TCP/IP port on a server console. Cyberpunk tech aesthetic, dark navy background (#051424) with glowing bright cyan (#00f0ff) and electric blue socket streams, high-contrast abstract tech art, no text, clean and professional.'
    ],
    
    // --- Next 5 Images ---
    [
        'slug' => 'securing-web-applications-code-simplicity',
        'title' => 'Securing Web Applications Through Absolute Code Simplicity',
        'filename' => 'code-simplicity.jpg',
        'prompt' => 'A striking minimalist representation of secure software code simplicity. Clean, glowing cyan vertical lines of perfect source code on a dark navy terminal interface, surrounded by glowing defensive security rings. Cyber-tech cyberpunk aesthetic, very dark navy (#051424) background, glowing neon cyan (#00f0ff) details, no text, clean and professional look.'
    ],
    [
        'slug' => 'handling-concurrency-race-conditions-checkouts',
        'title' => 'Handling Concurrency Race Conditions in E-Commerce Checkouts',
        'filename' => 'concurrency-race-conditions.jpg',
        'prompt' => 'An abstract representation of e-commerce checkout concurrency and transactional locks. Glowing cyan and white data blocks representing transactions queueing up sequentially before entering a secure database locker. High-contrast cyber-tech cyberpunk theme, deep navy (#051424) and cyan (#00f0ff) details, abstract database locking mechanism, no text.'
    ],
    [
        'slug' => 'preventing-cross-site-scripting-recursive-input-sanitization',
        'title' => 'Preventing Cross-Site Scripting via Recursive Input Sanitization',
        'filename' => 'xss-prevention.jpg',
        'prompt' => 'A high-tech digital input filter sanitizing untrusted inputs. Faint glowing green data variables passing through a glowing neon cyan filter mesh, emerging clean, secure, and purified on a dark navy background. High-contrast cyber-tech aesthetic, very dark navy (#051424) background, bright cyan (#00f0ff) elements, abstract representation of secure programming, no text.'
    ],
    [
        'slug' => 'enforcing-strict-database-boundary-isolation-multi-tenant',
        'title' => 'Enforcing Database Boundary Isolation in Multi-Tenant Apps',
        'filename' => 'database-boundary-isolation.jpg',
        'prompt' => 'An abstract multi-tenant database boundary isolation system. Multiple parallel, isolated glowing data tunnels or secure compartments representing separate site tenants, perfectly partitioned by high-contrast neon cyan energy walls. Cyberpunk tech aesthetic, dark navy background (#051424) with glowing bright cyan (#00f0ff) and electric blue compartment lines, no text.'
    ],
    [
        'slug' => 'continuous-integration-isolated-tests-php-subprocesses',
        'title' => 'Continuous Integration: Running Isolated Tests inside PHP Subprocesses',
        'filename' => 'ci-isolated-tests.jpg',
        'prompt' => 'A modern continuous integration automated test runner. A series of multiple isolated terminal window boxes executing code tests in parallel, showing green successful checkmark badges and progress lines. High-contrast cyber-tech cyberpunk theme, deep navy background (#051424), glowing neon cyan (#00f0ff) details, abstract software testing representation, no text.'
    ]
];

foreach ($targets as $index => $target) {
    $filePath = $outputDir . '/' . $target['filename'];
    
    if (file_exists($filePath) && filesize($filePath) > 1000) {
        $sizeKb = round(filesize($filePath) / 1024, 2);
        echo "⏭️ Skipping image [" . ($index + 1) . "/8] (already exists): '{$target['filename']}' ({$sizeKb} KB)\n";
        continue;
    }
    
    echo "\nGenerating image [" . ($index + 1) . "/8] for post: '{$target['title']}'...\n";
    echo "Filename: {$target['filename']}\n";
    
    try {
        // Request a 16:9 aspect ratio landscape image for beautiful blog headers
        $base64 = AiService::generateImage($target['prompt'], [
            'aspect_ratio' => '16:9'
        ]);
        
        $decoded = base64_decode($base64);
        if (empty($decoded)) {
            throw new Exception("Decoded image data is empty.");
        }
        
        if (file_put_contents($filePath, $decoded) === false) {
            throw new Exception("Failed to write generated image to disk.");
        }
        
        $sizeKb = round(filesize($filePath) / 1024, 2);
        echo "✅ Success! Saved to: seeders/data/generated-images/{$target['filename']} ({$sizeKb} KB)\n";
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
}

echo "\n==================================================\n";
echo "AI IMAGE GENERATION COMPLETED!\n";
echo "==================================================\n";
