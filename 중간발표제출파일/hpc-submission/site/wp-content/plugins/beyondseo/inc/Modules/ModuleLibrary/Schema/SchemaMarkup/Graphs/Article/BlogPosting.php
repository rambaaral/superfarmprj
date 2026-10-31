<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Graphs\Article;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Exception;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\SchemaManager;
use RankingCoach\Inc\Modules\ModuleManager;
use ReflectionException;
use WP_Post;

/**
 * BlogPosting schema markup generator.
 * 
 * Extends SocialMediaPosting to provide BlogPosting-specific structured data
 * with comprehensive blog metadata, proper Schema.org inheritance, and enhanced
 * validation for blog-specific properties.
 */
class BlogPosting extends SocialMediaPosting {

    /**
     * Generates BlogPosting schema markup data.
     *
     * @param object|null $graphData Optional custom graph data configuration.
     * @return array Schema.org BlogPosting structured data array.
     * @throws ReflectionException When reflection operations fail.
     * @throws Exception When critical dependencies are unavailable.
     */
    public function get( $graphData = null ): array {
        /** @var ModuleManager $moduleManager */
        $moduleManager = ModuleManager::instance();
        /** @var SchemaManager $schema */
        $schema = $moduleManager->get_module('schemaMarkup')->schema;

        // Handle overwrite data with validation
        $graphData = $this->processOverwriteData( $graphData );

        // Get base Article data
        $data = parent::get( $graphData );
        if ( empty( $data ) ) {
            return [];
        }

        // Validate critical BlogPosting requirements
        if ( ! $this->validateBlogPostingRequirements( $data ) ) {
            return [];
        }

        // Override type and enhance ID for BlogPosting
        $data['@type'] = 'BlogPosting';
        $data['@id'] = $this->generateBlogPostingId( $graphData, $schema );

        // Add BlogPosting-specific enhancements
        $this->enhanceBlogPostingData( $data, $graphData );

        return $data;
    }

    /**
     * Processes overwrite data with proper validation and merging.
     *
     * @param object|null $graphData Original graph data.
     * @return object|null Processed graph data.
     */
    private function processOverwriteData( $graphData ): ?object {
        if ( empty( self::$overwriteGraphData[ __CLASS__ ] ) ) {
            return $graphData;
        }

        $overwriteData = self::$overwriteGraphData[ __CLASS__ ];
        
        // Validate overwrite data structure
        if ( ! is_array( $overwriteData ) ) {
            return $graphData;
        }

        // Merge with existing data, prioritizing overwrite values
        $mergedData = $graphData ? (array) $graphData : [];
        $mergedData = wp_parse_args( $overwriteData, $mergedData );

        return json_decode( wp_json_encode( $mergedData ) );
    }

    /**
     * Validates critical BlogPosting requirements.
     *
     * @param array $data Schema data to validate.
     * @return bool True if valid, false otherwise.
     */
    private function validateBlogPostingRequirements( array $data ): bool {
        // Critical properties for BlogPosting
        $requiredProperties = [ 'headline', 'author', 'datePublished' ];
        
        foreach ( $requiredProperties as $property ) {
            if ( empty( $data[ $property ] ) ) {
                return false;
            }
        }

        // Validate author structure
        if ( ! isset( $data['author']['@type'] ) || $data['author']['@type'] !== 'Person' ) {
            return false;
        }

        // Validate date format
        if ( ! $this->isValidW3CDate( $data['datePublished'] ) ) {
            return false;
        }

        return true;
    }

    /**
     * Generates a proper BlogPosting ID with semantic meaning.
     *
     * @param object|null $graphData Custom graph data.
     * @param object      $schema    Schema context.
     * @return string Generated BlogPosting ID.
     */
    private function generateBlogPostingId( $graphData, $schema ): string {
        $baseUrl = $schema->context['url'] ?? home_url();
        
        // Use custom ID if provided and valid
        if ( ! empty( $graphData->id ) && is_string( $graphData->id ) ) {
            return trailingslashit( $baseUrl ) . sanitize_key( $graphData->id );
        }

        // Generate semantic ID based on post
        $post = WordpressHelpers::retrieve_post();
        if ( $post instanceof WP_Post ) {
            return trailingslashit( $baseUrl ) . '#blogposting-' . $post->ID;
        }

        // Fallback to generic ID
        return trailingslashit( $baseUrl ) . '#blogposting';
    }

