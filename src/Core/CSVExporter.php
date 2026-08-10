<?php
/**
 * File: src/Core/CSVExporter.php
 * Architectural Purpose: Core bootstrapping, system environment configuration, and utility class of the framework.
 * Package: Zero\Core
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */



namespace Zero\Core;

/**
 * Class CSVExporter
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class CSVExporter
{
    /**
     * Export an array of associative records directly as a CSV file attachment download.
     * Maps keys to column headers automatically or uses custom headers.
     *
     * @param string $filename The name of the file to download (e.g. 'export.csv')
     * @param array $records An array of associative arrays or objects to export
     * @param array $headers Optional custom headers mapping (e.g. ['id' => 'ID', 'created_at' => 'Timestamp'])
     * @param bool $exit Optional whether to terminate execution after outputting
     */
    public static function download(string $filename, array $records, array $headers = [], bool $exit = true): void
    {
        // Enforce CSV download headers
        if (!headers_sent()) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
            header('Pragma: no-cache');
            header('Expires: 0');
        }

        $out = fopen('php://output', 'w');

        // Add UTF-8 BOM for Excel compatibility
        fwrite($out, "\xEF\xBB\xBF");

        if (empty($records)) {
            // If empty, just output headers or empty
            if (!empty($headers)) {
                fputcsv($out, array_values($headers));
            }
            fclose($out);
            if ($exit) {
                exit;
            }
            return;
        }

        // Determine column keys and headers
        $keys = [];
        if (!empty($headers)) {
            $keys = array_keys($headers);
            fputcsv($out, array_values($headers));
        } else {
            // Infer keys and headers from first record
            $first = reset($records);
            $recordArray = is_object($first) ? get_object_vars($first) : $first;
            $keys = array_keys($recordArray);
            
            // Format column names beautifully (e.g., 'created_at' -> 'Created At')
            $formattedHeaders = array_map(function($key) {
                return ucwords(str_replace('_', ' ', $key));
            }, $keys);
            fputcsv($out, $formattedHeaders);
        }

        // Loop and write rows
        foreach ($records as $record) {
            $rowArray = is_object($record) ? get_object_vars($record) : $record;
            $row = [];
            foreach ($keys as $key) {
                $val = $rowArray[$key] ?? '';
                if (is_array($val) || is_object($val)) {
                    $val = json_encode($val, JSON_UNESCAPED_SLASHES);
                }
                $row[] = $val;
            }
            fputcsv($out, $row);
        }

        fclose($out);
        if ($exit) {
            exit;
        }
    }
}
