<?php
/** @noinspection PhpTooManyParametersInspection */
/** @noinspection PhpUsageOfSilenceOperatorInspection */
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Classes\Images;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * DTO (Data Transfer Object) for basic image information extracted from HTML
 * and potentially enriched with meta-data.
 */
final class ImageInfo
{
    // Basic image information from HTML attributes
    public string  $src;
    public string  $alt;
    public string  $title;
    public ?int    $width;
    public ?int    $height;
    public string  $srcset;
    public string  $sizes;
    
    // Meta-data populated by ImageMetaProvider
    public ?bool   $isLocal;
    public ?int    $attachmentId;
    public ?int    $size; // Size in bytes
    public ?string $format;
    public ?array  $metaDimensions; // ['width' => int, 'height' => int]
    
    // Analysis results populated by ImageAnalysisService
    public ?float  $optimizationPotential; // Potential calculated based on size/dimensions (0-1)
    public ?string $compressionLevelNeeded; // Level calculated based on size/dimensions (none, low, medium, high)

    /**
     * @param string $src The source URL of the image.
     * @param string $alt The alt text for the image.
     * @param string $title The title attribute for the image.
     * @param int|null $width The width of the image from HTML attributes.
     * @param int|null $height The height of the image from HTML attributes.
     * @param string $srcset The srcset attribute for responsive images.
     * @param string $sizes The size attribute for responsive images.
     */
    public function __construct(
        string  $src,
        string  $alt = '',
        string  $title = '', // Extracted title attribute
        // Dimensions from HTML attributes - might be null
        ?int    $width = null,
        ?int    $height = null,
        // Responsive attributes from HTML - might be empty strings
        string  $srcset = '',
        string  $sizes = '',
        // Meta-data populated by ImageMetaProvider
        ?bool   $isLocal = null,
        ?int    $attachmentId = null,
        ?int    $size = null, // Size in bytes
        ?string $format = null,
        // Dimensions obtained via getimagesize (potentially remote) - might differ from attributes
        ?array  $metaDimensions = null, // ['width' => int, 'height' => int]
        // Analysis results populated by ImageAnalysisService
        ?float  $optimizationPotential = null, // Potential calculated based on size/dimensions (0-1)
        ?string $compressionLevelNeeded = null, // Level calculated based on size/dimensions (none, low, medium, high)
    ) {
        $this->src = $src;
        $this->alt = $alt;
        $this->title = $title;
        $this->width = $width;
        $this->height = $height;
        $this->srcset = $srcset;
        $this->sizes = $sizes;
        $this->isLocal = $isLocal;
        $this->attachmentId = $attachmentId;
        $this->size = $size;
        $this->format = $format;
        $this->metaDimensions = $metaDimensions;
        $this->optimizationPotential = $optimizationPotential;
        $this->compressionLevelNeeded = $compressionLevelNeeded;
    }

    /**
     * Creates a new ImageInfo instance based on extracted HTML data.
     * This is the initial creation point from the Extractor.
     */
    public static function fromHtmlAttributes(
        string $src,
        string $alt = '',
        string $title = '',
        ?int $width = null,
        ?int $height = null,
        string $srcset = '',
        string $sizes = ''
    ): self {
        return new self($src, $alt, $title, $width, $height, $srcset, $sizes);
    }

    /**
     * Updates the ImageInfo instance with meta-data from the provider.
     * Returns the same instance (fluent interface).
     */
    public function withMeta(
        ?int $size,
        ?string $format,
        ?bool $isLocal,
        ?int $attachmentId,
        ?array $metaDimensions = null
    ): self {
        $this->size = $size;
        $this->format = $format;
        $this->isLocal = $isLocal;
        $this->attachmentId = $attachmentId;
        $this->metaDimensions = $metaDimensions;
        return $this;
    }

    /**
     * Updates the ImageInfo instance with analysis results.
     * Returns the same instance.
     */
    public function withAnalysis(
        ?float $optimizationPotential,
        ?string $compressionLevelNeeded
    ): self {
        $this->optimizationPotential = $optimizationPotential;
        $this->compressionLevelNeeded = $compressionLevelNeeded;
        return $this;
    }

    /**
     * Returns the most reliable dimensions available (meta first, then attributes).
     * @return array{width:int, height:int}|null
     */
    public function getEffectiveDimensions(): ?array
    {
        if ($this->metaDimensions !== null && $this->metaDimensions['width'] > 0 && $this->metaDimensions['height'] > 0) {
            return $this->metaDimensions;
        }
        if ($this->width !== null && $this->height !== null && $this->width > 0 && $this->height > 0) {
            return ['width' => $this->width, 'height' => $this->height];
        }
        return null;
    }
}