    /**
     * Enhances BlogPosting data with blog-specific properties.
     *
     * @param array       &$data     Reference to schema data array.
     * @param object|null $graphData Custom graph data.
     */
    private function enhanceBlogPostingData( array &$data, $graphData ): void {
        $post = WordpressHelpers::retrieve_post();
        if ( ! $post instanceof WP_Post ) {
            return;
        }

        // Add blog-specific properties
        $this->addBlogContext( $data, $post );
        $this->addWordCount( $data, $post );
        $this->addBlogCategories( $data, $post );
        $this->addReadingTime( $data, $post );
        
        // Handle custom BlogPosting properties from graphData
        $this->processCustomBlogProperties( $data, $graphData );
    }

    /**
     * Adds blog context information.
     *
     * @param array   &$data Reference to schema data array.
     * @param WP_Post $post  WordPress post object.
     */
    private function addBlogContext( array &$data, WP_Post $post ): void {
        // Add blog reference if this is part of a blog
        $blogPageId = get_option( 'page_for_posts' );
        if ( $blogPageId && $blogPageId !== $post->ID ) {
            $blogUrl = get_permalink( $blogPageId );
            if ( $blogUrl ) {
                $data['isPartOf'] = [
                    '@type' => 'Blog',
                    '@id'   => trailingslashit( $blogUrl ) . '#blog',
                    'name'  => get_the_title( $blogPageId ) ?: get_bloginfo( 'name' ) . ' Blog'
                ];
            }
        }
    }

    /**
     * Adds word count if available.
     *
     * @param array   &$data Reference to schema data array.
     * @param WP_Post $post  WordPress post object.
     */
    private function addWordCount( array &$data, WP_Post $post ): void {
        $content = $post->post_content;
        if ( ! empty( $content ) ) {
            $wordCount = str_word_count( wp_strip_all_tags( $content ) );
            if ( $wordCount > 0 ) {
                $data['wordCount'] = $wordCount;
            }
        }
    }

    /**
     * Adds blog categories as articleSection.
     *
     * @param array   &$data Reference to schema data array.
     * @param WP_Post $post  WordPress post object.
     */
    private function addBlogCategories( array &$data, WP_Post $post ): void {
        $categories = get_the_category( $post->ID );
        if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
            $categoryNames = wp_list_pluck( $categories, 'name' );
            if ( ! empty( $categoryNames ) ) {
                // Override or enhance existing articleSection
                $data['articleSection'] = implode( ', ', array_unique( $categoryNames ) );
            }
        }
    }

    /**
     * Adds estimated reading time.
     *
     * @param array   &$data Reference to schema data array.
     * @param WP_Post $post  WordPress post object.
     */
    private function addReadingTime( array &$data, WP_Post $post ): void {
        $content = $post->post_content;
        if ( ! empty( $content ) ) {
            $wordCount = str_word_count( wp_strip_all_tags( $content ) );
            // Average reading speed: 200-250 words per minute
            $readingTimeMinutes = max( 1, ceil( $wordCount / 225 ) );
            
            // Add as timeRequired in ISO 8601 duration format
            $data['timeRequired'] = 'PT' . $readingTimeMinutes . 'M';
        }
    }

    /**
     * Processes custom BlogPosting properties from graph data.
     *
     * @param array       &$data     Reference to schema data array.
     * @param object|null $graphData Custom graph data.
     */
    private function processCustomBlogProperties( array &$data, $graphData ): void {
        if ( ! $graphData || ! isset( $graphData->properties ) ) {
            return;
        }

        $properties = $graphData->properties;

        // Handle custom blog-specific properties
        if ( isset( $properties->wordCount ) && is_numeric( $properties->wordCount ) ) {
            $data['wordCount'] = (int) $properties->wordCount;
        }

        if ( isset( $properties->timeRequired ) && ! empty( $properties->timeRequired ) ) {
            $data['timeRequired'] = sanitize_text_field( $properties->timeRequired );
        }

        if ( isset( $properties->backstory ) && ! empty( $properties->backstory ) ) {
            $data['backstory'] = wp_strip_all_tags( $properties->backstory );
        }
    }

    /**
     * Validates W3C date format.
     *
     * @param string $date Date string to validate.
     * @return bool True if valid W3C date format.
     */
    private function isValidW3CDate( string $date ): bool {
        // Basic W3C date format validation
        return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $date );
    }
}