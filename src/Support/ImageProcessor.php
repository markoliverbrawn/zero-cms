<?php

declare(strict_types=1);

/**
 * File: src/Support/ImageProcessor.php
 * Architectural Purpose: Global diagnostic tools, cryptographic security handlers, SMTP email transmitters, and text helpers.
 * Package: Zero\Support
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Support;

/**
 * Class ImageProcessor
 *
 * The GD-backed rasterizer behind the image variant cache: decodes source bytes, resamples them
 * according to geometry decided by Assets, and re-encodes the result as WebP.
 *
 * Kept deliberately separate from Assets so that URL minting stays pure computation and this --
 * the only genuinely expensive part of the pipeline -- is reached exclusively on a cache miss.
 * Every operation works on in-memory byte strings rather than paths, so the same code serves
 * the local filesystem driver and the cloud drivers without branching on storage backend.
 *
 * GD reports most failures by emitting a PHP warning and returning false, which would corrupt a
 * binary HTTP response if it were allowed to reach the output stream. Rather than silencing that
 * with error suppression, every GD entry point here runs inside a handler that promotes warnings
 * to exceptions, so a malformed image fails loudly and cleanly.
 */
class ImageProcessor
{
    /**
     * Largest source image this will attempt to decode, as a total pixel count.
     *
     * A truecolor GD canvas costs four bytes per pixel and a resize holds both the source and
     * the destination at once, so this is what keeps a maliciously dimensioned "decompression
     * bomb" from exhausting the process memory limit. Ordinary uploads are already downscaled to
     * 1200px on their longest edge, roughly 1.4 megapixels, so this ceiling only ever engages on
     * files that predate that treatment or arrived through another path.
     */
    public const MAX_SOURCE_PIXELS = 16000000;

    /**
     * Decode raw image bytes into a truecolor GD image resource.
     *
     * Palette images are promoted to truecolor first: resampling an indexed image directly
     * produces severe banding, because GD has to quantize every interpolated pixel back into the
     * original limited palette.
     *
     * @param string $bytes Raw source image bytes.
     * @return \GdImage The decoded truecolor image.
     * @throws \Exception If the bytes are not a decodable image or exceed the pixel ceiling.
     */
    public static function decode(string $bytes): \GdImage
    {
        $probe = self::probe($bytes);
        if ($probe === null) {
            throw new \Exception('Image decode failed: the source bytes are not a recognizable image.');
        }

        $pixels = $probe['width'] * $probe['height'];
        if ($pixels > self::MAX_SOURCE_PIXELS) {
            throw new \Exception(
                "Image decode refused: source is {$probe['width']}x{$probe['height']} ({$pixels} pixels), "
                . 'which exceeds the ' . self::MAX_SOURCE_PIXELS . ' pixel processing ceiling.'
            );
        }

        $image = self::trapWarnings(static function () use ($bytes) {
            return \imagecreatefromstring($bytes);
        });

        if (!$image instanceof \GdImage) {
            throw new \Exception('Image decode failed: GD could not read the source bytes.');
        }

        if (!\imageistruecolor($image)) {
            self::trapWarnings(static function () use ($image) {
                return \imagepalettetotruecolor($image);
            });
        }

        return $image;
    }

    /**
     * Encode a GD image as WebP and return the resulting bytes.
     *
     * @param \GdImage $image The image to encode.
     * @param int $quality Encoder quality between 1 and 100.
     * @return string The encoded WebP bytes.
     * @throws \Exception If encoding fails or produces no output.
     */
    public static function encode(\GdImage $image, int $quality): string
    {
        \imagealphablending($image, false);
        \imagesavealpha($image, true);

        // imagewebp() writes to the output stream when handed a null path, so the encode is
        // captured through an output buffer. The buffer is torn down in every exit path,
        // including a promoted GD warning, so a failure can never leave it open and bleed
        // image bytes into whatever response is written next.
        \ob_start();
        try {
            $encoded = self::trapWarnings(static function () use ($image, $quality) {
                return \imagewebp($image, null, $quality);
            });
        } catch (\Throwable $exception) {
            \ob_end_clean();
            throw $exception;
        }
        $bytes = \ob_get_clean();

        if ($encoded !== true || !\is_string($bytes) || $bytes === '') {
            throw new \Exception('Image encode failed: GD produced no WebP output.');
        }

        return $bytes;
    }

