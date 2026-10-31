<?php /** @noinspection XmlUnusedNamespaceDeclaration */
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Sitemap;

if ( !defined('ABSPATH') ) {
    exit;
}

use Exception;
use SimpleXMLElement;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;

/**
 * Handles sitemap rendering with comprehensive error handling
 */
class Renderer
{
    use RcLoggerTrait;

    /**
     * Render sitemap entries to XML with comprehensive error handling
     */
    public function render(array $entries): string 
    {
        try {
            // Validate entries array
            if (empty($entries)) {
                $this->log('Sitemap Renderer: No entries provided for rendering');
                return $this->renderEmptyXML();
            }

            $xml = new SimpleXMLElement(
                '<?xml version="1.0" encoding="UTF-8"?>'
                . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"></urlset>'
            );

            $processed_count = 0;
            $error_count = 0;

            foreach ($entries as $index => $entry) {
                try {
                    // Validate entry structure
                    if (!$this->isValidEntry($entry)) {
                        $this->log("Sitemap Renderer: Invalid entry at index $index");
                        $error_count++;
                        continue;
                    }

                    $url = $xml->addChild('url');
                    
                    // Add location with validation
                    $loc = $this->sanitizeUrl($entry['loc']);
                    if (!$loc) {
                        $this->log("Sitemap Renderer: Invalid URL at index $index: " . ($entry['loc'] ?? 'empty'));
                        $error_count++;
                        continue;
                    }
                    $url->addChild('loc', $loc);
                    
                    // Add lastmod with validation
                    $lastmod = $this->sanitizeDate($entry['lastmod'] ?? '');
                    if ($lastmod) {
                        $url->addChild('lastmod', $lastmod);
                    }
                    
                    // Add priority with validation
                    if (!empty($entry['priority'])) {
                        $priority = $this->sanitizePriority($entry['priority']);
                        if ($priority !== null) {
                            $url->addChild('priority', $priority);
                        }
                    }
                    
                    // Add changefreq with validation
                    if (!empty($entry['changefreq'])) {
                        $changefreq = $this->sanitizeChangefreq($entry['changefreq']);
                        if ($changefreq) {
                            $url->addChild('changefreq', $changefreq);
                        }
                    }

                    // Add image with validation
                    if (!empty($entry['image'])) {
                        $image_url = $this->sanitizeUrl($entry['image']);
                        if ($image_url) {
                            $image = $url->addChild('image:image', '', 'http://www.google.com/schemas/sitemap-image/1.1');
                            $image->addChild('image:loc', $image_url, 'http://www.google.com/schemas/sitemap-image/1.1');
                        }
                    }
                    
                    $processed_count++;
                    
                } catch (Exception $e) {
                    $this->log("Sitemap Renderer: Error processing entry at index $index: " . $e->getMessage());
                    $error_count++;
                    continue;
                }
            }

            $xml_string = $xml->asXML();
            
            if ($xml_string === false) {
                $this->log('Sitemap Renderer: Failed to generate XML string');
                return $this->renderEmptyXML();
            }

            $this->log("Sitemap Renderer: Successfully rendered $processed_count entries ($error_count errors)");
            return $xml_string;
            
        } catch (Exception $e) {
            $this->log('Sitemap Renderer: Critical error in render(): ' . $e->getMessage());
            return $this->renderEmptyXML();
        }
    }

    /**
     * Validate entry structure
     */
    private function isValidEntry(array $entry): bool
    {
        return !empty($entry['loc']) &&
               isset($entry['lastmod']);
    }

    /**
     * Sanitize and validate URL
     */
    private function sanitizeUrl(string $url): ?string
    {
        $url = trim($url);
        if (empty($url)) {
            return null;
        }
        
        // Basic URL validation
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }
        
        return esc_url($url);
    }

    /**
     * Sanitize date format
     */
    private function sanitizeDate(string $date): ?string
    {
        if (empty($date)) {
            return null;
        }
        
        // Validate ISO 8601 date format
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return null;
        }
        
        return esc_html(gmdate('c', $timestamp));
    }

    /**
     * Sanitize priority value
     */
    private function sanitizePriority(string $priority): ?string
    {
        $priority = trim($priority);
        $float_priority = floatval($priority);
        
        if ($float_priority < 0.0 || $float_priority > 1.0) {
            return null;
        }
        
        return esc_html(number_format($float_priority, 1));
    }

    /**
     * Sanitize changefreq value
     */
    private function sanitizeChangefreq(string $changefreq): ?string
    {
        $valid_frequencies = ['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'];
        $changefreq = trim(strtolower($changefreq));
        
        if (!in_array($changefreq, $valid_frequencies, true)) {
            return null;
        }
        
        return esc_html($changefreq);
    }

    /**
     * Render empty XML when no valid entries
     */
    private function renderEmptyXML(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
               '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"></urlset>';
    }
}
