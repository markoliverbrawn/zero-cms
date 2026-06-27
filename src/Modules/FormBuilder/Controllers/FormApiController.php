<?php

namespace Zero\Modules\FormBuilder\Controllers;

use Zero\Core\App;
use Zero\Core\Validator;
use Zero\Database\DB;
use Zero\Support\Security;
use Zero\Support\Emailer;
use Zero\Interfaces\Controller;

class FormApiController implements Controller
{
    public function handle($param)
    {
        // Enforce POST requests
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
            exit;
        }

        header('Content-Type: application/json');

        // Parse JSON raw payload input
        $json = json_decode(file_get_contents('php://input'), true);
        if (!is_array($json)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid JSON Payload']);
            exit;
        }

        // SPAM HONEYPOT TRAP: If the hidden bait field is filled, silently discard
        if (!empty($json['website_url'])) {
            echo json_encode([
                'success' => true,
                'note' => 'Spam filtered successfully.'
            ]);
            exit;
        }

        // DYNAMIC RATE LIMITER MIDDLEWARE: Prevent form submission flood abuse (limit to 1 request per 10 seconds per session)
        App::applyRateLimitMiddleware('form_submission', 10);

        $siteId = App::getCurrentSiteId();
        $blockId = $json['block_id'] ?? '';
        $matchedBlock = null;
        $sourcePageTitle = 'Contact Page';

        // 1. Resolve and load the block configuration dynamically from the database
        if (!empty($blockId)) {
            // Check pages first
            $pages = DB::query("SELECT title, content FROM pages WHERE site_id = ? AND deleted_at IS NULL", [$siteId])->fetchAll();
            foreach ($pages as $p) {
                $blocks = json_decode($p['content'], true);
                if (is_array($blocks)) {
                    foreach ($blocks as $b) {
                        if (($b['type'] ?? '') === 'contact_form' && ($b['id'] ?? '') === $blockId) {
                            $matchedBlock = $b;
                            $sourcePageTitle = $p['title'];
                            break 2;
                        }
                    }
                }
            }

            // Fallback to blog posts
            if (!$matchedBlock) {
                $posts = DB::query("SELECT title, content FROM blog_posts WHERE site_id = ? AND deleted_at IS NULL", [$siteId])->fetchAll();
                foreach ($posts as $po) {
                    $blocks = json_decode($po['content'], true);
                    if (is_array($blocks)) {
                        foreach ($blocks as $b) {
                            if (($b['type'] ?? '') === 'contact_form' && ($b['id'] ?? '') === $blockId) {
                                $matchedBlock = $b;
                                $sourcePageTitle = $po['title'];
                                break 2;
                            }
                        }
                    }
                }
            }
        }

        if (!$matchedBlock) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Form configuration not found.']);
            exit;
        }

        // 2. Build validation rules dynamically from the configured fields schema
        $configuredFields = $matchedBlock['items'] ?? [];
        $rules = [];
        foreach ($configuredFields as $fieldObj) {
            $fieldName = $fieldObj['name'] ?? '';
            if (empty($fieldName)) continue;

            $fieldRules = [];
            if (($fieldObj['required'] ?? '0') === '1') {
                $fieldRules[] = 'required';
            }

            $valType = $fieldObj['validation'] ?? 'none';
            if ($valType !== 'none') {
                $fieldRules[] = $valType;
            }

            if (!empty($fieldRules)) {
                $rules[$fieldName] = implode('|', $fieldRules);
            }
        }

        // Validate custom inputs using our extensible Core Validator
        $validator = new Validator($json, $rules);

        if (!$validator->validate()) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'errors' => $validator->getErrors()
            ]);
            exit;
        }

        // Extract validated and filtered data payload
        $validatedData = $validator->getValidatedData();

        // 3. Extract metadata dynamically for database columns
        $senderName = 'Form Submission';
        $senderEmail = 'anonymous@guest.cms';
        $senderPhone = null;
        $submissionDetails = [];

        foreach ($configuredFields as $fieldObj) {
            $name = $fieldObj['name'] ?? '';
            $label = $fieldObj['label'] ?? '';
            $type = $fieldObj['type'] ?? 'text';
            $val = $json[$name] ?? null;

            if (empty($name)) continue;

            // Map standard column fields dynamically
            if ($type === 'email' && !empty($val)) {
                $senderEmail = $val;
            }
            if ($type === 'tel' && !empty($val)) {
                $senderPhone = $val;
            }
            if ((strpos($name, 'name') !== false || $type === 'text') && $senderName === 'Form Submission' && !empty($val)) {
                $senderName = is_array($val) ? implode(', ', $val) : $val;
            }

            // Save formatted visual value for the list/email body (json-encode arrays for multi-selects/checkboxes)
            $displayVal = is_array($val) ? implode(', ', $val) : $val;
            $submissionDetails[$label] = $displayVal ?? 'N/A';
        }

        // Inject source page and form header metadata securely inside the serialized payload
        $formTitle = $matchedBlock['title'] ?? 'Contact Form';
        $submissionDetails['_meta_form_title'] = $formTitle;
        $submissionDetails['_meta_source_page'] = $sourcePageTitle;

        // Serialize fields dictionary into the 'message' TEXT column
        $messagePayload = json_encode($submissionDetails, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        // Resolve notification recipient email
        $recipientEmail = $matchedBlock['recipient_email'] ?? 'admin@d6laptop.zero';

        // Insert submission record into database
        $submissionId = Security::uuidv7();

        try {
            DB::query("
                INSERT INTO form_submissions (id, site_id, name, email, phone, message, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ", [
                $submissionId,
                $siteId,
                $senderName,
                $senderEmail,
                $senderPhone,
                $messagePayload
            ]);

            // Construct and dispatch HTML notification email
            $subject = "New Submission: " . $formTitle;
            $htmlBody = "
                <h2>New Dynamic Form Submission</h2>
                <p>A new form has been submitted on your site page: <strong>" . htmlspecialchars($sourcePageTitle, ENT_QUOTES, 'UTF-8') . "</strong>.</p>
                <hr style='border: none; border-top: 1px solid #ddd; margin: 15px 0;'>
            ";

            foreach ($submissionDetails as $label => $val) {
                if (strpos($label, '_meta_') === 0) continue; // skip metadata keys in email
                $htmlBody .= "<p><strong>" . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . ":</strong> " . nl2br(htmlspecialchars($val, ENT_QUOTES, 'UTF-8')) . "</p>";
            }

            Emailer::send($recipientEmail, $subject, $htmlBody);

            echo json_encode([
                'success' => true,
                'id' => $submissionId
            ]);
            exit;
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Could not save the submission: ' . $e->getMessage()
            ]);
            exit;
        }
    }
}
