<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser;

if (!defined('ABSPATH')) { exit; }

use IteratorAggregate;
use Countable;
use ArrayIterator;

/**
 * Class SeoOptimisers
 *
 * This class is responsible for managing a collection of SEO optimizers.
 */
class SeoOptimisers implements IteratorAggregate, Countable
{
    /** @var SeoOptimiser[] */
    private array $elements = [];

    /**
     * Add an optimizer to the collection
     *
     * @param SeoOptimiser $optimiser
     * @return void
     */
    public function add(SeoOptimiser $optimiser): void
    {
        $this->elements[] = $optimiser;
    }

    /**
     * Get all elements in the collection
     *
     * @return SeoOptimiser[]
     */
    public function getElements(): array
    {
        return $this->elements;
    }

    /**
     * Get the number of elements in the collection
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->elements);
    }

    /**
     * Get the first element in the collection
     *
     * @return SeoOptimiser|null
     */
    public function first(): ?SeoOptimiser
    {
        return !empty($this->elements) ? reset($this->elements) : null;
    }

    /**
     * Get an iterator for the collection
     *
     * @return ArrayIterator
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->elements);
    }

    /**
     * Get an optimizer by its unique key
     *
     * @param string $uniqueKey
     * @return SeoOptimiser|null
     */
    public function getByUniqueKey(string $uniqueKey): ?SeoOptimiser
    {
        foreach ($this->elements as $element) {
            if ($element->uniqueKey() === $uniqueKey) {
                return $element;
            }
        }
        return null;
    }
}
