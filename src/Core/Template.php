<?php

declare(strict_types=1);

/**
 * File: src/Core/Template.php
 * Architectural Purpose: Core bootstrapping, system environment configuration, and utility class of the framework.
 * Package: Zero\Core
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Core;

use Zero\Models\Site;

/**
 * Class Template
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class Template
{
    /**
     * Render file processing implementation helper.
     *
     * @param mixed $templatePath Argument descriptor.
     * @param mixed $data Argument descriptor.
     * @return mixed Response output.
     */
    public static function renderFile($templatePath, $data = [])
    {
        // Ensure $templatePath is an absolute path from VIEWS_DIR
        // This assumes APPLICATION_ROOT and VIEWS_DIR are defined constants.
        if (\strpos($templatePath, '/') !== 0) { // If not already absolute
            // Resolve relative path inside active theme
            $siteId = App::getCurrentSiteId();
            require_once APPLICATION_ROOT . '/src/Models/Site.php';
            $site = Site::find($siteId);
            $theme = $site ? ($site->theme ?? 'default') : 'default';
            $templatePath = APPLICATION_ROOT . '/src/Views/themes/' . $theme . '/' . $templatePath;
        }

        // We assume templatePath now ends with .php
        $templatePath = \realpath($templatePath); // Resolve to absolute real path

        if ($templatePath === false) {
            // Handle error: template not found after path resolution
            \error_log("Template file not found after realpath: " . $templatePath);
            return '';
        }

        // extract data and capture output
        \extract($data, EXTR_SKIP);
        $oldError = \error_reporting();
        // suppress notices/warnings during template rendering to avoid undefined variable noise
        \error_reporting($oldError & ~E_NOTICE & ~E_WARNING);
        \ob_start();
        include $templatePath; // Directly include the PHP template file
        $out = \ob_get_clean();
        \error_reporting($oldError);
        return $out;
    }
}
