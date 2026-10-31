<?php /** @noinspection PhpPropertyOnlyWrittenInspection */
/** @noinspection PhpTooManyParametersInspection */
/** @noinspection PhpUsageOfSilenceOperatorInspection */
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Classes\Images;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use DOMDocument;
use DOMXPath;
use DOMElement;

/**
 * Extracts basic image information from HTML content.
 */
final class ImageExtractor
{
    /**
     * The image analysis service used for analyzing images.
     */
    private ImageAnalysisService $analysisService;

    /**
     * Constructor.
     *
     * @param ImageAnalysisService $analysisService The image analysis service.
     */
    public function __construct(ImageAnalysisService $analysisService)
    {
        $this->analysisService = $analysisService;
    }

    /**
     * Extracts images from an HTML string.
     *
     * @param string $html The HTML content.
     * @param string $baseUrl Optional base URL for resolving relative paths.
     * @return ImageCollection Collection of ImageInfo objects.
     */
    public function fromHtml(string $html, string $baseUrl = ''): ImageCollection
    {
        $html = trim($html);
        if ($html === '') {
            return new ImageCollection();
        }

        $dom = new DOMDocument();
        // Suppress errors for malformed HTML, then clear them.
        libxml_use_internal_errors(true);
        // Use @ to suppress DOMDocument warnings on invalid HTML5 tags/attributes when loading
        // Wrap in basic HTML structure to handle fragments correctly
        @$dom->loadHTML('<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>');
        libxml_clear_errors();
        libxml_use_internal_errors(false); // Restore error handling

        $xpath   = new DOMXPath($dom);
        // Find img tags, focusing on those within the body wrapper if added
        $imgTags = $xpath->query('//body//img');

        $collection = new ImageCollection();
        $baseUrl    = rtrim($baseUrl, '/');
        $baseUrlParts = $baseUrl ? wp_parse_url($baseUrl) : null;

        if ($imgTags === false || $imgTags->length === 0) {
            return $collection;
        }

        foreach ($imgTags as $img) {
            /** @var DOMElement $img */
            $src = $img->getAttribute('src');
            if ($src === '' || str_starts_with($src, 'data:')) {
                continue;
            }

            // --- Migrate robust relative URL resolution logic from old trait (analyzePageImages) ---
            if (!str_starts_with($src, 'http') && !str_starts_with($src, '//')) {
                if ($baseUrlParts) {
                    if (str_starts_with($src, '/')) {
                        // Root-relative URL: /path/to/image.jpg
                        // Combine with scheme and host of baseUrl
                        $src = ($baseUrlParts['scheme'] ?? 'http') . '://' . ($baseUrlParts['host'] ?? '') . $src;
                    } else {
                        // Page-relative URL: image.jpg or subdir/image.jpg
                        // Combine with base URL's directory path
                        $baseDir = dirname($baseUrlParts['path'] ?? '');
                        // Ensure baseDir ends with / if not empty, and src doesn't start with /
                        $resolvedSrc = rtrim($baseUrl, '/') . $baseDir . '/' . ltrim($src, '/');
                        // Clean up potential multiple slashes in a path (except after a scheme)
                        $parts = wp_parse_url($resolvedSrc);
                        if ($parts && isset($parts['path'])) {
                            $parts['path'] = preg_replace('#//+#', '/', $parts['path']);
                            // Rebuild URL
                            $src = (isset($parts['scheme']) ? $parts['scheme'] . '://' : '')
                                . ($parts['host'] ?? '')
                                . $parts['path']
                                . (isset($parts['query']) ? '?' . $parts['query'] : '')
                                . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
                        } else {
                            $src = $resolvedSrc; // Fallback if wp_parse_url fails
                        }
                    }
                }
                // If no base URL is provided, relative URLs are left as they are (might be invalid later)
            } elseif (str_starts_with($src, '//') && $baseUrlParts && isset($baseUrlParts['scheme'])) {
                // Protocol-relative URL: //example.com/path
                $src = $baseUrlParts['scheme'] . ':' . $src;
            }
            // --- End relative URL resolution ---

            $info = ImageInfo::fromHtmlAttributes(
                src   : $src,
                alt   : (string)$img->getAttribute('alt'),
                title : (string)$img->getAttribute('title'),
                width : $img->getAttribute('width') !== '' ? (int)$img->getAttribute('width') : null,
                height: $img->getAttribute('height') !== '' ? (int)$img->getAttribute('height') : null,
                srcset: (string)$img->getAttribute('srcset'), // Extract srcset
                sizes : (string)$img->getAttribute('sizes')   // Extract sizes
            );

            $collection->push($info);
        }

        return $collection;
    }
}
