<?php

declare(strict_types=1);

/**
 * File: src/Modules/FormBuilder/Controllers/FormApiController.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\FormBuilder\Controllers
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\FormBuilder\Controllers;

use Zero\Core\App;
use Zero\Core\Validator;
use Zero\Database\DB;
use Zero\Interfaces\Controller;
use Zero\Support\Emailer;
use Zero\Support\Security;
use Zero\Support\Str;

/**
 * Class FormApiController
 *
 * Receives public form submissions at /api/v1/contact/submit. Compiles the validation rules from
 * the stored form-builder block's own field schema, archives the accepted submission, and notifies
 * the recipients configured on the block -- which are resolved server-side from the block id,
 * never sent to the browser. A filled honeypot field is answered with an ordinary success response
 * and discarded.
 */
class FormApiController implements Controller
{
    /**
     * Handles the incoming HTTP action request context and dispatches response frames.
     *
     * @param mixed $param Argument descriptor.
     * @return mixed Response output.
     */
    public function handle($param)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->respond(405, ['success' => false, 'error' => 'Method Not Allowed']);
        }

        \header('Content-Type: application/json');

        $json = \json_decode(\file_get_contents('php://input'), true);
        if (!\is_array($json)) {
            $this->respond(400, ['success' => false, 'error' => 'Invalid JSON Payload']);
        }

        // SPAM HONEYPOT TRAP: If the hidden bait field is filled, silently discard
        if (!empty($json['website_url'])) {
            $this->respond(200, ['success' => true, 'note' => 'Spam filtered successfully.']);
        }

        // DYNAMIC RATE LIMITER MIDDLEWARE: Prevent form submission flood abuse (site-configurable window)
        $rateLimitSeconds = (int)App::getModuleSetting('formbuilder', 'submission_rate_limit_seconds', 10);
        App::applyRateLimitMiddleware('form_submission', $rateLimitSeconds);

        $siteId = App::getCurrentSiteId();
        $blockId = $json['block_id'] ?? '';

        $resolved = $this->resolveBlock($siteId, $blockId);
        if ($resolved === null) {
            $this->respond(404, ['success' => false, 'error' => 'Form configuration not found.']);
        }
        [$matchedBlock, $sourcePageTitle] = $resolved;
        $configuredFields = $matchedBlock['items'] ?? [];

        $validator = new Validator($json, $this->buildValidationRules($configuredFields));
        if (!$validator->validate()) {
            $this->respond(400, ['success' => false, 'errors' => $validator->getErrors()]);
        }

        $extracted = $this->extractSubmissionData($configuredFields, $json);
        $formTitle = $matchedBlock['title'] ?? 'Contact Form';
        $extracted['details']['_meta_form_title'] = $formTitle;
        $extracted['details']['_meta_source_page'] = $sourcePageTitle;
        $messagePayload = \json_encode($extracted['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $submissionId = Security::uuidv7();

        try {
            DB::query("
                INSERT INTO form_submissions (id, site_id, name, email, phone, message, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ", [
                $submissionId,
                $siteId,
                $extracted['senderName'],
                $extracted['senderEmail'],
                $extracted['senderPhone'],
                $messagePayload
            ]);
        } catch (\Exception $e) {
            \error_log("FormApiController: failed to save submission for block '{$blockId}' (site {$siteId}): " . $e->getMessage());
            $this->respond(500, ['success' => false, 'error' => 'Could not save the submission. Please try again later.']);
        }

        $this->notifyRecipient($matchedBlock, $blockId, $siteId, $formTitle, $sourcePageTitle, $extracted['details']);

        $this->respond(200, ['success' => true, 'id' => $submissionId]);
    }

    /**
     * Write a JSON response with the given status code and terminate the request.
     *
     * @param int $status
     * @param array $payload
     * @return never
     */
    private function respond(int $status, array $payload)
    {
        \http_response_code($status);
        \header('Content-Type: application/json');
        echo \json_encode($payload);
        exit;
    }

    /**
     * Locate the form-builder block matching $blockId among the site's published pages.
     *
     * @param string $siteId
     * @param string $blockId
     * @return array{0: array, 1: string}|null [$block, $sourcePageTitle], or null if not found.
     */
    private function resolveBlock(string $siteId, string $blockId): ?array
    {
        if (empty($blockId)) {
            return null;
        }

        $pages = DB::query("SELECT title, content FROM pages WHERE site_id = ? AND status = 'published' AND deleted_at IS NULL", [$siteId])->fetchAll();
        foreach ($pages as $p) {
            $blocks = \json_decode($p['content'], true);
            if (!\is_array($blocks)) {
                continue;
            }
            foreach ($blocks as $b) {
                if (($b['type'] ?? '') === 'form_builder' && ($b['id'] ?? '') === $blockId) {
                    return [$b, $p['title']];
                }
            }
        }

        return null;
    }

    /**
     * Build Validator rule strings from the block's configured field schema.
     *
     * @param array $configuredFields
     * @return array<string, string>
     */
    private function buildValidationRules(array $configuredFields): array
    {
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
                $rules[$fieldName] = \implode('|', $fieldRules);
            }
        }
        return $rules;
    }

    /**
     * Cast every configured field's submitted value and derive the submission's standard columns
     * (sender name/email/phone) plus the human-readable details dictionary archived in 'message'.
     *
     * @param array $configuredFields
     * @param array $json
     * @return array{senderName: string, senderEmail: string, senderPhone: ?string, details: array}
     */
    private function extractSubmissionData(array $configuredFields, array $json): array
    {
        // Canonical field-name candidates (delimiters stripped) recognised as "the sender's name" --
        // an exact allow-list rather than a substring match, so an admin-configured field like
        // "company_name" or "username" isn't mistaken for the visitor's own name.
        $nameFieldAliases = ['name', 'fullname', 'yourname', 'contactname', 'firstname'];

        $senderName = 'Form Submission';
        $senderEmail = 'anonymous@guest.cms';
        $senderPhone = null;
        $submissionDetails = [];

        foreach ($configuredFields as $fieldObj) {
            $name = $fieldObj['name'] ?? '';
            $label = $fieldObj['label'] ?? '';
            $type = $fieldObj['type'] ?? 'text';

            if (empty($name)) continue;

            // Translate FormBuilder's own vocabulary to the FormField registry's distinct group
            // types (its 'checkbox' means a GROUP of checkboxes, not a single boolean toggle),
            // and reshape a configured select's plain option-string list into the associative
            // value=>label shape (with a blank placeholder) so an intentionally-unselected
            // optional dropdown still casts through as a valid submission.
            $resolvedType = $type;
            $fieldOptions = [];
            if ($type === 'checkbox') {
                $resolvedType = 'checkbox_group';
            } elseif ($type === 'radio') {
                $resolvedType = 'radio_group';
            } elseif ($type === 'select') {
                $optionsStr = $fieldObj['options'] ?? '';
                $options = !empty($optionsStr) ? \array_map('trim', \explode(',', $optionsStr)) : [];
                $fieldOptions = ['' => '-- Select Option --'] + \array_combine($options, $options);
            }

            $field = App::makeFormField($resolvedType, $name, ['options' => $fieldOptions]);
            $val = $field->castSubmittedValue($json);

            // Map standard column fields dynamically
            if ($type === 'email' && !empty($val)) {
                $senderEmail = $val;
            }
            if ($type === 'tel' && !empty($val)) {
                $senderPhone = $val;
            }
            $normalizedName = \strtolower(\preg_replace('/[^a-z0-9]/i', '', $name));
            if (\in_array($normalizedName, $nameFieldAliases, true) && $senderName === 'Form Submission' && !empty($val)) {
                $senderName = \is_array($val) ? \implode(', ', $val) : $val;
            }

            // Save formatted visual value for the list/email body (json-encode arrays for multi-selects/checkboxes)
            $displayVal = \is_array($val) ? \implode(', ', $val) : $val;
            $submissionDetails[$label] = $displayVal ?? 'N/A';
        }

        return [
            'senderName' => $senderName,
            'senderEmail' => $senderEmail,
            'senderPhone' => $senderPhone,
            'details' => $submissionDetails,
        ];
    }

    /**
     * Email the block's configured recipient about a new submission. If no recipient is
     * configured, the submission is still archived (already done by the caller) -- there's simply
     * no address to guess, so this logs the gap instead of notifying an invented placeholder.
     *
     * @param array $matchedBlock
     * @param string $blockId
     * @param string $siteId
     * @param string $formTitle
     * @param string $sourcePageTitle
     * @param array $submissionDetails
     * @return void
     */
    private function notifyRecipient(array $matchedBlock, string $blockId, string $siteId, string $formTitle, string $sourcePageTitle, array $submissionDetails): void
    {
        $recipientEmail = $matchedBlock['recipient_email'] ?? '';
        if (empty($recipientEmail)) {
            \error_log("FormApiController: block '{$blockId}' (site {$siteId}) has no recipient_email configured; submission saved but no notification sent.");
            return;
        }

        $subject = "New Submission: " . $formTitle;
        $htmlBody = "
            <h2>New Dynamic Form Submission</h2>
            <p>A new form has been submitted on your site page: <strong>" . Str::escape($sourcePageTitle) . "</strong>.</p>
            <hr style='border: none; border-top: 1px solid #ddd; margin: 15px 0;'>
        ";

        foreach ($submissionDetails as $label => $val) {
            if (\strpos($label, '_meta_') === 0) continue; // skip metadata keys in email
            $htmlBody .= "<p><strong>" . Str::escape($label) . ":</strong> " . \nl2br(Str::escape($val)) . "</p>";
        }

        if (!Emailer::send($recipientEmail, $subject, $htmlBody)) {
            \error_log("FormApiController: notification email failed to send for block '{$blockId}' (site {$siteId}) to '{$recipientEmail}'.");
        }
    }
}
