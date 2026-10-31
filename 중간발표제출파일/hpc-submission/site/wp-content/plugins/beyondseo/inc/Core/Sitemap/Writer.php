<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Sitemap;

if (!defined('ABSPATH')) {
    exit;
}

use Exception;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;

/**
 * Handles sitemap writing with comprehensive error handling
 */
class Writer
{
    use RcLoggerTrait;

    /**
     * @param string $type
     * @param string $xml
     * @return bool
     */
    public function write(string $type, string $xml): bool
    {
        try {
            $upload_dir = wp_upload_dir();

            if (!empty($upload_dir['error'])) {
                $this->log('Sitemap Writer: Upload directory error - ' . $upload_dir['error']);
                return false;
            }

            $basedir = trailingslashit($upload_dir['basedir']);
            $path    = $basedir . "sitemap-$type.xml";
            $tmpPath = $path . '.tmp';

            if (!is_dir($basedir)) {
                $this->log("Sitemap Writer: Upload directory does not exist - $basedir");
                return false;
            }

            if (!wp_is_writable($basedir)) {
                $this->log("Sitemap Writer: Upload directory not writable - $basedir");
                return false;
            }

            if (empty($xml) || !$this->isValidXML($xml)) {
                $this->log("Sitemap Writer: Invalid XML provided for type $type");
                return false;
            }

            // Write temp file first (atomic write strategy)
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            if (file_put_contents($tmpPath, $xml) === false) {
                $this->log("Sitemap Writer: Failed to write temp file - $tmpPath");
                return false;
            }

            // Atomic move
            // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
            if (!rename($tmpPath, $path)) {
                $this->log("Sitemap Writer: Failed to move temp file to final path - $path");
                // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
                @unlink($tmpPath);
                return false;
            }

            $this->log(
                sprintf(
                    'Sitemap Writer: Successfully wrote sitemap-%s.xml (%d bytes)',
                    $type,
                    strlen($xml)
                )
            );

            return true;

        } catch (Exception $e) {
            $this->log('Sitemap Writer: Exception - ' . $e->getMessage());

            if (isset($tmpPath) && file_exists($tmpPath)) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
                @unlink($tmpPath);
            }

            return false;
        }
    }

    /**
     * Validate XML string
     * @param string $xml
     * @return bool
     */
    private function isValidXML(string $xml): bool
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $doc = simplexml_load_string($xml);
        $errors = libxml_get_errors();

        libxml_use_internal_errors($previous);

        return $doc !== false && empty($errors);
    }
}
