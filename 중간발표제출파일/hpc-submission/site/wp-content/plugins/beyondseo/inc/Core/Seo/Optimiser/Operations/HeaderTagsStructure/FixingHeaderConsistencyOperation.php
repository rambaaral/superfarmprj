<?php /** @noinspection PhpUnnecessaryCurlyVarSyntaxInspection */
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection PhpComplexClassInspection */
/** @noinspection PhpMissingParentCallCommonInspection */
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Operations\HeaderTagsStructure;

if (!defined('ABSPATH')) { exit; }

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Enums\Suggestion;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Interfaces\OperationInterface;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Operation;

/**
 * Class FixingHeaderConsistencyOperation
 *
 * This class is responsible for checking the consistency of header tags in a WordPress post.
 */
#[SeoMeta(
    name: 'Fixing Header Consistency',
    weight: WeightConfiguration::WEIGHT_FIXING_HEADER_CONSISTENCY_OPERATION,
    description: 'Evaluates header tag order and structure to ensure consistent hierarchy. Checks for single H1, avoiding jumps or improper resets, and provides suggestions to improve heading organization for better readability and SEO.',
)]
class FixingHeaderConsistencyOperation extends Operation implements OperationInterface
{
    private const SCORE_H1_OK = 0.4;
    private const SCORE_NO_LEVEL_JUMPS = 0.3;
    private const SCORE_NO_IMPROPER_RESETS = 0.2;
    private const SCORE_STARTS_WITH_H1 = 0.1;

    /**
     * Function for running the operation
     * @return array|null
     */
    public function run(): ?array
    {
        $content = $this->contentProvider->getContent($this->postId);

        if (empty($content)) {
            return [
                'success' => false,
                'message' => __('Unable to retrieve content', 'beyondseo'),
            ];
        }


        $headings = $this->contentProvider->getHeadingsFromContent($content);

        $h1Count = 0;
        $previousLevel = 0;
        $levelJumpIssues = [];
        $improperResets = [];

        foreach ($headings as $index => $heading) {
            $levelStr = strtolower($heading['level'] ?? '');
            $levelInt = (int) str_replace('h', '', $levelStr);

            if ($levelInt === 1) {
                $h1Count++;
            }

            if ($previousLevel === 0) {
                $previousLevel = $levelInt;
                continue;
            }

            /**
             * Check for level jump (e.g., h2 → h4)
             */
            if ($levelInt > $previousLevel + 1) {
                $levelJumpIssues[] = [
                    'position' => $index,
                    'from' => "h{$previousLevel}",
                    'to' => "h{$levelInt}"
                ];
            }

            /**
             * Check for improper reset (e.g. h3 → h1)
             */
            if ($levelInt === 1 && $previousLevel > 1) {
                $improperResets[] = [
                    'position' => $index,
                    'reset_from' => "h{$previousLevel}",
                    'to' => 'h1'
                ];
            }

            $previousLevel = $levelInt;
        }

        return [
            'success' => true,
            'message' => __('Header consistency check completed successfully', 'beyondseo'),
            'headings' => $headings,
            'h1_count' => $h1Count,
            'has_missing_h1' => $h1Count === 0,
            'has_multiple_h1' => $h1Count > 1,
            'level_jumps' => $levelJumpIssues,
            'improper_resets' => $improperResets
        ];
    }

    /**
     * Calculate operation score
     * @return float
     */
    public function calculateScore(): float {
        $score = 0.0;
        $structure = $this->value;

        // 1. Exactly one H1
        if ($structure['h1_count'] === 1) {
            $score += self::SCORE_H1_OK;
        }

        // 2. No level jumps
        if (empty($structure['level_jumps'])) {
            $score += self::SCORE_NO_LEVEL_JUMPS;
        }

        // 3. No improper resets
        if (empty($structure['improper_resets'])) {
            $score += self::SCORE_NO_IMPROPER_RESETS;
        }

        // 4. Starts with H1
        if (!empty($structure['headings']) && strtolower($structure['headings'][0]['level']) === 'h1') {
            $score += self::SCORE_STARTS_WITH_H1;
        }

        return round(min($score, 1.0), 2);
    }

    /**
     * Return the operation's suggestions
     * @return Suggestion[]
     */
    public function suggestions(): array
    {
        $suggestions = [];
        $structure = $this->value;

        if ($structure['has_missing_h1']) {
            $suggestions[] = Suggestion::MISSING_H1_TAG;
        }

        if ($structure['has_multiple_h1']) {
            $suggestions[] = Suggestion::MULTIPLE_H1_TAGS_FOUND;
        }

        if (!empty($structure['level_jumps']) || !empty($structure['improper_resets'])) {
            $suggestions[] = Suggestion::IMPROPER_HEADING_NESTING;
        }

        // Optional: first heading should ideally be h1
        if (!empty($structure['headings']) && strtolower($structure['headings'][0]['level']) !== 'h1') {
            // Could use a custom suggestion if you wish
             $suggestions[] = Suggestion::FIRST_HEADING_NOT_H1;
        }

        return $suggestions;
    }

}