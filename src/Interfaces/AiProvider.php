<?php

namespace Zero\Interfaces;

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
}
