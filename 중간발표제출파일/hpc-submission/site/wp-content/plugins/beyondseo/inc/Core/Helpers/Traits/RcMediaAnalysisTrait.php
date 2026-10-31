<?php
/** @noinspection PhpUsageOfSilenceOperatorInspection */
/** @noinspection PhpStatementHasEmptyBodyInspection */
/** @noinspection PhpInappropriateInheritDocUsageInspection */
/** @noinspection SqlNoDataSourceInspection */
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Helpers\Traits;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Models\Configs\SeoOptimiserConfig;
use DOMXPath;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\Classes\Images\ImageAnalysisService;

/**
 * Trait RcMediaAnalysisTrait
 *
 * Provides utility methods for handling media-related operations.
 */
trait RcMediaAnalysisTrait
{
    use RcLoggerTrait;

    // Lazily initialized instance of the analysis service
    private ?ImageAnalysisService $__imageService = null;

    /**
     * Lazily initializes and returns the ImageAnalysisService.
     * This method acts as a simple Service Locator for this trait.
     *
     * @return ImageAnalysisService
     */
    private function images(): ImageAnalysisService
    {
        // Initialize the service and its dependencies (Extractor, MetaProvider)
        return $this->__imageService
            ??= new ImageAnalysisService(
                $this->getSiteUrl(),
                function(string $url, array $args = [], $getHeader = false) {
                    $this->fetchInternalUrlContent($url, $args, $getHeader);
                }
            );
    }

    /**
     * @inheritDoc RankingCoach\Inc\Core\Classes\Images\ImageAnalysisService::extractImagesFromContent
     */
    public function extractImagesFromContent(string $htmlContent, string $baseUrl = ''): array
    {
        // Delegate to the service
        return $this->images()->extractImagesFromContent($htmlContent, $baseUrl);
    }

    /**
     * @inheritDoc RankingCoach\Inc\Core\Classes\Images\ImageAnalysisService::getImageFormat
     */
    public function getImageFormat(string $imageUrl): string
    {
        // Delegate to the service
        return $this->images()->getImageFormat($imageUrl);
    }

    /**
     * @inheritDoc RankingCoach\Inc\Core\Classes\Images\ImageAnalysisService::getImageSize
     */
    public function getImageSize(string $imageUrl): int
    {
        // Delegate to the service
        return $this->images()->getImageSize($imageUrl);
    }

    /**
     * @inheritDoc RankingCoach\Inc\Core\Classes\Images\ImageAnalysisService::getAttachmentIdFromUrl
     */
    public function getAttachmentIdFromUrl(string $url): ?int
    {
        // Delegate to the service
        return $this->images()->getAttachmentIdFromUrl($url);
    }

    /**
     * @inheritDoc RankingCoach\Inc\Core\Classes\Images\ImageAnalysisService::analyzePageImages
     */
    public function analyzePageImages(string $html, string $url, int $postId = 0): array
    {
        // Delegate to the service
        return $this->images()->analyzePageImages($html, $url, $postId);
    }

    /**
     * @inheritDoc RankingCoach\Inc\Core\Classes\Images\ImageAnalysisService::getImageData
     */
    public function getImageData(string $url, ?int $att = null): ?array
    {
        // Delegate to the service
        return $this->images()->getImageData($url, $att);
    }

    /**
     * @inheritDoc RankingCoach\Inc\Core\Classes\Images\ImageAnalysisService::calculateCompressionLevelForImage
     */
    public function calculateCompressionLevelForImage(int $size, int $w, int $h): string
    {
        // Delegate to the service
        return $this->images()->calculateCompressionLevelForImage($size, $w, $h);
    }

    /**
     * @inheritDoc RankingCoach\Inc\Core\Classes\Images\ImageAnalysisService::calculateOptimizationPotentialForImage
     */
    public function calculateOptimizationPotentialForImage(int $size, int $w, int $h): float
    {
        // Delegate to the service
        return $this->images()->calculateOptimizationPotentialForImage($size, $w, $h);
    }

    /**
     * @inheritDoc RankingCoach\Inc\Core\Classes\Images\ImageAnalysisService::analyseImageOptimizationPotential
     */
    public function analyseImageOptimizationPotential(array $analysis): float
    {
        // Delegate to the service
        return $this->images()->analyseImageOptimizationPotential($analysis);
    }

    /**
     * @inheritDoc RankingCoach\Inc\Core\Classes\Images\ImageAnalysisService::analyzeImageFormats
     */
    public function analyzeImageFormats(string $html, string $url): array
    {
        // Delegate to the service
        return $this->images()->analyzeImageFormats($html, $url);
    }

