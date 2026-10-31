<?php
/** @noinspection SqlNoDataSourceInspection */
/** @noinspection PhpTooManyParametersInspection */
/** @noinspection PhpUsageOfSilenceOperatorInspection */
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Classes\Images;

use RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Graphs\Traits\Image;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Provides meta-data for images (size, format, dimensions) with caching.
 * Handles interactions with WordPress media library and remote URLs.
 */
final class ImageMetaProvider
{
    use Image;

    /** @var ImageAnalysisService  The image analysis service used for analyzing images. */
    private ImageAnalysisService $analysisService;
    /** @var array<string,int>  Cache for image sizes (URL => bytes) */
    private array $sizeCache = [];
    /** @var array<string,string> Cache for image formats (URL => format) */
    private array $formatCache = [];
    /** @var array<string,array{width:int,height:int}|null> Cache for image dimensions (URL => ['width' => int, 'height' => int]|null) */
    private array $dimensionsCache = [];
    /** @var array<string,int|null> Cache for attachment IDs (URL => int|null) */
    private array $attachmentIdCache = [];
    /** @var array<string,bool> Cache for local status (URL => bool) */
    private array $isLocalCache = [];
    /** @var string The site's base URL (e.g., output of site_url()). */
    private string $siteUrl;
    /** @var array<string,string> Cache for WP upload URLs (baseurl and url) */
    private array $uploadDirUrls; // Cache for WP upload URLs

    /**
     * @param string $siteUrl The site's base URL (e.g., output of site_url()).
     */
    public function __construct(ImageAnalysisService $analysisService, string $siteUrl)
    {
        $this->analysisService = $analysisService;

        $this->siteUrl = rtrim($siteUrl, '/');
        // Pre-fetch upload directory URLs for faster local checks
        $uploadDir = wp_upload_dir();
        $this->uploadDirUrls = array_filter([
            $uploadDir['baseurl'] ?? '',
            $uploadDir['url'] ?? '', // Might be the same as baseurl, but good to include
        ]);
        // Ensure URLs are protocol-relative and without trailing slash for comparison if needed
        $this->uploadDirUrls = array_map(function($url) {
            $parts = wp_parse_url($url);
            return '//' . ($parts['host'] ?? '') . ($parts['path'] ?? '');
        }, $this->uploadDirUrls);
        $this->uploadDirUrls = array_unique(array_filter($this->uploadDirUrls));
    }

    /**
     * Determines if a URL points to an image within the local WordPress media library.
     *
     * @param string $url The image URL.
     * @return bool True if local, false otherwise.
     */
    public function isLocal(string $url): bool
    {
        if (isset($this->isLocalCache[$url])) {
            return $this->isLocalCache[$url];
        }

        // Quick check against site URL first
        if (!str_starts_with($url, $this->siteUrl)) {
            // Also check protocol-relative site URL if applicable
            $siteUrlParts = wp_parse_url($this->siteUrl);
            if ($siteUrlParts && isset($siteUrlParts['host'])) {
                $protocolRelativeSiteUrl = '//' . $siteUrlParts['host'] . ($siteUrlParts['path'] ?? '');
                if (!str_starts_with($url, $protocolRelativeSiteUrl)) {
                    // If not starting with site URL, check against upload dir URLs
                    $isLocal = false;
                    $urlToCheck = $url;
                    // Make URL protocol-relative for robust comparison
                    if (str_starts_with($urlToCheck, 'http')) {
                        $parts = wp_parse_url($urlToCheck);
                        $urlToCheck = '//' . ($parts['host'] ?? '') . ($parts['path'] ?? '');
                    }

                    foreach ($this->uploadDirUrls as $uploadUrl) {
                        if (str_starts_with($urlToCheck, $uploadUrl)) {
                            $isLocal = true;
                            break;
                        }
                    }
                    return $this->isLocalCache[$url] = $isLocal;
                }
            } else {
                // siteUrl was invalid, can't perform robust local check easily
                return $this->isLocalCache[$url] = false; // Assume not local or cannot determine
            }
        }

        // If it starts with the site URL (http/https or protocol relative),
        // we need to check if it's specifically within the upload directory path structure
        // A simpler way is to just try getting the attachment ID, if it works, it's local and in the media library.
        // But the old logic checked against upload_dir paths, let's use that.
        $isLocal = false;
        $urlToCheck = $url;
        // Make URL protocol-relative for robust comparison
        if (str_starts_with($urlToCheck, 'http')) {
            $parts = wp_parse_url($urlToCheck);
            $urlToCheck = '//' . ($parts['host'] ?? '') . ($parts['path'] ?? '');
        }

        foreach ($this->uploadDirUrls as $uploadUrl) {
            if (str_starts_with($urlToCheck, $uploadUrl)) {
                $isLocal = true;
                break;
            }
        }

        return $this->isLocalCache[$url] = $isLocal;
    }


