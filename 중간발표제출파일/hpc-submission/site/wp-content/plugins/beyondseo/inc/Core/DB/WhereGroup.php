<?php

namespace RankingCoach\Inc\Core\DB;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Helper class for building WHERE conditions that will be joined with OR.
 */
class WhereGroup
{
    /**
     * The parent Database instance.
     *
     * @var Database
     */
    private $db;

    /**
     * The conditions to be joined with OR.
     *
     * @var array
     */
    private $conditions = [];

    /**
     * Constructor.
     *
     * @param Database $db The parent Database instance.
     */
    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Add a WHERE condition to the group.
     *
     * @param string $column The column name.
     * @param mixed $value The value to compare against.
     * @param string $operator The comparison operator.
     * @return self
     */
    public function where(string $column, $value, string $operator = '='): self
    {
        $escapedValue = $this->db->escapeValue($value);
        $this->conditions[] = "$column $operator $escapedValue";
        return $this;
    }

    /**
     * Add a WHERE IN condition to the group.
     *
     * @param string $column The column name.
     * @param array $values The values to compare against.
     * @return self
     */
    public function whereIn(string $column, array $values): self
    {
        $escapedValues = array_map([$this->db, 'escapeValue'], $values);
        $this->conditions[] = "$column IN (" . implode(', ', $escapedValues) . ")";
        return $this;
    }

    /**
     * Add a WHERE NOT IN condition to the group.
     *
     * @param string $column The column name.
     * @param array $values The values to compare against.
     * @return self
     */
    public function whereNotIn(string $column, array $values): self
    {
        $escapedValues = array_map([$this->db, 'escapeValue'], $values);
        $this->conditions[] = "$column NOT IN (" . implode(', ', $escapedValues) . ")";
        return $this;
    }

    /**
     * Add a WHERE LIKE condition to the group.
     *
     * @param string $column The column name.
     * @param string $value The value to compare against.
     * @return self
     */
    public function whereLike(string $column, string $value): self
    {
        $escapedValue = $this->db->escapeValue("%$value%");
        $this->conditions[] = "$column LIKE $escapedValue";
        return $this;
    }

    /**
     * Add a WHERE NULL condition to the group.
     *
     * @param string $column The column name.
     * @return self
     */
    public function whereNull(string $column): self
    {
        $this->conditions[] = "$column IS NULL";
        return $this;
    }

    /**
     * Add a WHERE NOT NULL condition to the group.
     *
     * @param string $column The column name.
     * @return self
     */
    public function whereNotNull(string $column): self
    {
        $this->conditions[] = "$column IS NOT NULL";
        return $this;
    }

    /**
     * Add a raw WHERE condition to the group.
     *
     * @param string $condition The raw SQL condition.
     * @return self
     */
    public function whereRaw(string $condition): self
    {
        $this->conditions[] = $condition;
        return $this;
    }

    /**
     * Get the conditions in this group.
     *
     * @return array The conditions.
     */
    public function getConditions(): array
    {
        return $this->conditions;
    }
}
