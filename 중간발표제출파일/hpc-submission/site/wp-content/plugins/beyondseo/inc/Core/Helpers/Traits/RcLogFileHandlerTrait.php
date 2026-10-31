<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Helpers\Traits;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use SplFileObject;

/**
 * Trait RcLogFileHandlerTrait
 * 
 * Provides utility methods for handling log files, including reading, formatting,
 * and extracting information from log files.
 * 
 * @package RankingCoach\Inc\Core\Helpers\Traits
 */
trait RcLogFileHandlerTrait
{
    /**
     * Get the most recent file from an array of files.
     *
     * @param array $files Array of file paths
     * @return string|null The path to the most recent file, or null if the array is empty
     */
    private function getLatestFile(array $files): ?string
    {
        if (empty($files)) {
            return null;
        }

        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        return $files[0];
    }

    /**
     * Get the last N lines of a file.
     *
     * @param string $filePath Path to the file
     * @param int $lines Number of lines to read from the end
     * @return string The last N lines of the file
     */
    private function getTailContent(string $filePath, int $lines): string
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return 'File not found or not readable.';
        }

        $file = new SplFileObject($filePath, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();

        $startLine = max(0, $totalLines - $lines);
        $content = [];

        $file->seek($startLine);
        while (!$file->eof()) {
            $line = $file->fgets();
            if ($line !== false) {
                $content[] = $line;
            }
        }

        return implode('', $content);
    }

    /**
     * Format file size in human-readable format.
     *
     * @param int $bytes File size in bytes
     * @return string Formatted file size
     */
    private function formatFileSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        } elseif ($bytes < 1048576) {
            return round($bytes / 1024, 2) . ' KB';
        } elseif ($bytes < 1073741824) {
            return round($bytes / 1048576, 2) . ' MB';
        } else {
            return round($bytes / 1073741824, 2) . ' GB';
        }
    }

    /**
     * Get the last N lines from a special log file.
     *
     * @param string $filePath Path to the special log file
     * @param int $lines Number of lines to read from the end
     * @return array Array of log lines
     */
    private function getSpecialLogLines(string $filePath, int $lines): array
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return ['File not found or not readable.'];
        }

        $file = new SplFileObject($filePath, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();

        $startLine = max(0, $totalLines - $lines);
        $content = [];

        $file->seek($startLine);
        while (!$file->eof()) {
            $line = $file->fgets();
            if ($line !== false) {
                $content[] = $line;
            }
        }

        return $content;
    }

    /**
     * Extract timestamp from a log line.
     * 
     * Example log line format:
     * "[2025-05-22 11:27:27] [SAVE-SUMMARY] [App\Domain\Integrations\WordPress\Setup\Services\WPRequirementsService::processRequirements] { ... }"
     * 
     * Extracts: "[2025-05-22 11:27:27]"
     *
     * @param string $logLine The log line to extract timestamp from
     * @return string The extracted timestamp
     */
    private function extractTimestamp(string $logLine): string
    {
        // Default timestamp if extraction fails
        $defaultTimestamp = '';

        // Check if the line is empty
        if (empty($logLine)) {
            return $defaultTimestamp;
        }

        // Regular expression to match the timestamp pattern
        $pattern = '/(\[\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00\])/';

        if (preg_match($pattern, $logLine, $matches) && isset($matches[1])) {
            // replace "[" and "]" with empty string
            return str_replace(['[', ']'], '', $matches[1]);
        }

        return $defaultTimestamp;
    }

    /**
     * Extract headline from a log line.
     * 
     * Example log line format:
     * "[2025-05-22 11:27:27] [SAVE-SUMMARY] [App\Domain\Integrations\WordPress\Setup\Services\WPRequirementsService::processRequirements] { ... }"
     * 
     * Extracts: "[SAVE-SUMMARY] [App\Domain\Integrations\WordPress\Setup\Services\WPRequirementsService::processRequirements]"
     *
     * @param string $logLine The log line to extract headline from
     * @return string The extracted headline
     */
    private function extractHeadline(string $logLine): string
    {
        // Check if the line is empty
        if (empty($logLine)) {
            return '';
        }

        // Regular expression to match the headline pattern
        $pattern = '/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] (\[(.*?)\] \[(.*?)\])/';

        if (preg_match($pattern, $logLine, $matches) && isset($matches[1])) {
            // Extract the keyword and class path parts
            $keyword = $matches[2] ?? '';
            $classPath = $matches[3] ?? '';

            // Generate a color for the badge based on the keyword
            $badgeColor = $this->getBadgeColorForKeyword($keyword);

            // Format the keyword as a badge with the determined color
            $formattedKeyword = sprintf(
                '<span style="display: inline-block; padding: 2px 6px; border-radius: 4px; background-color: %s; color: white; font-weight: bold;">%s</span>',
                $badgeColor,
                esc_html($keyword)
            );

            // Format the class path (remove square brackets)
            $formattedClassPath = esc_html($classPath);

            // Return the formatted headline
            return sprintf('%s %s', $formattedKeyword, $formattedClassPath);
        }

        // If the pattern doesn't match, return a shortened version of the log line
        return (strlen($logLine) > 100) ? substr($logLine, 0, 97) . '...' : $logLine;
    }

    /**
     * Get a color for a badge based on the keyword.
     *
     * @param string $keyword The keyword to get a color for
     * @return string The color in hex format
     */
    private function getBadgeColorForKeyword(string $keyword): string
    {
        // Define color mapping for different types of keywords
        $colorMap = [
            'SAVE' => '#4CAF50',        // Green
            'DETECT' => '#2196F3',      // Blue
            'ERROR' => '#F44336',       // Red
            'AUTOSUGGEST' => '#FF9800',     // Orange
            'INFO' => '#607D8B',        // Blue Grey
            'DEBUG' => '#9E9E9E',       // Grey
            'NOTICE' => '#9C27B0',      // Purple
            'CRITICAL' => '#D32F2F',    // Dark Red
            'ALERT' => '#FF5722',       // Deep Orange
            'EMERGENCY' => '#B71C1C',   // Very Dark Red
        ];

        // Check if the keyword contains any of the defined prefixes
        foreach ($colorMap as $prefix => $color) {
            if (strpos($keyword, $prefix) !== false) {
                return $color;
            }
        }

        // Default color if no match is found
        return '#3498db'; // Default blue
    }
}