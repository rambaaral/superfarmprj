<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Base;

if (!defined('ABSPATH')) { exit; }

/**
 * Class GenericOperation
 * 
 * Concrete fallback class for abstract Operation.
 */
class GenericOperation extends Operation
{
    /**
     * Run fallback
     * 
     * @return array|null
     */
    public function run(): ?array
    {
        return $this->value;
    }

    /**
     * Calculate score fallback
     * 
     * @return float
     */
    public function calculateScore(): float
    {
        return $this->score;
    }

    /**
     * Suggestions fallback
     * 
     * @return array
     */
    public function suggestions(): array
    {
        return $this->suggestions;
    }
}