    /**
     * @inheritDoc RankingCoach\Inc\Core\Classes\Images\ImageAnalysisService::analyzeResponsiveImages
     */
    public function analyzeResponsiveImages(string $html, string $baseUrl): array
    {
        // Delegate to the service
        return $this->images()->analyzeResponsiveImages($html, $baseUrl);
    }

    /**
     * @inheritDoc RankingCoach\Inc\Core\Classes\Images\ImageAnalysisService::evaluateAltTextQuality
     */
    public function evaluateAltTextQuality(string $altText): float
    {
        // Delegate to the service
        return $this->images()->evaluateAltTextQuality($altText);
    }

    /**
     * @inheritDoc RankingCoach\Inc\Core\Classes\Images\ImageAnalysisService::analyzeImageAltTextAttributes
     */
    public function analyzeImageAltTextAttributes(string $html): array
    {
        // Delegate to the service
        return $this->images()->analyzeImageAltTextAttributes($html);
    }

    /**
     * Analyzes image attributes (alt, title) for keyword presence using a DOMXPath object.
     *
     * @param string $keyword The keyword to analyze.
     * @param DOMXPath $xpath The DOMXPath object for the HTML document.
     * @return array Analysis results.
     */
    public function analyzeImageAttributes(string $keyword, DOMXPath $xpath): array
    {
        $htmlContent = $this->loadHTMLFromDomXPath($xpath);
        if ($htmlContent === null) {
            return [];
        }

        // Delegate to the service method which expects HTML string
        return $this->images()->analyzeImageAttributes($keyword, $htmlContent);
    }

    /**
     * Check for the presence of keywords in image alt attributes, starting from an array of image data.
     *
     * @param array $images Array of image data.
     * @param array $keywords Keywords to look for.
     * @return array Results of the keyword check.
     */
    public function checkKeywordsInImageAlt(array $images, array $keywords): array
    {
        if (empty($images)) {
            return [
                'has_any_keyword' => false,
                'keywords_found' => 0,
                'keywords_missing' => count($keywords),
                'images_with_keywords' => 0,
                'total_images' => 0,
                'keyword_instances' => array_fill_keys($keywords, 0),
                'details' => []
            ];
        }

        $details = [];
        $imagesWithKeywords = 0;
        $keywordInstances = array_fill_keys($keywords, 0);
        $lowerKeywords = array_map('strtolower', $keywords);

        foreach ($images as $image) {
            // Expecting array structure like ['src' => ..., 'alt' => ...]
            $alt = strtolower($image['alt'] ?? '');
            $imageHasAnyKeyword = false;
            $imageDetails = [];

            foreach ($lowerKeywords as $keyword) {
                // Use str_contains for case-insensitive check after lowercasing
                $found = str_contains($alt, $keyword);
                $imageDetails[$keyword] = $found;

                if ($found) {
                    // Find the index of the lowercase keyword to increment the count for the original keyword
                    $originalKeywordIndex = array_search($keyword, $lowerKeywords, true);
                    if ($originalKeywordIndex !== false) {
                        $keywordInstances[$keywords[$originalKeywordIndex]]++;
                    }
                    $imageHasAnyKeyword = true;
                }
            }

            if ($imageHasAnyKeyword) {
                $imagesWithKeywords++;
            }

            $details[] = [
                'src' => $image['src'] ?? '',
                'alt' => $image['alt'] ?? '',
                'has_any_keyword' => $imageHasAnyKeyword,
                'keywords' => $imageDetails
            ];
        }

        // Count unique keywords found by checking which instance counts are > 0
        $uniqueKeywordsFound = count(array_filter($keywordInstances, fn($count) => $count > 0));

        return [
            'has_any_keyword' => $imagesWithKeywords > 0,
            'keywords_found' => $uniqueKeywordsFound,
            'keywords_missing' => count($keywords) - $uniqueKeywordsFound,
            'images_with_keywords' => $imagesWithKeywords,
            'total_images' => count($images),
            'keyword_instances' => $keywordInstances,
            'details' => $details
        ];
    }

    /**
     * Extract images with alt attributes from HTML using a DOMXPath object.
     *
     * @param DOMXPath $xpath The XPath object for the HTML document.
     * @return array Array of image data with src and alt.
     */
    public function extractImagesWithAlt(DOMXPath $xpath): array
    {
        $htmlContent = $this->loadHTMLFromDomXPath($xpath);
        if ($htmlContent === null) {
            return [];
        }

        // Delegate to the service method which expects HTML string
        return $this->images()->extractImagesWithAlt($htmlContent);
    }

