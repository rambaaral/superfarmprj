<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Base;

if (!defined('ABSPATH')) { exit; }

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Enums\Suggestion;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Enums\SuggestionType;

/**
 * Class FactorSuggestion
 * 
 * Represents a specific SEO improvement suggestion related to an operation.
 */
class FactorSuggestion
{
    // Suggestion priorities
    /** @var int PRIORITY_LOW Lowest level of importance */
    public const PRIORITY_LOW = 4;
    /** @var int PRIORITY_MEDIUM Medium level of importance */
    public const PRIORITY_MEDIUM = 3;
    /** @var int PRIORITY_HIGH High level of importance - critical for SEO performance */
    public const PRIORITY_HIGH = 2;
    /** @var int PRIORITY_CRITICAL Most urgent that should be addressed immediately */
    public const PRIORITY_CRITICAL = 1;

    // Suggestion properties
    /** @var string $operationKey Reference to the SEO operation this suggestion belongs to */
    public string $operationKey = '';
    /** @var string $title Short descriptive title of the suggestion */
    public string $title = '';
    /** @var string $description Detailed explanation of what needs to be improved and how */
    public string $description = '';
    /** @var int $priority Importance level of this suggestion (uses PRIORITY_* constants) */
    public int $priority = self::PRIORITY_MEDIUM;
    /** @var float $activationThreshold Score threshold below which this suggestion is shown (0.0-1.0) */
    public float $activationThreshold = 1.0;
    /** @var string $issueType Categorization of the issue type (e.g., 'missing_primary_keyword', 'missing_meta_description') */
    public string $issueType = '';

    /** @var array<string> $additionalInfo Supplementary contextual information or specific data related to the suggestion */
    public array $additionalInfo = [];

    /** @var array $displayConfig Configuration for the badge display, including color and label */
    public array $displayConfig = [];

    /**
     * Constructor for creating a new suggestion
     *
     * @param string $operationKey The key of the operation this suggestion belongs to
     * @param string $title Short title/summary of the suggestion
     * @param string $description Detailed description of what needs to be improved
     * @param int $priority Importance of this suggestion (use PRIORITY_* constants)
     * @param float $activationThreshold Score threshold below which this suggestion is activated (0.0-1.0)
     * @param string $issueType Type of issue (e.g., 'missing_primary_keyword', 'missing_meta_description')
     * @param array $additionalInfo Any additional context or information
     */
    public function __construct(
        string $operationKey = '',
        string $title = '',
        string $description = '',
        int $priority = self::PRIORITY_MEDIUM,
        float $activationThreshold = 1.0,
        array $additionalInfo = [],
        string $issueType = ''
    ) {
        $this->operationKey = $operationKey;
        $this->title = $title;
        $this->description = $description;
        $this->priority = $priority;
        $this->activationThreshold = $activationThreshold;
        $this->additionalInfo = $additionalInfo;
        $this->issueType = $issueType;
    }

    /**
     * Get a user-friendly priority label
     * 
     * Converts the numeric priority value to a human-readable text representation
     * for display in the UI or reports.
     *
     * @return string Human-readable priority label (Low, Medium, High, Critical)
     */
    public function getPriorityLabel(): string
    {
        return match ($this->priority) {
            self::PRIORITY_LOW => __('Low', 'beyondseo'),
            self::PRIORITY_MEDIUM => __('Medium', 'beyondseo'),
            self::PRIORITY_HIGH => __('High', 'beyondseo'),
            self::PRIORITY_CRITICAL => __('Critical', 'beyondseo'),
            default => __('Unknown', 'beyondseo'),
        };
    }

    /**
     * Check if this is a high-priority suggestion
     * 
     * Determines if this suggestion should be prominently highlighted as high priority
     * based on its priority level being HIGH or CRITICAL.
     *
     * @return bool True if the suggestion is high priority or critical
     */
    public function isHighPriority(): bool
    {
        return $this->priority >= self::PRIORITY_HIGH;
    }

    /**
     * Get formatted action steps for implementing this suggestion
     * 
     * Provides specific instructions on how to implement the suggestion.
     * Currently, it returns the description but could be extended to provide
     * more structured action steps.
     *
     * @return string Formatted action steps as a string
     */
    public function getActionSteps(): string
    {
        // This could be further customized based on actionType and actionData
        return $this->description;
    }