    /**
     * Get the attachment ID from an image URL using WordPress functions and DB.
     *
     * @param string $url The URL of the image.
     * @return int|null The attachment ID or null if not found or a media library image.
     */
    public function getAttachmentIdFromUrl(string $url): ?int
    {
        if (isset($this->attachmentIdCache[$url])) {
            return $this->attachmentIdCache[$url];
        }

        // Not local → no attachment ID
        if (!$this->isLocal($url)) {
            return $this->attachmentIdCache[$url] = null;
        }

        // Remove query string
        $urlWithoutQuery = preg_replace('/\?.*/', '', $url);

        // Try WordPress built-in resolver first
        $attachmentId = attachment_url_to_postid($urlWithoutQuery);

        if ($attachmentId) {
            return $this->attachmentIdCache[$url] = $attachmentId;
        }

        // Validate upload dir
        $upload_dir = wp_upload_dir();
        $upload_dir_baseurl = $upload_dir['baseurl'] ?? '';

        if (empty($upload_dir_baseurl) || !str_contains($urlWithoutQuery, $upload_dir_baseurl)) {
            return $this->attachmentIdCache[$url] = null;
        }

        // Extract just the file name (e.g. image-scaled.jpg)
        $file = wp_basename($urlWithoutQuery);

        // --- FALLBACK: Use get_posts with limited results to avoid slow meta_query ---
        // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key
        // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_value
        $posts = get_posts([
            'post_type'  => 'attachment',
            'fields'     => 'ids',
            'post_status'=> 'inherit',
            'numberposts' => 1,
            'meta_key'   => '_wp_attached_file',
            'meta_value' => $file,
            'meta_compare' => 'LIKE',
        ]);
        // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key
        // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_value

        if (!empty($posts)) {
            return $this->attachmentIdCache[$url] = (int)$posts[0];
        }

        return $this->attachmentIdCache[$url] = null;
    }


    /**
     * Gets the size of an image in bytes. Uses caching.
     * Tries local file system/WP metadata first, then remote headers.
     *
     * @param string $url The URL of the image.
     * @param int|null $attachmentId Optional attachment ID if already known.
     * @return int The size in bytes, or 0 if size cannot be determined.
     */
    public function getSize(string $url, ?int $attachmentId = null): int
    {
        try {
            $host = wp_parse_url($url, PHP_URL_HOST);
        } catch (\Exception $e) {
            $host = null;
        }
        $isLocalhost = $host && (in_array($host, ['localhost', '127.0.0.1', '::1'])
                || str_contains($host, 'localhost'));

        if (isset($this->sizeCache[$url])) {
            return $this->sizeCache[$url];
        }

        $size = 0;
        $id = $attachmentId ?? $this->getAttachmentIdFromUrl($url);

        // --- Migrate logic from old RcImagesTrait::getImageSize AND parts of getImageData ---
        if ($this->isLocal($url)) {
            if ($id) {
                // If we have the attachment ID, use WordPress functions
                $metadata = wp_get_attachment_metadata($id);
                $file = get_attached_file($id);

                if ($file && file_exists($file)) {
                    // Prefer actual file size if a file exists
                    $size = filesize($file);
                } elseif ($metadata && !empty($metadata['filesize'])) {
                    // Fallback to metadata filesize if a file not found locally (e.g., network drive or external storage)
                    $size = (int) $metadata['filesize'];
                }
            } else {
                // Try to get a file path from URL even if not a standard attachment
                $uploadDir = wp_upload_dir();
                // Replace baseurl with basedir to get a potential local path
                $imageUrlPath = str_replace($uploadDir['baseurl'], $uploadDir['basedir'], $url);

                // Clean up potential "//" in a path after replacement
                $imageUrlPath = str_replace('//', '/', $imageUrlPath);

                if (file_exists($imageUrlPath)) {
                    $size = filesize($imageUrlPath);
                }
            }
        } else {// For external images, use remote headers to get size

            $response = is_callable($this->analysisService->fetchFunction) ? call_user_func($this->analysisService->fetchFunction, $url, [
                'timeout' => 5, // Shorter timeout for just getting headers
                'sslverify' => !$isLocalhost && wp_get_environment_type() === 'production', // Standard WP SSL verification
            ], true) : null;

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $contentLength = wp_remote_retrieve_header($response, 'content-length');
                if (!empty($contentLength)) {
                    // Handles cases where Content-Length is an array (redirects?) by taking the last value
                    $size = (int) (is_array($contentLength) ? end($contentLength) : $contentLength);
                }
            }
        }
        // --- End logic migration ---

