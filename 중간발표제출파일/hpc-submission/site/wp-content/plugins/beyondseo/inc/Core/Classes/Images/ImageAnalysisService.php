<?php
/** @noinspection SqlNoDataSourceInspection */
/** @noinspection PhpTooManyParametersInspection */
/** @noinspection PhpUsageOfSilenceOperatorInspection */
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Classes\Images;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Assume SeoOptimiserConfig is available
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Models\Configs\SeoOptimiserConfig;

/**
 * Orchestrates image analysis using Extractor and MetaProvider.
 * Contains the business logic for various image-related SEO analyses.
 */
final class ImageAnalysisService
{
    private ImageExtractor    $extractor;
    private ImageMetaProvider $meta;
    private string $siteUrl;
    public $fetchFunction = null; // Placeholder for fetch function

    /**
     * @param string $siteUrl The site's base URL (e.g., output of site_url()).
     * @param callable|null $fetchFunction
     */
    public function __construct(string $siteUrl, ?callable $fetchFunction = null)
    {
        $this->siteUrl = rtrim($siteUrl, '/');
        // Inject dependencies or use defaults if not provided.
        $this->extractor = new ImageExtractor($this);
        $this->meta      = new ImageMetaProvider($this, $this->siteUrl); // MetaProvider requires siteUrl
        if($this->fetchFunction == null && is_callable($fetchFunction)){
            $this->fetchFunction = $fetchFunction;
        }
    }

    // --- Facade methods delegating directly to components or combining simple data ---
    // Note: These facade methods return arrays to match the old trait's signature expectations.

    /**
     * Extracts basic image info (src, alt, title, initial dimensions from attributes, srcset, sizes)
     * from HTML. Does NOT fetch meta-data like size or format.
     *
     * @param string $htmlContent The HTML content.
     * @param string $baseUrl Optional base URL for relative path resolution (e.g., page URL).
     * @return array<array{src:string, alt:string, title:string, width:int|null, height:int|null, srcset:string, sizes:string}>
     */
    public function extractImagesFromContent(string $htmlContent, string $baseUrl = ''): array
    {
        // Delegate to the extractor
        $imageInfos = $this->extractor->fromHtml($htmlContent, $baseUrl)->all();

        // Convert ImageInfo objects back to the required array format for compatibility
        $output = [];
        foreach ($imageInfos as $info) {
            $output[] = [
                'src' => $info->src,
                'alt' => $info->alt,
                'title' => $info->title,
                'width' => $info->width,
                'height' => $info->height,
                'srcset' => $info->srcset, // Include srcset and sizes
                'sizes' => $info->sizes,
                // If strict compatibility is needed, remove these last two keys. Keeping for completeness of data extraction.
            ];
        }
        return $output;
    }

    /**
     * Gets the format of an image from its URL using the MetaProvider.
     *
     * @param string $imageUrl The URL of the image.
     * @return string The image format.
     */
    public function getImageFormat(string $imageUrl): string
    {
        return $this->meta->getFormat($imageUrl);
    }

    /**
     * Gets the size of an image in bytes using the MetaProvider.
     *
     * @param string $imageUrl The URL of the image.
     * @return int The size in bytes.
     */
    public function getImageSize(string $imageUrl): int
    {
        return $this->meta->getSize($imageUrl);
    }

    /**
     * Get the attachment ID from an image URL using the MetaProvider.
     *
     * @param string $url The URL of the image.
     * @return int|null The attachment ID or null if not found.
     */
    public function getAttachmentIdFromUrl(string $url): ?int
    {
        return $this->meta->getAttachmentIdFromUrl($url);
    }

    // --- Analysis methods using Extractor and MetaProvider ---

