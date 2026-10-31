<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Base;

if (!defined('ABSPATH')) { exit; }

use IteratorAggregate;
use Countable;
use ArrayIterator;

/**
 * Class Factors
 * 
 * Collection of Factor objects.
 */
class Factors implements IteratorAggregate, Countable
{
    /** @var Factor[] */
    public array $elements = [];

    /**
     * Add a factor to the collection
     *
     * @param Factor $factor
     * @return void
     */
    public function add(Factor $factor): void
    {
        $this->elements[] = $factor;
    }

    /**
     * Get all elements in the collection
     *
     * @return Factor[]
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
     * @return Factor|null
     */
    public function first(): ?Factor
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
     * Calculate scores for all factors
     * @return void
     */
    public function calculateAllScores(): void
    {
        foreach ($this->elements as $factor) {
            $factor->calculateScore();
        }
    }

    /**
     * Get the average score of all factors
     * @return float The average score
     */
    public function getAverageScore(): float
    {
        $count = count($this->elements);
        if ($count === 0) {
            return 0.0;
        }

        $totalScore = 0.0;
        foreach ($this->elements as $factor) {
            $totalScore += $factor->score;
        }

        return $totalScore / $count;
    }

    /**
     * Get the weighted score of all factors
     * @return float The weighted score
     */
    public function getWeightedScore(): float
    {
        $totalWeight = 0.0;
        $weightedSum = 0.0;

        foreach ($this->elements as $factor) {
            $weightedSum += $factor->score * $factor->weight;
            $totalWeight += $factor->weight;
        }

        if ($totalWeight === 0.0) {
            return 0.0;
        }

        return $weightedSum / $totalWeight;
    }

    /**
     * Get a factor by its unique key
     *
     * @param string $uniqueKey
     * @return Factor|null
     */
    public function getByUniqueKey(string $uniqueKey): ?Factor
    {
        foreach ($this->elements as $element) {
            if ($element->uniqueKey() === $uniqueKey) {
                return $element;
            }
        }
        return null;
    }
}