        return $this->sizeCache[$url] = $size;
    }

    /**
     * Gets the format of an image from its URL or content type header. Uses caching.
     *
     * @param string $url The URL of the image.
     * @return string The image format (e.g., 'jpeg', 'png', 'webp', 'unknown').
     */
    public function getFormat(string $url): string
    {
        try {
            $host = wp_parse_url($url, PHP_URL_HOST);
        } catch (\Exception $e) {
            $host = null;
        }
        $isLocalhost = $host && (in_array($host, ['localhost', '127.0.0.1', '::1'])
                || str_contains($host, 'localhost'));

        if (isset($this->formatCache[$url])) {
            return $this->formatCache[$url];
        }

        // --- Migrate logic from old RcImagesTrait::getImageFormat ---
        // Try to get a format from file extension first
        $extension = pathinfo(wp_parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION); // Use a path from URL
        if (!empty($extension)) {
            // Clean potential query string artifacts from extension
            $extension = strtok($extension, '?');
            if (!empty($extension)) {
                return $this->formatCache[$url] = strtolower($extension);
            }
        }

        // If no extension, try to get a format from the content type using headers
        $response = is_callable($this->analysisService->fetchFunction) ? call_user_func($this->analysisService->fetchFunction, $url, [
            'timeout' => 5, // Shorter timeout
            'sslverify' => !$isLocalhost && wp_get_environment_type() === 'production',
        ], true) : null;

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $contentType = wp_remote_retrieve_header($response, 'content-type');
            if (!empty($contentType)) {
                // Handles cases where Content-Type is an array
                $contentType = is_array($contentType) ? end($contentType) : $contentType;
                // Extract the main type (e.g., "image/jpeg;charset=UTF-8" -> "jpeg")
                if (str_contains($contentType, 'image/')) {
                    $parts = explode(';', $contentType); // Split by semicolon for charset etc.
                    $format = str_replace('image/', '', $parts[0]); // Get type before semicolon
                    return $this->formatCache[$url] = strtolower(trim($format));
                }
            }
        }
        // --- End logic migration ---

        return $this->formatCache[$url] = 'unknown';
    }

    /**
     * Gets image dimensions (width and height). Uses caching.
     * Tries local file system/WP metadata first, then remote getimagesize (expensive!).
     *
     * @param string $url The URL of the image.
     * @param int|null $attachmentId Optional attachment ID if already known.
     * @return array{width:int,height:int}|null Dimensions array or null if unavailable.
     */
    public function getDimensions(string $url, ?int $attachmentId = null): ?array
    {
        if (isset($this->dimensionsCache[$url])) {
            return $this->dimensionsCache[$url];
        }

        $width = 0;
        $height = 0;
        $id = $attachmentId ?? $this->getAttachmentIdFromUrl($url);

        // --- Migrate logic from old RcImagesTrait::getImageData for dimensions ---
        if ($this->isLocal($url)) {
            if ($id) {
                // Use WP metadata if available
                $metadata = wp_get_attachment_metadata($id);
                if ($metadata && isset($metadata['width'], $metadata['height'])) {
                    $width = (int) $metadata['width'];
                    $height = (int) $metadata['height'];
                } else {
                    // Try getting dimensions directly from the file path if metadata is missing or incomplete
                    $file = get_attached_file($id);
                    if ($file && file_exists($file)) {
                        $imageDimensions = @getimagesize($file); // Suppress potential errors
                        if ($imageDimensions) {
                            $width = $imageDimensions[0];
                            $height = $imageDimensions[1];
                        }
                    }
                }
            } else {
                // Try to get dimensions from a local path if not a standard attachment
                $uploadDir = wp_upload_dir();
                $imageUrlPath = str_replace($uploadDir['baseurl'], $uploadDir['basedir'], $url);
                $imageUrlPath = str_replace('//', '/', $imageUrlPath); // Clean path

                if (file_exists($imageUrlPath)) {
                    $imageDimensions = @getimagesize($imageUrlPath); // Suppress potential errors
                    if ($imageDimensions) {
                        $width = $imageDimensions[0];
                        $height = $imageDimensions[1];
                    }
                }
            }
        } else {
            // For external images, use getimagesize on the URL. This is EXPENSIVE as it downloads part/all the image!
            // Consider if this is truly necessary or if dimensions from HTML attributes are enough for analysis.
            // Keeping it for compatibility with the old trait's behavior.
            $imageDimensions = $this->getImageSizeFromUrl($url); // Suppress potential errors
            if ($imageDimensions) {
                $width = $imageDimensions[0];
                $height = $imageDimensions[1];
            }
        }
        // --- End logic migration ---

        return $this->dimensionsCache[$url] = ($width > 0 && $height > 0) ? ['width' => $width, 'height' => $height] : null;
    }

    /**
     * Formats bytes into a human-readable string (e.g., "1.23 MB").
     * This helper is needed by the service for output formatting but lives here
     * as it's tied to image size representation. Could also be a separate utility.
     *
     * @param int $bytes The size in bytes.
     * @param int $precision The number of decimal places.
     * @return string Formatted size string.
     */
    public function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << ($pow * 10));
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
