<?php

namespace Zero\Services\Ai\Providers;

use Zero\Interfaces\AiProvider;
use Zero\Core\Env;
use Exception;

class GeminiProvider implements AiProvider
{
    /**
     * Generate content/text from a prompt using Google Gemini API.
     *
     * @param string $prompt The prompt string.
     * @param array $options Optional configuration parameters.
     * @return string Generated content.
     * @throws Exception On failure.
     */
    public function generate(string $prompt, array $options = []): string
    {
        $apiKey = $options['api_key'] ?? Env::get('GEMINI_API_KEY');
        if (empty($apiKey)) {
            throw new Exception("Gemini API key is not configured. Please set GEMINI_API_KEY in your .env file.");
        }

        $model = $options['model'] ?? 'gemini-2.5-flash';
        $timeout = $options['timeout'] ?? 60;

        $url = "https://generativelanguage.googleapis.com/v1beta/models/" . urlencode($model) . ":generateContent?key=" . urlencode($apiKey);
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if (!empty($curlError)) {
            throw new Exception("cURL Network Issue: " . $curlError);
        }

        if ($httpCode !== 200) {
            $resData = json_decode($response, true);
            $errorMsg = $resData['error']['message'] ?? 'Unknown Google API Error';
            throw new Exception("Google Gemini API Error (HTTP {$httpCode}): " . $errorMsg);
        }

        if (empty($response)) {
            throw new Exception("Empty response returned from Google Gemini API.");
        }

        $resData = json_decode($response, true);
        $generatedText = $resData['candidates'][0]['content']['parts'][0]['text'] ?? '';

        if (empty($generatedText)) {
            throw new Exception("Malformed JSON or missing generative text structure in response.");
        }

        return $generatedText;
    }
}
