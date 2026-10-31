<?php
/** @noinspection PhpTooManyParametersInspection */
/** @noinspection PhpUsageOfSilenceOperatorInspection */
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Classes\Images;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use ArrayIterator;
use IteratorAggregate;
use Countable;
use Traversable;

/**
 * Collection helper for ImageInfo objects. Provides basic array-like functionality
 * and de-duplication by image source URL.
 */
final class ImageCollection implements IteratorAggregate, Countable
{
    /** @var ImageInfo[] */
    private array $images = [];
    /** @var array<string,bool> */
    private array $processedUrls = []; // Helper to prevent duplicates based on src

    /** @param ImageInfo[] $images */
    public function __construct(array $images = [])
    {
        foreach ($images as $img) {
            $this->push($img); // Use push to handle duplicates if any initial array is passed
        }
    }

    /** @return Traversable<int, ImageInfo> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->images);
    }

    /**
     * Returns the number of images in the collection.
     * Implements Countable interface for array-like behavior.
     */
    public function count(): int
    {
        return count($this->images);
    }

    /** @return ImageInfo[] */
    public function all(): array
    {
        return $this->images;
    }

    /** @return ImageInfo[] */
    public function toArray(): array
    {
        return $this->images;
    }

    /**
     * Adds an ImageInfo object to the collection if its source URL hasn't been processed yet.
     */
    public function push(ImageInfo $img): void
    {
        if (!isset($this->processedUrls[$img->src])) {
            $this->images[] = $img;
            $this->processedUrls[$img->src] = true;
        }
    }

    /**
     * Retrieves an ImageInfo object by its source URL.
     */
    public function getByUrl(string $url): ?ImageInfo
    {
        // Optimized lookup using the processedUrls index is not direct with array_search on objects
        // Iterate for now, or restructure to use URL as a key if that's the primary access pattern.
        // Iteration is fine for typical numbers of images on a page.
        foreach ($this->images as $img) {
            if ($img->src === $url) {
                return $img;
            }
        }
        return null;
    }

    /**
     * Check if an image with the given URL exists in the collection.
     */
    public function hasUrl(string $url): bool
    {
        return isset($this->processedUrls[$url]);
    }
}