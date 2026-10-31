<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Base;

if (!defined('ABSPATH')) { exit; }

use IteratorAggregate;
use Countable;
use ArrayIterator;

/**
 * Class OptimiserContexts
 * 
 * Collection of OptimiserContext objects.
 */
class OptimiserContexts implements IteratorAggregate, Countable
{
    /** @var OptimiserContext[] */
    public array $elements = [];

    /**
     * Add a context to the collection
     *
     * @param OptimiserContext $context
     * @return void
     */
    public function add(OptimiserContext $context): void
    {
        $this->elements[] = $context;
    }

    /**
     * Get all elements in the collection
     *
     * @return OptimiserContext[]
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
     * @return OptimiserContext|null
     */
    public function first(): ?OptimiserContext
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
     * Get the weighted score of all contexts
     */
    public function getWeightedScore(): float
    {
        if ($this->count() === 0) {
            return 0.0;
        }
        $totalScore = 0.0;
        $totalWeight = 0.0;
        
        foreach ($this->elements as $context) {
            $totalScore += $context->score * $context->weight;
            $totalWeight += $context->weight;
        }
        return $totalWeight > 0.0 ? $totalScore / $totalWeight : 0.0;
    }

    /**
     * Calculate the score for all contexts
     */
    public function calculateAllScores(): void
    {
        foreach ($this->elements as $context) {
            $context->calculateScore();
        }
    }

    /**
     * Get a context by its key
     * @param string $contextKey The key for the context
     * @return OptimiserContext|null The context if found, null otherwise
     */
    public function getContextByKey(string $contextKey): ?OptimiserContext
    {
        foreach ($this->elements as $context) {
            if ($context->contextKey === $contextKey) {
                return $context;
            }
        }
        return null;
    }

    /**
     * Get a context by its unique key
     *
     * @param string $uniqueKey
     * @return OptimiserContext|null
     */
    public function getByUniqueKey(string $uniqueKey): ?OptimiserContext
    {
        foreach ($this->elements as $element) {
            if ($element->uniqueKey() === $uniqueKey) {
                return $element;
            }
        }
        return null;
    }
}