    /**
     * Analyze image attributes (alt, title) for keyword presence.
     *
     * @param string $keyword The keyword to analyze.
     * @param string $htmlContent The HTML content to analyze.
     * @return array Analysis results ('total', 'with_keyword_alt', 'with_keyword_title').
     */
    public function analyzeImageAttributes(string $keyword, string $htmlContent): array
    {
        // Use the extractor to get images
        $images = $this->extractor->fromHtml($htmlContent, $this->siteUrl)->all();
        $totalImages = count($images);
        $imagesWithKeywordAlt = 0;
        $imagesWithKeywordTitle = 0;

        // --- Migrate loop logic from old RcImagesTrait::analyzeImageAttributes ---
        $lowerKeyword = strtolower($keyword);
        foreach ($images as $image) {
            $alt = strtolower($image->alt);
            $title = strtolower($image->title);

            if (!empty($lowerKeyword) && str_contains($alt, $lowerKeyword)) {
                $imagesWithKeywordAlt++;
            }

            if (!empty($lowerKeyword) && str_contains($title, $lowerKeyword)) {
                $imagesWithKeywordTitle++;
            }
        }
        // --- End logic migration ---

        return [
            'total' => $totalImages,
            'with_keyword_alt' => $imagesWithKeywordAlt,
            'with_keyword_title' => $imagesWithKeywordTitle
        ];
    }

    /**
     * Check for the presence of keywords in image alt attributes.
     *
     * @param string $htmlContent The HTML content to analyze.
     * @param array<string> $keywords Keywords to look for.
     * @return array Results of the keyword check.
     */
    public function checkKeywordsInImageAlt(string $htmlContent, array $keywords): array
    {
        // Use the extractor to get images
        $images = $this->extractor->fromHtml($htmlContent, $this->siteUrl)->all();

        // --- Migrate logic from old RcImagesTrait::checkKeywordsInImageAlt ---
        if (empty($images)) {
            return [
                'has_any_keyword' => false,
                'keywords_found' => 0,
                'keywords_missing' => count($keywords),
                'images_with_keywords' => 0,
                'total_images' => 0, // Added total images field for clarity
                'keyword_instances' => array_fill_keys($keywords, 0),
                'details' => []
            ];
        }

        $details = [];
        $imagesWithKeywords = 0;
        $keywordInstances = array_fill_keys($keywords, 0);
        $lowerKeywords = array_map('strtolower', $keywords);

        foreach ($images as $image) {
            $alt = strtolower($image->alt); // Use the alt property from ImageInfo
            $imageHasAnyKeyword = false;
            $imageDetails = [];

            foreach ($lowerKeywords as $keyword) {
                $found = str_contains($alt, $keyword); // Case-insensitive check is done by lowercasing both
                $imageDetails[$keyword] = $found;

                if ($found) {
                    $keywordInstances[array_search($keyword, $lowerKeywords, true)]++; // Increment correct original keyword count
                    $imageHasAnyKeyword = true;
                }
            }

            if ($imageHasAnyKeyword) {
                $imagesWithKeywords++;
            }

            $details[] = [
                'src' => $image->src, // Use src property
                'alt' => $image->alt, // Use alt property (original casing)
                'has_any_keyword' => $imageHasAnyKeyword,
                'keywords' => $imageDetails // Keys are lowercase keywords
            ];
        }

        // Count unique keywords found by checking which instance counts are > 0
        $uniqueKeywordsFound = count(array_filter($keywordInstances, fn($count) => $count > 0));

        return [
            'has_any_keyword' => $imagesWithKeywords > 0,
            'keywords_found' => $uniqueKeywordsFound,
            'keywords_missing' => count($keywords) - $uniqueKeywordsFound,
            'images_with_keywords' => $imagesWithKeywords,
            'total_images' => count($images), // Added total images field
            'keyword_instances' => $keywordInstances, // Counts for original keywords
            'details' => $details
        ];
    }

