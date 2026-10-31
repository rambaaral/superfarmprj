<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Base\Interfaces;

if (!defined('ABSPATH')) { exit; }

/**
 * Interface OperationInterface
 * 
 * Defines the contract for SEO optimization operations.
 * All Operation classes must implement these core methods to standardize the optimization workflow.
 */
interface OperationInterface
{
    /**
     * Performs the actual operation or analysis on the content.
     * 
     * This method should execute the core logic of the SEO operation,
     * analyze the content, and collect data for scoring and suggestions.
     *
     * @return array|null The collected data or null if analysis couldn't be performed
     */
    public function run(): ?array;

    /**
     * Evaluates the operation and returns a normalized score (0.0-1.0).
     * 
     * This method should calculate how well the content meets the SEO criteria
     * for this specific operation based on the collected data.
     *
     * @return float A normalized score between 0.0 and 1.0
     */
    public function calculateScore(): float;

    /**
     * Generates suggestions based on the analyzed data.
     * 
     * This method should identify issues and provide actionable suggestions
     * to improve the SEO aspect measured by this operation.
     *
     * @return array List of suggestion issue types applicable to the analyzed content
     */
    public function suggestions(): array;
}
