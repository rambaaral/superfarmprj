<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Base;

if (!defined('ABSPATH')) { exit; }

use IteratorAggregate;
use Countable;
use ArrayIterator;

/**
 * Class FactorSuggestions
 * 
 * A collection class that manages sets of SEO optimization suggestions (FactorSuggestion objects).
 */
class FactorSuggestions implements IteratorAggregate, Countable
{
    /** @var FactorSuggestion[] */
    public array $elements = [];

    /**
     * Add a suggestion to the collection
     *
     * @param FactorSuggestion $suggestion
     * @return void
     */
    public function add(FactorSuggestion $suggestion): void
    {
        $this->elements[] = $suggestion;
    }

    /**
     * Get all elements in the collection
     *
     * @return FactorSuggestion[]
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
     * @return FactorSuggestion|null
     */
    public function first(): ?FactorSuggestion
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
     * Get a suggestion by its unique key
     *
     * @param string $uniqueKey
     * @return FactorSuggestion|null
     */
    public function getByUniqueKey(string $uniqueKey): ?FactorSuggestion
    {
        foreach ($this->elements as $element) {
            if ($element->uniqueKey() === $uniqueKey) {
                return $element;
            }
        }
        return null;
    }

    /**
     * Order factor suggestions by a specific field
     *
     * @param string $field The field name to sort by (e.g., 'priority', 'activationThreshold')
     * @param string $order The sort direction ('ASC' for ascending, 'DESC' for descending)
     * @return static Returns the sorted collection instance for method chaining
     */
    public function orderBy(string $field, string $order = 'ASC'): static
    {
        $elements = $this->elements;
        usort($elements, static function ($a, $b) use ($field, $order) {
            $aValue = isset($a->{$field}) ? $a->{$field} : 0;
            $bValue = isset($b->{$field}) ? $b->{$field} : 0;
            if ($order === 'ASC') {
                return $aValue <=> $bValue;
            }
            return $bValue <=> $aValue;
        });

        $this->elements = array_values($elements);
        return $this;
    }
}