    /**
     * Extract images with non-empty alt attributes from HTML content.
     *
     * @param string $htmlContent The HTML content to analyze.
     * @return array<array{src:string, alt:string}> Array of image data.
     */
    public function extractImagesWithAlt(string $htmlContent): array
    {
        // Use the extractor to get images
        $images = $this->extractor->fromHtml($htmlContent, $this->siteUrl)->all();
        $imagesWithAlt = [];

        foreach ($images as $img) {
            $altText = trim($img->alt); // Use alt property from ImageInfo
            if (!empty($altText)) {
                $imagesWithAlt[] = [
                    'src' => $img->src, // Use src property
                    'alt' => $altText
                ];
            }
        }

        return $imagesWithAlt;
    }

    /**
     * Analyzes all images on a page including size, format, dimensions, local/remote status,
     * optimization potential, and compression level needed. Combines images found in HTML
     * with images attached to a given post-ID (if provided).
     *
     * @param string $htmlContent The HTML content of the page.
     * @param string $pageUrl The URL of the page (used as base URL for HTML extraction).
     * @param int $postId Optional post-ID to include attached media.
     * @return array Analysis results including a detailed 'images' array.
     */
    public function analyzePageImages(string $htmlContent, string $pageUrl, int $postId = 0): array
    {
        $allImages = new ImageCollection();

        // First pass: Get images from a media library attached to the post (if postId > 0)
        // This involves WordPress functions.
        if ($postId > 0 && function_exists('get_attached_media') && function_exists('wp_get_attachment_url')) {
            $attachments = get_attached_media('image', $postId);
            if ($attachments) {
                foreach ($attachments as $attachment) {
                    $imageUrl = wp_get_attachment_url($attachment->ID);
                    if ($imageUrl) {
                        // Create ImageInfo and get meta-data immediately using MetaProvider
                        $imgInfo = ImageInfo::fromHtmlAttributes(src: $imageUrl); // Create with minimal info
                        $metaDimensions = $this->meta->getDimensions($imageUrl, $attachment->ID);
                        $imgInfo->withMeta(
                            $this->meta->getSize($imageUrl, $attachment->ID),
                            $this->meta->getFormat($imageUrl),
                            $this->meta->isLocal($imageUrl),
                            $attachment->ID,
                            $metaDimensions
                        );

                        // Calculate analysis metrics and update ImageInfo
                        $effectiveDims = $imgInfo->getEffectiveDimensions();
                        $imgInfo->withAnalysis(
                            $this->calculateOptimizationPotentialForImage($imgInfo->size ?? 0, $effectiveDims['width'] ?? 0, $effectiveDims['height'] ?? 0),
                            $this->calculateCompressionLevelForImage($imgInfo->size ?? 0, $effectiveDims['width'] ?? 0, $effectiveDims['height'] ?? 0)
                        );

                        $allImages->push($imgInfo);
                    }
                }
            }
        }

        // Second pass: Parse HTML to find all images in the content
        $imagesFromHtml = $this->extractor->fromHtml($htmlContent, $pageUrl);

        // Combine images from HTML with attached images, adding meta-data if needed
        foreach ($imagesFromHtml as $imgInfo) {
            // Check if this image URL is already in the collection (e.g., from attachments)
            $existingImgInfo = $allImages->getByUrl($imgInfo->src);

            if (!$existingImgInfo) {
                // If not already processed, get meta-data and analysis for the image from HTML
                $metaDimensions = $this->meta->getDimensions($imgInfo->src, $imgInfo->attachmentId);
                $imgInfo->withMeta(
                    $this->meta->getSize($imgInfo->src, $imgInfo->attachmentId),
                    $this->meta->getFormat($imgInfo->src),
                    $this->meta->isLocal($imgInfo->src),
                    $this->meta->getAttachmentIdFromUrl($imgInfo->src), // Double-check attachment ID
                    $metaDimensions
                );

                // Calculate analysis metrics
                $effectiveDims = $imgInfo->getEffectiveDimensions();
                $imgInfo->withAnalysis(
                    $this->calculateOptimizationPotentialForImage($imgInfo->size ?? 0, $effectiveDims['width'] ?? 0, $effectiveDims['height'] ?? 0),
                    $this->calculateCompressionLevelForImage($imgInfo->size ?? 0, $effectiveDims['width'] ?? 0, $effectiveDims['height'] ?? 0)
                );

                $allImages->push($imgInfo); // Add to a collection (handled de-duplication)

            } elseif ($existingImgInfo->size === null) { // Check if meta was already added
                $metaDimensions = $this->meta->getDimensions($existingImgInfo->src, $existingImgInfo->attachmentId);
                $existingImgInfo->withMeta(
                    $this->meta->getSize($existingImgInfo->src, $existingImgInfo->attachmentId),
                    $this->meta->getFormat($existingImgInfo->src),
                    $this->meta->isLocal($existingImgInfo->src),
                    $this->meta->getAttachmentIdFromUrl($existingImgInfo->src), // Double-check attachment ID
                    $metaDimensions
                );
                $effectiveDims = $existingImgInfo->getEffectiveDimensions();
                $existingImgInfo->withAnalysis(
                    $this->calculateOptimizationPotentialForImage($existingImgInfo->size ?? 0, $effectiveDims['width'] ?? 0, $effectiveDims['height'] ?? 0),
                    $this->calculateCompressionLevelForImage($existingImgInfo->size ?? 0, $effectiveDims['width'] ?? 0, $effectiveDims['height'] ?? 0)
                );
                // No need to push, it's already in the collection. ImageCollection handles de-duplication.
            }
        }

        // Calculate totals and oversized count based on the combined and enriched list
        $totalSize = 0;
        $oversizedCount = 0;
        $processedImages = []; // Final array structure matching the old trait output

        $optimalSizeThreshold = SeoOptimiserConfig::OPTIMAL_IMAGE_SIZE_THRESHOLD ?? 100000; // Default 100KB

        foreach ($allImages->all() as $img) {
            $totalSize += $img->size ?? 0; // Use cached size from ImageInfo

            if (($img->size ?? 0) > $optimalSizeThreshold) {
                $oversizedCount++;
            }

            // Reconstruct the output structure expected by the old trait's consumers
            $processedImages[] = [
                'url' => $img->src,
                'filename' => $this->extractFilenameFromUrl($img->src),
                'size' => $img->size ?? 0,
                'size_formatted' => $this->meta->formatBytes($img->size ?? 0), // Use MetaProvider's helper
                'width' => $img->getEffectiveDimensions()['width'] ?? 0, // Use effective dimensions
                'height' => $img->getEffectiveDimensions()['height'] ?? 0, // Use effective dimensions
                'is_local' => $img->isLocal ?? false, // Use cached boolean
                'is_oversized' => ($img->size ?? 0) > $optimalSizeThreshold,
                'compression_level_needed' => $img->compressionLevelNeeded ?? 'none', // Use a cached analysis result
                'optimization_potential' => $img->optimizationPotential ?? 0.0, // Use a cached analysis result
            ];
        }
        // --- End logic migration ---

        return [
            'images' => $processedImages,
            'oversized_count' => $oversizedCount,
            'total_size' => $totalSize,
            'total_images' => $allImages->count(),
        ];
    }

