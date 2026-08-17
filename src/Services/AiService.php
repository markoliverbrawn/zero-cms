<?php

declare(strict_types=1);

/**
 * File: src/Services/AiService.php
 * Architectural Purpose: Handles operations and business logic within the system.
 * Package: Zero\Services
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Services;

use Exception;
use Zero\Core\Env;
use Zero\Interfaces\AiProvider;

/**
 * Class AiService
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class AiService
{
    /**
     * @var AiProvider[]
     */
    protected static array $customProviders = [];

    /**
     * @var AiProvider|null
     */
    protected static ?AiProvider $providerInstance = null;

    /**
     * Generate content using the active AI provider.
     *
     * @param string $prompt The prompt text.
     * @param array $options Optional configuration override.
     * @return string
     * @throws Exception
     */
    public static function generate(string $prompt, array $options = []): string
    {
        return self::getProvider()->generate($prompt, $options);
    }

    /**
     * Generate an image using the active AI provider.
     * Returns the raw generated image bytes encoded as a Base64 string.
     *
     * @param string $prompt The image generation prompt description.
     * @param array $options Optional configuration override parameters.
     * @return string Generated image Base64 string.
     * @throws Exception
     */
    public static function generateImage(string $prompt, array $options = []): string
    {
        return self::getProvider()->generateImage($prompt, $options);
    }

    /**
     * Get the active AI provider instance.
     *
     * @return AiProvider
     * @throws Exception
     */
    public static function getProvider(): AiProvider
    {
        if (self::$providerInstance === null) {
            $providerName = Env::get('AI_PROVIDER', 'gemini');

            if (isset(self::$customProviders[$providerName])) {
                self::$providerInstance = self::$customProviders[$providerName];
            } elseif ($providerName === 'gemini') {
                require_once APPLICATION_ROOT . '/src/Services/Ai/Providers/GeminiProvider.php';
                self::$providerInstance = new \Zero\Services\Ai\Providers\GeminiProvider();
            } elseif ($providerName === 'mock') {
                require_once APPLICATION_ROOT . '/src/Services/Ai/Providers/MockProvider.php';
                self::$providerInstance = new \Zero\Services\Ai\Providers\MockProvider();
            } else {
                throw new Exception("Unsupported AI provider configured: {$providerName}");
            }
        }
        return self::$providerInstance;
    }

    /**
     * Check if an AI provider is configured and available.
     *
     * @return bool
     */
    public static function isAvailable(): bool
    {
        try {
            $providerName = Env::get('AI_PROVIDER', 'gemini');
            if ($providerName === 'mock') {
                return true;
            }
            if ($providerName === 'gemini') {
                return !empty(Env::get('GEMINI_API_KEY'));
            }
            if (isset(self::$customProviders[$providerName])) {
                return true;
            }
        } catch (Exception $e) {
            return false;
        }
        return false;
    }

    /**
     * Register a custom AI provider dynamically.
     *
     * @param string $name
     * @param AiProvider $provider
     * @return void
     */
    public static function registerProvider(string $name, AiProvider $provider): void
    {
        self::$customProviders[$name] = $provider;
    }
}
