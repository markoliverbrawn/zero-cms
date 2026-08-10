<?php

declare(strict_types=1);

/**
 * File: src/Interfaces/AiProvider.php
 * Architectural Purpose: Handles operations and business logic within the system.
 * Package: Zero\Interfaces
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Interfaces;

/**
 * Interface AiProvider
 *
 * Defines systemic behavioral interface contract mechanisms.
 */
interface AiProvider
{
    /**
     * Generate content/text from a prompt.
     *
     * @param string $prompt The prompt string.
     * @param array $options Optional configuration parameters.
     * @return string Generated content.
     * @throws \Exception On failure.
     */
    public function generate(string $prompt, array $options = []): string;

    /**
     * Generate an image from a prompt.
     * Returns the raw generated image bytes encoded as a Base64 string.
     * MUST be 100% database-query free to prevent N+1 queries.
     *
     * @param string $prompt The image generation prompt description.
     * @param array $options Optional configuration parameters (such as aspect_ratio, model, size).
     * @return string Generated image as a Base64 encoded string.
     * @throws \Exception On failure.
     */
    public function generateImage(string $prompt, array $options = []): string;
}