    /**
     * Gets data for a specific image (size, dimensions, format, etc.).
     * This method exists primarily for compatibility with the old trait signature.
     * It uses the MetaProvider and calculates analysis metrics.
     *
     * @param string $imageUrl The URL of the image.
     * @param int|null $attachmentId Optional attachment ID if available.
     * @return array|null Image data or null if unavailable.
     */
    public function getImageData(string $imageUrl, ?int $attachmentId = null): ?array
    {
        // Use MetaProvider to get core meta info
        $size = $this->meta->getSize($imageUrl, $attachmentId);

        if ($size === 0) {
            return null; // Size 0 usually means the image was not found or inaccessible
        }

        $id = $attachmentId ?? $this->meta->getAttachmentIdFromUrl($imageUrl);
        $format = $this->meta->getFormat($imageUrl);
        $dimensions = $this->meta->getDimensions($imageUrl, $id);
        $isLocal = $this->meta->isLocal($imageUrl);

        $width = $dimensions['width'] ?? 0;
        $height = $dimensions['height'] ?? 0;

        // Calculate derived metrics
        $optimizationPotential = $this->calculateOptimizationPotentialForImage($size, $width, $height);
        $compressionLevel = $this->calculateCompressionLevelForImage($size, $width, $height);

        // Reconstruct the output array structure expected by the old trait's consumers
        return [
            'url' => $imageUrl,
            'filename' => $this->extractFilenameFromUrl($imageUrl),
            'size' => $size,
            'size_formatted' => $this->meta->formatBytes($size), // Use MetaProvider's helper
            'width' => $width,
            'height' => $height,
            'format' => $format,
            'is_local' => $isLocal,
            'is_oversized' => $size > (SeoOptimiserConfig::OPTIMAL_IMAGE_SIZE_THRESHOLD ?? 100000),
            'compression_level_needed' => $compressionLevel,
            'optimization_potential' => $optimizationPotential,
        ];
    }