    /**
     * Inspect raw image bytes for their intrinsic dimensions and mime type without decoding them.
     *
     * @param string $bytes Raw source image bytes.
     * @return array{width: int, height: int, mime: string}|null Null when the bytes are not an image.
     */
    public static function probe(string $bytes): ?array
    {
        if ($bytes === '') {
            return null;
        }

        $info = self::trapWarnings(static function () use ($bytes) {
            return \getimagesizefromstring($bytes);
        });

        if (!\is_array($info) || empty($info[0]) || empty($info[1])) {
            return null;
        }

        return [
            'width' => (int)$info[0],
            'height' => (int)$info[1],
            'mime' => (string)($info['mime'] ?? ''),
        ];
    }

    /**
     * Render a resized, optionally focal-point-cropped WebP rendition of a source image.
     *
     * The geometry itself is delegated to Assets so that the maths stays independently testable
     * and identical to whatever the URL promised; this method only performs the resampling.
     *
     * @param string $bytes Raw source image bytes.
     * @param int $targetWidth Requested width in pixels, or 0 for unconstrained.
     * @param int $targetHeight Requested height in pixels, or 0 for unconstrained.
     * @param string $fit Assets::FIT_COVER or Assets::FIT_CONTAIN.
     * @param int $focusX Horizontal focal point percentage (0-100).
     * @param int $focusY Vertical focal point percentage (0-100).
     * @param int $quality Encoder quality between 1 and 100.
     * @return array{bytes: string, width: int, height: int} The encoded variant and its true size.
     * @throws \Exception If the source cannot be decoded, resampled, or encoded.
     */
    public static function render(
        string $bytes,
        int $targetWidth,
        int $targetHeight,
        string $fit = Assets::FIT_COVER,
        int $focusX = 50,
        int $focusY = 50,
        int $quality = Assets::DEFAULT_QUALITY
    ): array {
        $source = self::decode($bytes);

        try {
            $geometry = Assets::geometry(
                \imagesx($source),
                \imagesy($source),
                $targetWidth,
                $targetHeight,
                $fit,
                $focusX,
                $focusY
            );

            $destination = self::trapWarnings(static function () use ($geometry) {
                return \imagecreatetruecolor($geometry['width'], $geometry['height']);
            });

            if (!$destination instanceof \GdImage) {
                throw new \Exception('Image render failed: could not allocate the destination canvas.');
            }

            try {
                // Preserve transparency rather than flattening onto a background colour: WebP
                // carries an alpha channel, so a cut-out PNG stays a cut-out after resizing.
                // Alpha blending stays off for the duration of the copy so that the source's
                // alpha values are transferred verbatim -- with blending enabled GD composites
                // each interpolated pixel against the canvas underneath, which fringes the
                // edges of a transparent image with a dark halo.
                \imagealphablending($destination, false);
                \imagesavealpha($destination, true);
                $transparent = \imagecolorallocatealpha($destination, 255, 255, 255, 127);
                if ($transparent !== false) {
                    \imagefill($destination, 0, 0, $transparent);
                }

                $resampled = self::trapWarnings(static function () use ($destination, $source, $geometry) {
                    return \imagecopyresampled(
                        $destination,
                        $source,
                        0,
                        0,
                        $geometry['source_x'],
                        $geometry['source_y'],
                        $geometry['width'],
                        $geometry['height'],
                        $geometry['source_width'],
                        $geometry['source_height']
                    );
                });

                if ($resampled !== true) {
                    throw new \Exception('Image render failed: GD could not resample the source image.');
                }

                return [
                    'bytes' => self::encode($destination, $quality),
                    'width' => $geometry['width'],
                    'height' => $geometry['height'],
                ];
            } finally {
                \imagedestroy($destination);
            }
        } finally {
            \imagedestroy($source);
        }
    }

    /**
     * Run a GD call with PHP warnings and notices promoted to exceptions.
     *
     * GD signals most failures through a warning plus a false return value. Left alone those
     * warnings would be written straight into the response body, which for an image endpoint
     * means a corrupt file rather than a clean error; suppressing them with the @ operator is
     * disallowed by the codebase's error-handling rules and would also discard the diagnostic.
     * Promoting them keeps the message and guarantees nothing leaks into the output stream.
     *
     * @param callable $operation The GD call to perform.
     * @return mixed Whatever the operation returned.
     * @throws \Exception If the operation raised a PHP warning, notice, or error.
     */
    protected static function trapWarnings(callable $operation)
    {
        \set_error_handler(static function (int $severity, string $message): bool {
            throw new \Exception('GD image operation failed: ' . $message);
        });

        try {
            return $operation();
        } finally {
            \restore_error_handler();
        }
    }
}
