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
 * SocialMediaPosting schema markup generator.
 * 
 * Extends Article to provide SocialMediaPosting-specific structured data
 * with shared content properties according to Schema.org specifications.
 * This serves as the proper parent class for BlogPosting.
 */
class SocialMediaPosting extends Article {

    /**
     * Generates SocialMediaPosting schema markup data.
     *
     * @param object|null $graphData Optional custom graph data configuration.
     * @return array Schema.org SocialMediaPosting structured data array.
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

        // Override type and ID for SocialMediaPosting
        $data['@type'] = 'SocialMediaPosting';
        $data['@id'] = ! empty( $graphData->id ) 
            ? $schema->context['url'] . $graphData->id 
            : $schema->context['url'] . '#socialmediaposting';

        // Add SocialMediaPosting-specific properties
        $this->addSocialMediaProperties( $graphData, $data );

        return $data;
    }

    /**
     * Adds SocialMediaPosting-specific properties.
     *
     * @param object|null $graphData Custom graph data.
     * @param array       &$data     Reference to data array.
     */
    private function addSocialMediaProperties( $graphData, array &$data ): void {
        // Add shared content if available
        $this->addSharedContent( $graphData, $data );
    }

    /**
     * Adds shared content property for SocialMediaPosting.
     *
     * @param object|null $graphData Custom graph data.
     * @param array       &$data     Reference to data array.
     */
    private function addSharedContent( $graphData, array &$data ): void {
        $sharedContent = null;
        
        // Check for custom shared content in graph data
        if ( $graphData && isset( $graphData->properties->sharedContent ) && ! empty( $graphData->properties->sharedContent ) ) {
            $sharedContent = $graphData->properties->sharedContent;
        } else {
            // Check for shared content in post meta or custom fields
            $post = WordpressHelpers::retrieve_post();
            if ( $post instanceof WP_Post ) {
                $sharedContentId = get_post_meta( $post->ID, '_shared_content_id', true );
                if ( ! empty( $sharedContentId ) ) {
                    $sharedContent = $this->buildSharedContentReference( $sharedContentId );
                }
            }
        }
        
        if ( ! empty( $sharedContent ) ) {
            $data['sharedContent'] = $sharedContent;
        }
    }

    /**
     * Builds shared content reference structure.
     *
     * @param mixed $contentReference Content reference (ID, URL, or object).
     * @return array|string Shared content structure.
     */
    private function buildSharedContentReference( $contentReference ) {
        // If it's already an object/array, return as-is
        if ( is_array( $contentReference ) || is_object( $contentReference ) ) {
            return $contentReference;
        }
        
        // If it's a URL, return as-is
        if ( filter_var( $contentReference, FILTER_VALIDATE_URL ) ) {
            return $contentReference;
        }
        
        // If it's a post ID, build CreativeWork reference
        if ( is_numeric( $contentReference ) ) {
            $sharedPost = get_post( (int) $contentReference );
            if ( $sharedPost instanceof WP_Post ) {
                return [
                    '@type' => 'CreativeWork',
                    '@id'   => get_permalink( $sharedPost->ID ) . '#creativework',
                    'name'  => get_the_title( $sharedPost->ID ),
                    'url'   => get_permalink( $sharedPost->ID )
                ];
            }
        }
        
        return $contentReference;
    }
}