    /**
     * Safely extracts filename from URL with comprehensive error handling.
     *
     * @param string $url The URL to extract filename from.
     * @return string The extracted filename or fallback.
     */
    private function extractFilenameFromUrl(string $url): string
    {
        if (empty($url) || !is_string($url)) {
            return 'unknown';
        }

        $parsedPath = wp_parse_url($url, PHP_URL_PATH);
        
        if ($parsedPath === false || $parsedPath === null) {
            return 'unknown';
        }

        $filename = basename($parsedPath);
        
        return !empty($filename) ? $filename : 'unknown';
    }

    /**
     * Calculates the compression level needed for an image based on size and dimensions.
     *
     * @param int $size The size of the image in bytes.
     * @param int $width The width of the image.
     * @param int $height The height of the image.
     * @return string The compression level needed ('none', 'low', 'medium', 'high').
     */
    public function calculateCompressionLevelForImage(int $size, int $width, int $height): string
    {
        $optimalThreshold = SeoOptimiserConfig::OPTIMAL_IMAGE_SIZE_THRESHOLD ?? 100000; // e.g., 100KB
        $acceptableThreshold = SeoOptimiserConfig::ACCEPTABLE_IMAGE_SIZE_THRESHOLD ?? 300000; // e.g., 300KB
        $criticalThreshold = SeoOptimiserConfig::CRITICAL_IMAGE_SIZE_THRESHOLD ?? 800000; // e.g., 800KB

        if ($size <= $optimalThreshold) {
            return 'none';
        }

        $area = $width * $height;

        if ($area === 0) { // Dimensions unknown or zero
            if ($size > $criticalThreshold) {
                return 'high';
            } elseif ($size > $acceptableThreshold) {
                return 'medium';
            } else {
                return 'low';
            }
        }

        $bytesPerPixel = $size / $area;

        // Thresholds based on bytes per pixel (example values from old trait)
        if ($bytesPerPixel > 0.5) {
            return 'high';
        } elseif ($bytesPerPixel > 0.2) {
            return 'medium';
        } else {
            return 'low';
        }
        // --- End logic migration ---
    }

