<?php
// src/Modules/DemoGenerator/Views/blocks/frontend/demo_creator.php

use Zero\Support\Str;
use Zero\Core\App;

$content = $block['content'] ?? 'Experience the power of Zero CMS. Spin up a fully functional, transaction-isolated, multi-tenant sandbox instance with pre-populated demo data in under 5 seconds.';
?>
<div class="block-demo-creator block-row container">
    <!-- Explanatory Content -->
    <div class="demo-description-wrapper font-body-md" style="margin-bottom: 30px;">
        <?php echo $content; ?>
    </div>

    <!-- Form Container -->
    <form class="demo-creator-form glass-panel">
        <div class="form-general-error alert-card error-card" style="display: none; margin-bottom: 20px;"></div>

        <!-- Email Input -->
        <div class="form-group">
            <label class="form-label" for="demo_email">Your Email Address *</label>
            <p class="form-desc">We will send your sandbox administrative credentials and login instructions directly to this email.</p>
            <input type="email" id="demo_email" name="email" class="form-input" placeholder="e.g. dev@yourcompany.com" required>
        </div>

        <!-- Preset Template Selection Grid -->
        <div class="form-group" style="margin-top: 30px;">
            <label class="form-label">Select Workspace Blueprint Template *</label>
            <p class="form-desc">Choose a pre-populated preset theme and database records blueprint to seed your sandbox workspace:</p>
            
            <div class="preset-cards-grid">
                <!-- Preset 1: Shop -->
                <label class="preset-card-label">
                    <input type="radio" name="preset" value="shop" checked class="preset-radio-input">
                    <div class="preset-card-inner glass-panel">
                        <div class="preset-card-header">
                            <span class="material-symbols-outlined preset-icon">shopping_cart</span>
                            <span class="preset-badge">E-Commerce</span>
                        </div>
                        <h4>Luxe Emporium</h4>
                        <p>Fully functional luxury e-commerce catalog featuring product variants, shopping carts, checkout states, and transactional ledger databases.</p>
                    </div>
                </label>

                <!-- Preset 2: KitchenSink -->
                <label class="preset-card-label">
                    <input type="radio" name="preset" value="kitchensink" class="preset-radio-input">
                    <div class="preset-card-inner glass-panel">
                        <div class="preset-card-header">
                            <span class="material-symbols-outlined preset-icon">package</span>
                            <span class="preset-badge">All Features</span>
                        </div>
                        <h4>Kitchen Sink Showroom</h4>
                        <p>Complete Zero CMS suite: E-commerce, community forums, article blogs with notifications, form builder submissions, and security audits combined.</p>
                    </div>
                </label>

                <!-- Preset 3: Corporate -->
                <label class="preset-card-label">
                    <input type="radio" name="preset" value="corporate" class="preset-radio-input">
                    <div class="preset-card-inner glass-panel">
                        <div class="preset-card-header">
                            <span class="material-symbols-outlined preset-icon">home</span>
                            <span class="preset-badge">Corporate</span>
                        </div>
                        <h4>Corporate Blueprint</h4>
                        <p>Clean multi-page corporate website layout containing baseline video heroes, testimonials, capability grids, contact forms, and company blogs.</p>
                    </div>
                </label>

                <!-- Preset 4: Portfolio -->
                <label class="preset-card-label">
                    <input type="radio" name="preset" value="portfolio" class="preset-radio-input">
                    <div class="preset-card-inner glass-panel">
                        <div class="preset-card-header">
                            <span class="material-symbols-outlined preset-icon">image</span>
                            <span class="preset-badge">Creative</span>
                        </div>
                        <h4>Designer Portfolio</h4>
                        <p>Sleek, high-impact creative portfolio displaying masonry layouts, imagery sliders, customizable columns, and focus-pointed visual grids.</p>
                    </div>
                </label>
            </div>
        </div>

        <!-- Progressive Loading Indicator -->
        <div class="demo-progress-indicator" style="display: none; margin-top: 30px;">
            <div class="demo-progress-bar-wrapper">
                <div class="demo-progress-bar-fill"></div>
            </div>
            <p class="demo-progress-text font-body-sm">Assembling isolated tenant environment...</p>
        </div>

        <!-- Submission Trigger -->
        <div class="form-actions" style="margin-top: 30px;">
            <button type="submit" class="btn-primary submit-btn">
                Assemble Sandbox Workspace
            </button>
        </div>
    </form>

    <!-- Success Credentials Display Card (Initially Hidden) -->
    <div class="demo-success-card glass-panel" style="display: none; margin-top: 20px;">
        <div class="success-header">
            <span class="material-symbols-outlined success-icon-glow">shield</span>
            <h3>Sandbox Tenant Generated with 100% Success!</h3>
        </div>
        
        <p class="font-body-md" style="margin-top: 15px;">
            We have dynamically provisioned and seeded your isolated Zero CMS multi-tenant sandbox! We have also dispatched these temporary credentials to your inbox.
        </p>

        <div class="credentials-box" style="margin-top: 25px;">
            <div class="credential-row">
                <span class="cred-label">Sandbox URL:</span>
                <span class="cred-value"><a class="success-domain-link" target="_blank" href="#">http://demo-abc.d6laptop.zero</a></span>
            </div>
            <div class="credential-row">
                <span class="cred-label">Admin Panel:</span>
                <span class="cred-value"><code>/admin/dashboard</code></span>
            </div>
            <div class="credential-row">
                <span class="cred-label">Username:</span>
                <span class="cred-value"><code class="success-username-code">admin</code></span>
            </div>
            <div class="credential-row">
                <span class="cred-label">Password:</span>
                <span class="cred-value"><strong class="success-password-code">abcde</strong></span>
            </div>
        </div>

        <div style="margin-top: 30px; display: flex; gap: 16px;">
            <a class="success-admin-link btn-primary" target="_blank" href="#">
                Enter Admin Panel
            </a>
            <a href="/docs/demo" class="btn-secondary">
                Spin Up Another Demo
            </a>
        </div>
    </div>
</div>
