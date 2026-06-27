<?php

namespace Zero\Services;

use Zero\Core\Env;
use Zero\Interfaces\AiProvider;
use Exception;

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