    /**
     * Calculates the optimization potential for an image (0-1).
     *
     * @param int $size The size of the image in bytes.
     * @param int $width The width of the image.
     * @param int $height The height of the image.
     * @return float The optimization potential (0.0-1.0).
     */
    public function calculateOptimizationPotentialForImage(int $size, int $width, int $height): float
    {
        $optimalThreshold = SeoOptimiserConfig::OPTIMAL_IMAGE_SIZE_THRESHOLD ?? 100000; // e.g., 100KB
        $acceptableThreshold = SeoOptimiserConfig::ACCEPTABLE_IMAGE_SIZE_THRESHOLD ?? 300000; // e.g., 300KB
        $criticalThreshold = SeoOptimiserConfig::CRITICAL_IMAGE_SIZE_THRESHOLD ?? 800000; // e.g., 800KB

        if ($size <= $optimalThreshold) {
            return 0.0;
        }

        $area = $width * $height;

        if ($area === 0) { // Dimensions unknown or zero
            if ($size > $criticalThreshold) {
                return 1.0; // High potential
            } elseif ($size > $acceptableThreshold) {
                return 0.7; // Medium potential
            } else {
                return 0.3; // Low potential (just over optimal)
            }
        }

        $bytesPerPixel = $size / $area;

        // Potential based on bytes per pixel (example values from old trait)
        if ($bytesPerPixel > 0.5) {
            return 1.0; // Very high potential
        } elseif ($bytesPerPixel > 0.2) {
            return 0.7; // High potential
        } elseif ($bytesPerPixel > 0.1) {
            return 0.3; // Medium potential
        } else {
            return 0.0; // Low potential (good BPP but still over optimal size)
        }
        // --- End logic migration ---
    }

    /**
     * Calculates the overall optimization potential for all images in a page analysis result.
     *
     * @param array $imageAnalysis The analysis data structure returned by analyzePageImages.
     * @return float The overall optimization potential (0.0-1.0).
     */
    public function analyseImageOptimizationPotential(array $imageAnalysis): float
    {
        if (empty($imageAnalysis['images'])) {
            return 0.0;
        }

        $totalPotential = 0.0;
        $totalWeight = 0.0;
        // Use total_size from analysis if available, otherwise calculate
        $totalSize = $imageAnalysis['total_size'] ?? array_sum(array_column($imageAnalysis['images'], 'size'));

        if ($totalSize <= 0) { // Avoid division by zero or negative size
            return 0.0;
        }

        foreach ($imageAnalysis['images'] as $image) {
            // Ensure 'size' and 'optimization_potential' keys exist and are numeric
            $size = (int) ($image['size'] ?? 0);
            $potential = (float) ($image['optimization_potential'] ?? 0.0);

            if ($size > 0) {
                // Weight by image size (larger images have more impact)
                $weight = $size / $totalSize;
                $totalPotential += $potential * $weight;
                $totalWeight += $weight; // Sum actual weights
            }
        }

        // Return calculated potential weighted by size, or 0 if no images contributed
        return $totalWeight > 0 ? $totalPotential / $totalWeight : 0.0;
        // --- End logic migration ---
    }

