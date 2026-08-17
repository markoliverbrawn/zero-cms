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
            <?php echo App::makeFormField('email', 'email', [
                'required' => true,
                'attributes' => ['id' => 'demo_email', 'class' => 'form-input', 'placeholder' => 'e.g. dev@yourcompany.com'],
                'showLabel' => false,
                'guessHelperTextKey' => false,
            ])->render(); ?>
        </div>

        <!-- Hidden Preset Mapping (Forces Kitchen Sink) -->
        <input type="hidden" name="preset" value="kitchensink">

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