    /**
     * Return a unique key for this suggestion
     *
     * @return string Unique identifier for this suggestion
     */
    public function uniqueKey(): string
    {
        return md5($this->issueType);
    }

    /**
     * Get badge color class based on priority and activation threshold
     * 
     * Combines priority (main factor) with activation threshold (intensity modifier)
     * to provide nuanced visual feedback for suggestion urgency.
     * 
     * @return string CSS color class (e.g., 'critical-dark', 'high-normal', 'medium-light')
     */
    public function getBadgeColor(): string
    {
        // Primary color based on priority
        $baseColor = match ($this->priority) {
            self::PRIORITY_CRITICAL => 'critical',    // Red family
            self::PRIORITY_HIGH => 'high',           // Orange family
            self::PRIORITY_MEDIUM => 'medium',       // Yellow family
            self::PRIORITY_LOW => 'low',            // Green family
            default => 'unknown'                     // Gray family
        };
        
        // Intensity based on activation threshold
        $intensity = $this->getThresholdIntensity();
        
        return $baseColor . '-' . $intensity;
    }

    /**
     * Get badge color as hex value based on priority and activation threshold
     * 
     * Alternative method that returns actual hex colors instead of CSS classes.
     * 
     * @return string Hex color value (e.g., '#DC2626', '#EA580C')
     */
    public function getBadgeColorHex(): string
    {
        $colorMap = [
            'critical-dark' => '#7F1D1D',   // red-900
            'critical-normal' => '#DC2626', // red-600
            'critical-light' => '#FCA5A5',  // red-300
            
            'high-dark' => '#9A3412',       // orange-900
            'high-normal' => '#EA580C',     // orange-600
            'high-light' => '#FDBA74',      // orange-300
            
            'medium-dark' => '#92400E',     // yellow-900
            'medium-normal' => '#CA8A04',   // yellow-600
            'medium-light' => '#FDE68A',    // yellow-300
            
            'low-dark' => '#14532D',        // green-900
            'low-normal' => '#16A34A',      // green-600
            'low-light' => '#86EFAC',       // green-300
            
            'unknown-dark' => '#374151',    // gray-700
            'unknown-normal' => '#6B7280',  // gray-500
            'unknown-light' => '#D1D5DB',   // gray-300
        ];
        
        $colorClass = $this->getBadgeColor();
        return $colorMap[$colorClass] ?? $colorMap['unknown-normal'];
    }

    /**
     * Get intensity modifier based on activation threshold
     * 
     * Lower threshold = more urgent = darker color
     * Higher threshold = less urgent = lighter color
     * 
     * @return string Intensity level ('light', 'normal', 'dark')
     */
    private function getThresholdIntensity(): string
    {
        return match (true) {
            $this->activationThreshold >= 0.7 => 'light',   // Less urgent (300 shade)
            $this->activationThreshold >= 0.4 => 'normal',  // Normal urgency (600 shade)
            default => 'dark'                                // Very urgent (900 shade)
        };
    }

    /**
     * Get a complete badge configuration for frontend use
     * 
     * Returns a comprehensive array with all badge-related information
     * for easy frontend integration.
     * 
     * @return array Badge configuration with class, hex, label, and metadata
     */
    public function getBadgeConfig(): array
    {
        return [
            'class' => $this->getBadgeColor(),
            'hex' => $this->getBadgeColorHex(),
            'label' => $this->getPriorityLabel(),
            'priority' => $this->priority,
            'threshold' => $this->activationThreshold,
            'intensity' => $this->getThresholdIntensity(),
        ];
    }

    /**
     * Create a FactorSuggestion from an enum Suggestion type
     *
     * This static method allows creating a FactorSuggestion instance
     * directly from a Suggestion enum value, encapsulating the logic
     * for converting enum data into a structured suggestion object.
     *
     * @param string $suggestionIssueType The enum type representing the suggestion
     * @param string $operationKey The key of the operation this suggestion belongs to
     * @return self A new FactorSuggestion instance populated with enum data
     */
    public static function createFromEnum(string $suggestionIssueType, string $operationKey): self
    {
        $suggestion = Suggestion::getDescription($suggestionIssueType);
        return new self(
                $operationKey,
                $suggestion['title'],
                $suggestion['description'],
                $suggestion['priority'],
                $suggestion['threshold'],
                $suggestion['additionalInfo'],
                $suggestionIssueType
        );
    }
}