    /**
     * Analyzes image formats on a page, counting legacy vs. next-gen formats
     * and estimating potential savings from conversion.
     *
     * @param string $htmlContent The HTML content of the page.
     * @param string $pageUrl The URL of the page (used as base URL for extraction).
     * @return array Analysis results including image format details.
     */
    public function analyzeImageFormats(string $htmlContent, string $pageUrl): array
    {
        // Use the extractor to get images
        $images = $this->extractor->fromHtml($htmlContent, $pageUrl)->all();

        // Initialize result structure matching old trait output
        $results = [
            'images' => [],
            'total_images' => count($images),
            'legacy_format_count' => 0,
            'next_gen_format_count' => 0,
            'potential_savings' => 0, // Total potential savings in bytes
        ];

        if (empty($images)) {
            return $results;
        }


        foreach ($images as $image) {
            $imageUrl = $image->src;
            if (empty($imageUrl)) {
                continue;
            }

            // Get image format and size using MetaProvider
            $format = $this->meta->getFormat($imageUrl);
            $size = $this->meta->getSize($imageUrl);
            $dimensions = $image->getEffectiveDimensions(); // Use effective dimensions

            $lowerFormat = strtolower($format);
            $isLegacyFormat = in_array($lowerFormat, SeoOptimiserConfig::IMAGE_LEGACY_FORMATS, true);
            $isNextGenFormat = in_array($lowerFormat, SeoOptimiserConfig::NEXT_GEN_IMAGE_FORMATS, true);

            // Calculate potential savings (estimated for legacy formats)
            $potentialSavings = 0;
            if ($isLegacyFormat && $size > 0) {
                $results['legacy_format_count']++;
                // Estimate savings based on format (conservative estimate from old trait)
                $savingsRate = 0.25; // 25% savings default for JPEG/GIF/BMP
                if ($lowerFormat === 'png') {
                    $savingsRate = 0.35; // 35% savings for PNG
                }
                $potentialSavings = $size * $savingsRate;
                $results['potential_savings'] += $potentialSavings;
            } elseif ($isNextGenFormat) {
                $results['next_gen_format_count']++;
            }

            // Add image details to the result array
            $results['images'][] = [
                'url' => $imageUrl,
                'format' => $format,
                'size' => $size,
                'size_formatted' => $this->meta->formatBytes($size), // Use MetaProvider's helper
                'is_legacy_format' => $isLegacyFormat,
                'is_next_gen_format' => $isNextGenFormat,
                'potential_savings' => $potentialSavings, // Savings in bytes for this image
                'potential_savings_formatted' => $this->meta->formatBytes((int)$potentialSavings), // Formatted savings
                'dimensions' => $dimensions, // Use effective dimensions
                'alt' => $image->alt, // Use alt from ImageInfo
            ];
        }
        // --- End logic migration ---

        // Add formatted total potential savings
        // This wasn't explicit in the old output structure but is useful.
        // Adding it here as an additional field to the top-level result array.
        // If strict compatibility is required, remove this.
        $results['total_potential_savings_formatted'] = $this->meta->formatBytes((int)$results['potential_savings']);


        return $results;
    }

    /**
     * Analyzes images in HTML content for responsive sizing attributes (srcset, sizes).
     *
     * @param string $htmlContent The HTML content to analyze.
     * @param string $baseUrl The base URL for resolving relative paths (e.g., page URL).
     * @return array Analysis results including responsive image details.
     */
    public function analyzeResponsiveImages(string $htmlContent, string $baseUrl): array
    {
        // Use the extractor which now extracts srcset and sizes
        $images = $this->extractor->fromHtml($htmlContent, $baseUrl)->all();

        $totalImages = count($images);
        $responsiveImages = 0; // Has srcset
        $nonResponsiveImages = 0; // No srcset
        $hasSizesAttribute = 0; // Has srcset AND sizes
        $potentialBandwidthSavings = 0; // Total potential savings from non-responsive images
        $analysisDetails = []; // Array structure matching old trait output

        // --- Migrate loop logic from old RcImagesTrait::analyzeResponsiveImages ---
        foreach ($images as $img) {
            // ImageInfo now includes srcset and sizes properties
            $hasResponsiveAttributes = trim($img->srcset) !== '';
            $hasSizes = trim($img->sizes) !== '';

            if ($hasResponsiveAttributes) {
                $responsiveImages++;
                if ($hasSizes) {
                    $hasSizesAttribute++;
                }
            } else {
                $nonResponsiveImages++;

                // Estimate potential bandwidth savings for non-responsive images
                // This is an estimate based on the assumption that adding responsive
                // attributes could save approximately 30% of bandwidth on average for *that specific image*.
                $imageSize = $this->meta->getSize($img->src); // Use MetaProvider for size
                if ($imageSize > 0) {
                    $potentialBandwidthSavings += $imageSize * 0.3;
                }
            }

            // Add image data to a result array, matching the old structure
            $analysisDetails[] = [
                'src' => $img->src,
                'alt' => $img->alt,
                'has_srcset' => $hasResponsiveAttributes,
                'has_sizes' => $hasSizes,
                'width' => $img->width, // Use width from attribute (as in the old trait output)
                'height' => $img->height, // Use height from the attribute (as in the old trait output)
                'is_responsive' => $hasResponsiveAttributes, // Based on srcset presence
            ];
        }
        // --- End logic migration ---

        // Calculate responsive percentage
        $responsivePercentage = $totalImages > 0 ? ($responsiveImages / $totalImages) * 100 : 0;

        return [
            'total_images' => $totalImages,
            'responsive_images' => $responsiveImages,
            'non_responsive_images' => $nonResponsiveImages,
            'responsive_percentage' => $responsivePercentage,
            'images' => $analysisDetails, // The array of analyzed image details
            'has_sizes_attribute' => $hasSizesAttribute,
            'potential_bandwidth_savings' => $potentialBandwidthSavings, // Total savings in bytes
            'potential_bandwidth_savings_formatted' => $this->meta->formatBytes((int)$potentialBandwidthSavings), // Formatted total savings
        ];
    }

