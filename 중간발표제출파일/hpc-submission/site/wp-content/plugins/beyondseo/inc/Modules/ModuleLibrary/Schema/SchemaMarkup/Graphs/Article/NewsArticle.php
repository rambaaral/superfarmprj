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
 * NewsArticle schema markup generator.
 * 
 * Extends Article to provide NewsArticle-specific structured data
 * with proper dateline information and news-specific properties
 * according to Schema.org specifications.
 */
class NewsArticle extends Article {

    /**
     * Generates NewsArticle schema markup data.
     *
     * @param object|null $graphData Optional custom graph data configuration.
     * @return array Schema.org NewsArticle structured data array.
     * @throws ReflectionException When reflection operations fail.
     * @throws Exception When critical dependencies are unavailable.
     */
    public function get( $graphData = null ): array {
        /** @var ModuleManager $moduleManager */
        $moduleManager = ModuleManager::instance();
        /** @var SchemaManager $schema */
        $schema = $moduleManager->get_module('schemaMarkup')->schema;

        // Handle overwrite data if present
        if ( ! empty( self::$overwriteGraphData[ __CLASS__ ] ) ) {
            $graphData = json_decode( wp_json_encode( wp_parse_args( self::$overwriteGraphData[ __CLASS__ ], $graphData ) ) );
        }

        $data = parent::get( $graphData );
        if ( ! $data ) {
            return [];
        }

        // Override type and ID for NewsArticle
        $data['@type'] = 'NewsArticle';
        $data['@id'] = ! empty( $graphData->id ) 
            ? $schema->context['url'] . $graphData->id 
            : $schema->context['url'] . '#newsarticle';

        // Add NewsArticle-specific properties
        $this->addNewsArticleProperties( $graphData, $data );

        return $data;
    }

    /**
     * Adds NewsArticle-specific properties.
     *
     * @param object|null $graphData Custom graph data.
     * @param array       &$data     Reference to data array.
     */
    private function addNewsArticleProperties( $graphData, array &$data ): void {
        // Add proper dateline (location and date information)
        $this->addDateline( $graphData, $data );
        
        // Add print publication properties if available
        $this->addPrintProperties( $graphData, $data );
        
        // Add news-specific backstory if available
        $this->addBackstory( $graphData, $data );
    }

    /**
     * Adds proper dateline information for NewsArticle.
     * 
     * According to Schema.org, dateline should include location and date
     * information in a format like "BEIRUT, Lebanon, June 2." or "Paris, France"
     *
     * @param object|null $graphData Custom graph data.
     * @param array       &$data     Reference to data array.
     */
    private function addDateline( $graphData, array &$data ): void {
        $dateline = '';
        
        // Check for custom dateline in graph data
        if ( $graphData && isset( $graphData->properties->dateline ) && ! empty( $graphData->properties->dateline ) ) {
            $dateline = sanitize_text_field( $graphData->properties->dateline );
        } else {
            // Generate dateline from available data
            $location = $this->getNewsLocation( $graphData );
            $date = $this->getFormattedNewsDate( $graphData );
            
            if ( $location || $date ) {
                $dateline = trim( $location . ( $location && $date ? ', ' : '' ) . $date );
            }
        }
        
        if ( ! empty( $dateline ) ) {
            $data['dateline'] = $dateline;
        }
    }

    /**
     * Gets news location from various sources.
     *
     * @param object|null $graphData Custom graph data.
     * @return string Location string or empty.
     */
    private function getNewsLocation( $graphData ): string {
        // Priority 1: Custom location from graph data
        if ( $graphData && isset( $graphData->properties->location ) && ! empty( $graphData->properties->location ) ) {
            return sanitize_text_field( $graphData->properties->location );
        }
        
        // Priority 2: Content location from post meta or custom fields
        $post = WordpressHelpers::retrieve_post();
        if ( $post instanceof WP_Post ) {
            $location = get_post_meta( $post->ID, '_news_location', true );
            if ( ! empty( $location ) ) {
                return sanitize_text_field( $location );
            }
            
            // Check for common location custom fields
            $locationFields = [ 'location', 'news_location', 'dateline_location' ];
            foreach ( $locationFields as $field ) {
                $value = get_post_meta( $post->ID, $field, true );
                if ( ! empty( $value ) ) {
                    return sanitize_text_field( $value );
                }
            }
        }
        
        return '';
    }

    /**
     * Gets formatted date for news dateline.
     *
     * @param object|null $graphData Custom graph data.
     * @return string Formatted date or empty.
     */
    private function getFormattedNewsDate( $graphData ): string {
        $dateString = '';
        
        // Get date from graph data or post
        if ( $graphData && isset( $graphData->properties->datePublished ) && ! empty( $graphData->properties->datePublished ) ) {
            $dateString = $graphData->properties->datePublished;
        } else {
            $post = WordpressHelpers::retrieve_post();
            if ( $post instanceof WP_Post ) {
                $dateString = $post->post_date;
            }
        }
        
        if ( ! empty( $dateString ) ) {
            // Format as "Month Day, Year" (e.g., "June 2, 2024")
            return mysql2date( 'F j, Y', $dateString, false );
        }
        
        return '';
    }

    /**
     * Adds print publication properties for NewsArticle.
     *
     * @param object|null $graphData Custom graph data.
     * @param array       &$data     Reference to data array.
     */
    private function addPrintProperties( $graphData, array &$data ): void {
        if ( ! $graphData || ! isset( $graphData->properties ) ) {
            return;
        }
        
        $properties = $graphData->properties;
        
        // Add print-specific properties if available
        if ( isset( $properties->printColumn ) && ! empty( $properties->printColumn ) ) {
            $data['printColumn'] = sanitize_text_field( $properties->printColumn );
        }
        
        if ( isset( $properties->printEdition ) && ! empty( $properties->printEdition ) ) {
            $data['printEdition'] = sanitize_text_field( $properties->printEdition );
        }
        
        if ( isset( $properties->printPage ) && ! empty( $properties->printPage ) ) {
            $data['printPage'] = sanitize_text_field( $properties->printPage );
        }
        
        if ( isset( $properties->printSection ) && ! empty( $properties->printSection ) ) {
            $data['printSection'] = sanitize_text_field( $properties->printSection );
        }
    }

    /**
     * Adds backstory property for NewsArticle context.
     *
     * @param object|null $graphData Custom graph data.
     * @param array       &$data     Reference to data array.
     */
    private function addBackstory( $graphData, array &$data ): void {
        $backstory = '';
        
        // Check for custom backstory in graph data
        if ( $graphData && isset( $graphData->properties->backstory ) && ! empty( $graphData->properties->backstory ) ) {
            $backstory = wp_strip_all_tags( $graphData->properties->backstory );
        } else {
            // Check for backstory in post meta
            $post = WordpressHelpers::retrieve_post();
            if ( $post instanceof WP_Post ) {
                $backstory = get_post_meta( $post->ID, '_news_backstory', true );
                if ( empty( $backstory ) ) {
                    $backstory = get_post_meta( $post->ID, 'backstory', true );
                }
            }
        }
        
        if ( ! empty( $backstory ) ) {
            $data['backstory'] = sanitize_textarea_field( $backstory );
        }
    }
}