    /**
     * Extract images with alt attributes using the provided HTML content.
     *
     * @param string $htmlContent The HTML content to analyze.
     * @return array Array of image data with src and alt.
     */
    public function extractImagesWithAltFromContent(string $htmlContent): array
    {
        // Delegate to the service method which expects HTML string
        return $this->images()->extractImagesWithAlt($htmlContent);
    }

    /**
     * Analyze all images in the HTML content for alt text and keyword usage.
     *
     * @param string $htmlContent The HTML content to analyze
     * @param string $primaryKeyword The primary keyword to check for
     * @return array|null Analysis results or null on error
     */
    public function analyzeAltTextKeywordUsageInImages(string $htmlContent, string $primaryKeyword): ?array
    {
        // Get all image elements
        $images = $this->extractImagesWithAltFromContent($htmlContent);
        $totalImages = count($images) ?? 0;

        if ($totalImages === 0) {
            return [
                'images_analyzed' => 0,
                'images_with_alt' => 0,
                'images_with_keyword' => 0,
                'images_with_keyword_stuffing' => 0,
                'percentage_with_alt' => 0,
                'percentage_with_keyword' => 0,
                'percentage_with_keyword_stuffing' => 0,
                'image_details' => []
            ];
        }

        // Analysis data
        $imagesWithAlt = 0;
        $imagesWithKeyword = 0;
        $imagesWithKeywordStuffing = 0;
        $imagesWithNaturalKeyword = 0;
        $imageDetails = [];

        // Lowercase the primary keyword for case-insensitive matching
        $primaryKeywordLower = strtolower($primaryKeyword);
        $keywordWords = explode(' ', $primaryKeywordLower);

        // Analyze each image
        foreach($images as $image) {
            $altText = $image['alt'] ?? '';
            $src = $image['src'];

            $hasAlt = !empty($altText);
            $keywordOccurrences = 0;
            $keywordNaturallyIncluded = false;

            if ($hasAlt) {
                $imagesWithAlt++;
                $altTextLower = strtolower($altText);

                // Count keyword occurrences in alt text
                $keywordOccurrences = substr_count($altTextLower, $primaryKeywordLower);

                if ($keywordOccurrences > 0) {
                    $imagesWithKeyword++;

                    if (strlen($altTextLower) > strlen($primaryKeywordLower) + 5) {
                        $keywordNaturallyIncluded = true;
                        $imagesWithNaturalKeyword++;
                    } else {
                        // Check if the alt text has words that aren't in the keyword
                        $altWords = explode(' ', $altTextLower);
                        $nonKeywordWords = array_diff($altWords, $keywordWords);
                        if (count($nonKeywordWords) > 0) {
                            $keywordNaturallyIncluded = true;
                            $imagesWithNaturalKeyword++;
                        }
                    }
                }

                if ($keywordOccurrences > SeoOptimiserConfig::MAX_KEYWORD_OCCURRENCES_IN_ALT) {
                    $imagesWithKeywordStuffing++;
                }
            }

            // Add image details to the results
            $imageDetails[] = [
                'src' => $src,
                'alt' => $altText,
                'has_alt' => $hasAlt,
                'keyword_occurrences' => $keywordOccurrences,
                'has_keyword' => $keywordOccurrences > 0,
                'has_natural_keyword' => $keywordNaturallyIncluded,
                'has_keyword_stuffing' => $keywordOccurrences > SeoOptimiserConfig::MAX_KEYWORD_OCCURRENCES_IN_ALT,
            ];
        }

        $percentageWithAlt = $totalImages > 0 ? ($imagesWithAlt / $totalImages) * 100 : 0;
        $percentageWithKeyword = $imagesWithAlt > 0 ? ($imagesWithKeyword / $imagesWithAlt) * 100 : 0;
        $percentageWithNaturalKeyword = $imagesWithKeyword > 0 ? ($imagesWithNaturalKeyword / $imagesWithKeyword) * 100 : 0;
        $percentageWithKeywordStuffing = $imagesWithKeyword > 0 ? ($imagesWithKeywordStuffing / $imagesWithKeyword) * 100 : 0;

        return [
            'images_analyzed' => $totalImages,
            'images_with_alt' => $imagesWithAlt,
            'images_with_keyword' => $imagesWithKeyword,
            'images_with_natural_keyword' => $imagesWithNaturalKeyword,
            'images_with_keyword_stuffing' => $imagesWithKeywordStuffing,
            'percentage_with_alt' => round($percentageWithAlt, 2),
            'percentage_with_keyword' => round($percentageWithKeyword, 2),
            'percentage_with_natural_keyword' => round($percentageWithNaturalKeyword, 2),
            'percentage_with_keyword_stuffing' => round($percentageWithKeywordStuffing, 2),
            'image_details' => $imageDetails,
        ];
    }
}
