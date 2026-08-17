<?php

declare(strict_types=1);

/**
 * File: src/Modules/Admin/Controllers/Api/AiApiController.php
 * Architectural Purpose: REST API endpoint exposing AI-assisted content generation features
 * (e.g. block/page summary generation) to the admin back-office.
 * Package: Zero\Modules\Admin\Controllers\Api
 */

namespace Zero\Modules\Admin\Controllers\Api;

use Zero\Services\AiService;

/**
 * Class AiApiController
 */
class AiApiController extends AdminApiControllerBase
{
    /**
     * Handles the incoming HTTP action request context and dispatches response frames.
     *
     * @param mixed $matches Argument descriptor.
     * @return mixed Response output.
     */
    public function handle($matches)
    {
        $this->authenticate();
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = \parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $body = $this->parseBody();

        if (\preg_match('#^/api/v1/admin/ai/generate-summary/?$#', $uri) && $method === 'POST') {
            $this->handleAiGenerateSummary($body);
        }

        $this->respond(['success' => false, 'error' => 'Endpoint not found or method not allowed'], 404);
    }

    /**
     * Handle ai generate summary processing implementation helper.
     *
     * @param mixed $body Argument descriptor.
     * @return mixed Response output.
     */
    protected function handleAiGenerateSummary($body)
    {
        $content = $body['content'] ?? '';
        if (empty($content)) {
            $this->respond([
                'success' => false,
                'error' => 'No block content provided to generate summary from.'
            ], 400);
        }

        $prompt = "You are an expert copywriter. Generate a concise, engaging, single-paragraph summary (under 250 characters) summarizing the following content of a web page/blog post. Do not include any HTML tags, emojis, markdown, introductory phrases, or conversational filler. Output ONLY the raw paragraph text:\n\n" . $content;

        try {
            if (!AiService::isAvailable()) {
                throw new \Exception("AI Provider is not configured or available.");
            }
            $summary = AiService::generate($prompt);
            $this->respond([
                'success' => true,
                'summary' => \trim($summary)
            ]);
        } catch (\Exception $e) {
            $this->respond([
                'success' => false,
                'error' => 'AI Generation Failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
