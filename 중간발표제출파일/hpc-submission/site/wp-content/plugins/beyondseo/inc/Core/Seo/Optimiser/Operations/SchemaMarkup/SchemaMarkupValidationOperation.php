<?php
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection PhpComplexClassInspection */
/** @noinspection PhpMissingParentCallCommonInspection */
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Operations\SchemaMarkup;

if (!defined('ABSPATH')) { exit; }

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Enums\Suggestion;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Interfaces\OperationInterface;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Operation;

/**
 * Class SchemaMarkupValidationOperation
 *
 * This class is responsible for validating schema markup implementation
 * according to Google's structured data guidelines.
 */
#[SeoMeta(
    name: 'Schema Markup Validation',
    weight: WeightConfiguration::WEIGHT_SCHEMA_MARKUP_VALIDATION_OPERATION,
    description: 'Validates structured data against Google\'s guidelines to ensure markup is properly implemented. Checks schema types and required fields, reporting errors so search engines can display rich snippets accurately.',
)]
class SchemaMarkupValidationOperation extends Operation implements OperationInterface
{
    /**
     * Validates schema markup for the given post-ID according to Google's structured data guidelines.
     *
     * @return array|null The validation results or null if the post-ID is invalid
     */
    public function run(): ?array
    {
        $postId = $this->postId;

        // Get the full HTML content
        $htmlContent = $this->contentProvider->getContent($postId);

        if (empty($htmlContent)) {
            return [
                'success' => false,
                'message' => __('Failed to retrieve content', 'beyondseo'),
                'schema_found' => false,
            ];
        }

        // Extract schema.org data from the HTML
        $schemas = $this->contentProvider->extractSchemaData($htmlContent);

        if (empty($schemas)) {
            return [
                'success' => false,
                'message' => __('No schema markup found', 'beyondseo'),
                'schema_found' => false,
            ];
        }

        // Find LocalBusiness schema if present
        $localBusinessSchema = $this->contentProvider->findLocalBusinessSchema($schemas);

        // Validate schema for all schemas
        $validationResults = $this->contentProvider->validateSchemas($schemas);

        // Add specific validation for LocalBusiness if found
        if ($localBusinessSchema !== null) {
            $localBusinessValidation = $this->contentProvider->validateLocalBusinessSchema($localBusinessSchema);
            $validationResults['local_business_validation'] = $localBusinessValidation;
        }

        // Check if the page is relevant for local business schema
        $isRelevantPage = false;
        if ($localBusinessSchema === null) {
            $postType = $this->contentProvider->getPostType($postId);
            $isRelevantPage = $this->contentProvider->isRelevantPageType($postId, $postType);

            // If the page is relevant for local business but doesn't have the schema, note it
            if ($isRelevantPage) {
                $validationResults['warnings'][] = [
                    'warning' => __('This page appears to be relevant for LocalBusiness schema but does not have it implemented.', 'beyondseo'),
                ];
            }
        }

        return [
            'success' => true,
            'message' => __('Schema markup validation completed', 'beyondseo'),
            'schema_found' => true,
            'schema_count' => count($schemas),
            'schema_types' => $this->contentProvider->extractSchemaTypes($schemas),
            'validation_results' => $validationResults,
            'has_local_business_schema' => $localBusinessSchema !== null,
            'is_relevant_for_local_business' => $isRelevantPage,
        ];
    }

    /**
     * Evaluate the operation value based on schema markup validation.
     *
     * @return float A score based on the validation
     */
    public function calculateScore(): float
    {
        $factorData = $this->value;

        // Check if schema was found
        if (!isset($factorData['schema_found']) || !$factorData['schema_found']) {
            return 0;
        }

        // Get validation results
        $validationResults = $factorData['validation_results'] ?? [];

        if (empty($validationResults)) {
            return 0;
        }

        // Calculate score based on valid schemas percentage
        $validSchemas = $validationResults['valid_schemas'] ?? 0;
        $invalidSchemas = $validationResults['invalid_schemas'] ?? 0;
        $totalSchemas = $validSchemas + $invalidSchemas;

        if ($totalSchemas === 0) {
            return 0;
        }

        // Base score from a valid schemas ratio
        $baseScore = $validSchemas / $totalSchemas;

        // Apply penalties for issues and warnings
        $issues = $validationResults['issues'] ?? [];
        $warnings = $validationResults['warnings'] ?? [];

        $issuesPenalty = min(0.5, count($issues) * 0.05); // 5% penalty per issue, max 50%
        $warningsPenalty = min(0.3, count($warnings) * 0.02); // 2% penalty per warning, max 30%

        // Calculate final score
        $finalScore = max(0, $baseScore - $issuesPenalty - $warningsPenalty);

        // Bonus for LocalBusiness schema if applicable
        if (isset($validationResults['local_business_validation']) &&
            $validationResults['local_business_validation']['valid']) {
            $completeness = $validationResults['local_business_validation']['completeness'] / 100;
            $finalScore = min(1, $finalScore + ($completeness * 0.1)); // Add up to 10% bonus
        }

        return $finalScore;
    }

    /**
     * Get suggestions for the operation based on validation results.
     *
     * @return array An array of suggestions
     */
    public function suggestions(): array
    {
        $suggestions = [];
        $factorData = $this->value;

        if (empty($factorData)) {
            return [Suggestion::TECHNICAL_SEO_ISSUES];
        }

        // Check if schema was found
        if (!isset($factorData['schema_found']) || !$factorData['schema_found']) {
            return [Suggestion::MISSING_SCHEMA_MARKUP];
        }

        // Get validation results
        $validationResults = $factorData['validation_results'] ?? [];

        if (empty($validationResults)) {
            return [Suggestion::TECHNICAL_SEO_ISSUES];
        }

        // Get issues and warnings
        $issues = $validationResults['issues'] ?? [];
        $warnings = $validationResults['warnings'] ?? [];

        // Add suggestions based on issues found
        if (count($issues) > 0) {
            $suggestions[] = Suggestion::TECHNICAL_SEO_ISSUES;
        }

        // Check for specific issues to provide targeted suggestions
        $issueMessages = array_column($issues, 'issue');

        // Check for missing required properties
        foreach ($issueMessages as $message) {
            if (str_contains($message, 'Missing required properties')) {
                $suggestions[] = Suggestion::SCHEMA_MARKUP_VALIDATION_FAILED;
                break;
            }
            if (str_contains($message, 'Unexpected type') || str_contains($message, 'type mismatch')) {
                $suggestions[] = Suggestion::IMPROPER_SCHEMA_TYPE_USED;
            }
        }

        // Check if the page is relevant for local business but doesn't have the schema
        $isRelevantForLocalBusiness = $factorData['is_relevant_for_local_business'] ?? false;
        $hasLocalBusinessSchema = $factorData['has_local_business_schema'] ?? false;

        if ($isRelevantForLocalBusiness && !$hasLocalBusinessSchema) {
            $suggestions[] = Suggestion::MISSING_SCHEMA_MARKUP;
        }

        // Use a custom approach to get unique enum values
        return $this->getUniqueEnumValues($suggestions);
    }
}