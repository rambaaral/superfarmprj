<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Sitemap;

if ( !defined('ABSPATH') ) {
    exit;
}

use Exception;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;

/**
 * Handles sitemap generation with comprehensive error handling
 */
class Generator
{
    use RcLoggerTrait;

    /**
     * Generate sitemap with comprehensive error handling
     */
    public function generate(string $type = 'general'): string 
    {
        try {
            $this->log("Sitemap Generator: Starting generation for type '$type'");
            
            // Build entries with error handling
            $entryBuilder = new EntryBuilder();
            $entries = $entryBuilder->getEntries($type);
            
            if (empty($entries)) {
                $this->log("Sitemap Generator: No entries found for type '$type'");
                // Still generate empty XML rather than failing completely
            }
            
            // Render XML with error handling
            $renderer = new Renderer();
            $xml = $renderer->render($entries);
            
            if (empty($xml)) {
                $this->log("Sitemap Generator: Renderer returned empty XML for type '$type'");
                throw new Exception('Failed to render sitemap XML');
            }

            // Write sitemap file with error handling
            $writer = new Writer();
            $write_success = $writer->write($type, $xml);
            
            if (!$write_success) {
                $this->log("Sitemap Generator: Failed to write sitemap file for type '$type'");
                // Continue execution to return XML even if file write fails
            }

            $entry_count = count($entries);
            $this->log("Sitemap Generator: Successfully generated sitemap for type '$type' with $entry_count entries");
            
            return $xml;
            
        } catch (Exception $e) {
            $this->log('Sitemap Generator: Critical error in generate(): ' . $e->getMessage());
            
            // Return minimal valid XML as fallback
            return $this->getFallbackXML();
        }
    }

    /**
     * Generate minimal fallback XML when generation fails
     */
    private function getFallbackXML(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
               '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n" .
               '<url><loc>' . esc_url(home_url('/')) . '</loc><lastmod>' . gmdate('c') . '</lastmod><priority>1.0</priority></url>' . "\n" .
               '</urlset>';
    }
}