    /**
     * Evaluates the quality of an alt text based on length, content, etc.
     *
     * @param string $altText The alt text to evaluate.
     * @return float Quality score between 0 and 1.
     */
    public function evaluateAltTextQuality(string $altText): float
    {
        // --- Migrate logic from old RcImagesTrait::evaluateAltTextQuality ---
        $altText = trim($altText);
        if (empty($altText)) {
            return 0.0; // No alt text is the lowest quality
        }

        $score = 1.0;
        $length = strlen($altText);

        // Check length - ideal is between ~5 and ~125 characters for description
        // (Exact numbers from old trait)
        if ($length < 5) {
            $score *= 0.4; // Too short - likely not descriptive
        } elseif ($length > 125) {
            $score *= 0.7; // Too long - might be keyword stuffed or not concise
        }

        // Check if an alt text is just a filename
        if (preg_match('/\.(jpg|jpeg|png|gif|webp|svg|bmp)$/i', $altText)) { // Added bmp
            $score *= 0.5; // Using filename as alt text is poor practice
        }

        // Check if an alt text is too generic (case-insensitive)
        if (preg_match('/^(image|picture|photo|img|pic|graphic)$/i', $altText)) {
            $score *= 0.6; // Generic descriptions are not useful
        }

        // Consider adding checks for repeating keywords or exact match to src basename?
        // Not in original trait, but could improve quality score.

        // Ensure the score is within the 0-1 range
        return max(0.0, min(1.0, $score));
        // --- End logic migration ---
    }

    /**
     * Analyzes HTML content to check for image alt text attributes presence and quality.
     *
     * @param string $htmlContent The HTML content to analyze.
     * @return array Analysis results including alt text details per image.
     */
    public function analyzeImageAltTextAttributes(string $htmlContent): array
    {
        // Use the extractor to get images
        $images = $this->extractor->fromHtml($htmlContent, $this->siteUrl)->all();

        $totalImages = count($images);
        $imagesWithAlt = 0;
        $imagesWithoutAlt = 0;
        $analysisDetails = []; // Array structure matching old trait output

        // --- Migrate loop logic from old RcImagesTrait::analyzeImageAltTextAttributes ---
        foreach ($images as $img) {
            $alt = $img->alt; // Use alt property from ImageInfo
            $hasAlt = trim($alt) !== '';

            if ($hasAlt) {
                $imagesWithAlt++;
            } else {
                $imagesWithoutAlt++;
            }

            // Add image data to a result array, matching the old structure
            $analysisDetails[] = [
                'src' => $img->src, // Use src property
                'alt' => $alt, // Use alt property (original casing)
                'has_alt' => $hasAlt,
                'quality_score' => $this->evaluateAltTextQuality($alt), // Use the service's method
            ];
        }
        // --- End logic migration ---

        // Calculate alt text ratio
        $altTextRatio = $totalImages > 0 ? (float) $imagesWithAlt / $totalImages : 1.0;

        return [
            'success' => true, // Old trait output included success/message
            'message' => 'Alt text analysis completed successfully',
            'images' => $analysisDetails, // The array of analyzed image details
            'total_images' => $totalImages,
            'images_with_alt' => $imagesWithAlt,
            'images_without_alt' => $imagesWithoutAlt,
            'alt_text_ratio' => $altTextRatio,
        ];
    }
}
