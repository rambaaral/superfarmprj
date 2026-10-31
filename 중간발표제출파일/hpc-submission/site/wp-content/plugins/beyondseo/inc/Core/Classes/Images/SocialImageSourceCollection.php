<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Classes\Images;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use ArrayIterator;
use Countable;
use IteratorAggregate;

/**
 * Class SocialImageSourceCollection
 * Collection of SocialImageSource objects
 */
final class SocialImageSourceCollection implements IteratorAggregate, Countable
{
    /**
     * @var SocialImageSource[] Array of SocialImageSource objects
     */
    private array $sources = [];

    /**
     * @param SocialImageSource[] $sources Initial sources
     */
    public function __construct(array $sources = [])
    {
        foreach ($sources as $source) {
            if ($source instanceof SocialImageSource) {
                $this->sources[] = $source;
            }
        }
    }

    /**
     * Adds a SocialImageSource to the collection
     * 
     * @param SocialImageSource $source The source to add
     * @return self For method chaining
     */
    public function add(SocialImageSource $source): self
    {
        $this->sources[] = $source;
        return $this;
    }

    /**
     * Creates and adds a new SocialImageSource to the collection
     * 
     * @param string $label The descriptive label of the image source
     * @param string|null $value The URL value of the image
     * @param string $source The source identifier
     * @return self For method chaining
     */
    public function addNew(string $label, ?string $value, string $source): self
    {
        $this->sources[] = new SocialImageSource($label, $value, $source, false);
        return $this;
    }

    /**
     * Creates and adds a new SocialImageSource to the collection
     *
     * @param string $label The descriptive label of the image source
     * @param string|null $value The URL value of the image
     * @param string $source The source identifier
     * @return self For method chaining
     */
    public function addDefaultNew(string $label, ?string $value, string $source): self
    {
        $this->sources[] = new SocialImageSource(
            sprintf(
                /* translators: %s: image source label */
                __('Default image source (%s)', 'beyondseo'),
                $label
            ),
            $value,
            $source,
            true
        );
        return $this;
    }



    /**
     * Gets all sources as an array of SocialImageSource objects
     * 
     * @return SocialImageSource[] Array of SocialImageSource objects
     */
    public function all(): array
    {
        return $this->sources;
    }

    /**
     * Converts the collection to an array of arrays
     * 
     * @return array Array representation of all sources
     */
    public function toArray(): array
    {
        return array_map(fn(SocialImageSource $source) => $source->toArray(), $this->sources);
    }

    /**
     * Creates a collection from an array of arrays
     * 
     * @param array $data Array of source data arrays
     * @return self The created collection
     */
    public static function fromArray(array $data): self
    {
        $collection = new self();
        foreach ($data as $item) {
            if (is_array($item)) {
                $collection->add(SocialImageSource::fromArray($item));
            }
        }
        return $collection;
    }

    /**
     * Filters sources by a specific source type
     * 
     * @param string $sourceType The source type to filter by
     * @return self A new collection with filtered sources
     */
    public function filterBySource(string $sourceType): self
    {
        return new self(
            array_filter($this->sources, fn(SocialImageSource $source) => $source->getSource() === $sourceType)
        );
    }

    /**
     * Gets the first source with a non-null value
     * 
     * @return SocialImageSource|null The first valid source or null if none found
     */
    public function getFirstValid(): ?SocialImageSource
    {
        foreach ($this->sources as $source) {
            if ($source->getValue() !== null) {
                return $source;
            }
        }
        return null;
    }

    /**
     * Implements IteratorAggregate interface
     * 
     * @return ArrayIterator Iterator for the sources
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->sources);
    }

    /**
     * Implements Countable interface
     * 
     * @return int The number of sources in the collection
     */
    public function count(): int
    {
        return count($this->sources);
    }
}
