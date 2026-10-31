<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Base;

if (!defined('ABSPATH')) { exit; }

use IteratorAggregate;
use Countable;
use ArrayIterator;

/**
 * Class Operations
 * 
 * Collection of Operation objects.
 */
class Operations implements IteratorAggregate, Countable
{
    /** @var Operation[] */
    public array $elements = [];

    /**
     * Add an operation to the collection
     *
     * @param Operation $operation
     * @return void
     */
    public function add(Operation $operation): void
    {
        $this->elements[] = $operation;
    }

    /**
     * Get all elements in the collection
     *
     * @return Operation[]
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
     * @return Operation|null
     */
    public function first(): ?Operation
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
     * Gets the average score of all operations
     * @return float The average score
     */
    public function getAverageScore(): float
    {
        $count = count($this->elements);
        if ($count === 0) {
            return 0.0;
        }

        $totalScore = 0.0;
        foreach ($this->elements as $operation) {
            $totalScore += $operation->getScore();
        }

        return $totalScore / $count;
    }

    /**
     * Gets the weighted average score of all operations
     * @return float The weighted average score
     */
    public function getWeightedScore(): float
    {
        $totalWeight = 0.0;
        $weightedSum = 0.0;
        
        foreach ($this->elements as $operation) {
            $weightedSum += $operation->getScore() * $operation->weight;
            $totalWeight += $operation->weight;
        }
        
        if ($totalWeight === 0.0) {
            return 0.0;
        }
        
        return $weightedSum / $totalWeight;
    }

    /**
     * Get an operation by its unique key
     *
     * @param string $uniqueKey
     * @return Operation|null
     */
    public function getByUniqueKey(string $uniqueKey): ?Operation
    {
        foreach ($this->elements as $element) {
            if ($element->uniqueKey() === $uniqueKey) {
                return $element;
            }
        }
        return null;
    }
}
