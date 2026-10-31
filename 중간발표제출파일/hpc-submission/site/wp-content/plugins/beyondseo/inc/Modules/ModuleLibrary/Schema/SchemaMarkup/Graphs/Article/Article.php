<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Graphs\Article;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Exception;
use RankingCoach\Inc\Core\Helpers\CoreHelper;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Core\Settings\SettingsManager;
use RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Graphs;
use RankingCoach\Inc\Modules\ModuleManager;
use ReflectionException;
use WP_Post;
use WP_User;

/**
 * Article schema markup generator.
 * 
 * Generates Schema.org Article structured data for WordPress posts,
 * with comprehensive fallback mechanisms for images and metadata.
 */
class Article extends Graphs\Graph {

    use Graphs\Traits\Image;

    /**
     * Cached module manager instance.
     *
     * @var ModuleManager|null
     */
    private $moduleManager;

    /**
     * Cached settings manager instance.
     *
     * @var SettingsManager|null
     */
    private $settingsManager;

    /**
     * Cached options array.
     *
     * @var array|null
     */
    private $options;
    /**
     * Generates Article schema markup data.
     *
     * @param object|null $graphData Optional custom graph data configuration.
     *
     * @return array Schema.org Article structured data array.
     * @throws ReflectionException When reflection operations fail.
     * @throws Exception When critical dependencies are unavailable.
     */
    public function get( $graphData = null ): array {
        $schema = $this->getModuleManager()->get_module('schemaMarkup')->schema;
        $options = $this->getOptions();
        
        $post = WordpressHelpers::retrieve_post();
        if ( ! $post instanceof WP_Post ) {
            return [];
        }

        $articleImage = $this->getArticleImage( $graphData, $post );
        
        $data = [
            '@type'            => 'Article',
            '@id'              => $this->buildArticleId( $graphData, $schema ),
            'name'             => $this->getArticleName( $graphData, $schema ),
            'headline'         => $this->getArticleHeadline( $graphData ),
            'description'      => $this->getArticleDescription( $graphData, $schema ),
            'articleBody'      => $this->getArticleBody( $graphData, $post ),
            'author'           => $this->buildAuthorData( $graphData, $post ),
            'publisher'        => $this->buildPublisherReference( $options ),
            'image'            => $articleImage,
            'datePublished'    => $this->getPublishedDate( $graphData, $post ),
            'dateModified'     => $this->getModifiedDate( $graphData, $post ),
            'inLanguage'       => WordpressHelpers::current_language_code_BCP47(),
            'commentCount'     => $this->getCommentCount( $post ),
            'mainEntityOfPage' => $this->buildWebpageReference( $graphData, $schema ),
            'isPartOf'         => $this->buildWebpageReference( $graphData, $schema ),
        ];
        
        // Add mainImage property for better SEO (references the same image)
        if ( ! empty( $articleImage ) ) {
            $data['mainImage'] = $articleImage;
        }

        // Handle author reference for PersonAuthor graph
        $this->handleAuthorReference( $graphData, $schema, $data, $post );
        
        // Add keywords if provided
        $this->addKeywords( $graphData, $data );
        
        // Handle date inclusion settings
        $this->handleDateInclusion( $graphData, $data );
        
        // Add article sections from taxonomies
        $this->addArticleSections( $post, $data );
        
        // Add pagination if applicable
        $this->addPagination( $data );
        
        return $data;
    }

    /**
     * Gets cached module manager instance.
     *
     * @return ModuleManager
     * @throws Exception When module manager is unavailable.
     */
    private function getModuleManager(): ModuleManager {
        if ( null === $this->moduleManager ) {
            $this->moduleManager = ModuleManager::instance();
        }
        return $this->moduleManager;
    }

    /**
     * Gets cached settings manager instance.
     *
     * @return SettingsManager
     * @throws Exception When settings manager is unavailable.
     */
    private function getSettingsManager(): SettingsManager {
        if ( null === $this->settingsManager ) {
            $this->settingsManager = SettingsManager::instance();
        }
        return $this->settingsManager;
    }

    /**
     * Gets cached options array.
     *
     * @return array
     * @throws Exception When options cannot be retrieved.
     */
    private function getOptions(): array {
        if ( null === $this->options ) {
            $this->options = $this->getSettingsManager()->get_options();
        }
        return $this->options;
    }

    /**
     * Builds the article ID.
     *
     * @param object|null $graphData Custom graph data.
     * @param object      $schema    Schema context.
     *
     * @return string
     */
    private function buildArticleId( $graphData, $schema ): string {
        return ! empty( $graphData->id ) 
            ? $schema->context['url'] . $graphData->id 
            : $schema->context['url'] . '#article';
    }

    /**
     * Gets the article name.
     *
     * @param object|null $graphData Custom graph data.
     * @param object      $schema    Schema context.
     *
     * @return string
     */
    private function getArticleName( $graphData, $schema ): string {
        return ( $graphData && isset( $graphData->properties->name ) && ! empty( $graphData->properties->name ) )
            ? $graphData->properties->name 
            : ( $schema->context['name'] ?? get_the_title() );
    }

    /**
     * Gets the article headline.
     *
     * @param object|null $graphData Custom graph data.
     *
     * @return string
     */
    private function getArticleHeadline( $graphData ): string {
        return ( $graphData && isset( $graphData->properties->headline ) && ! empty( $graphData->properties->headline ) )
            ? $graphData->properties->headline 
            : get_the_title();
    }

    /**
     * Gets the article description.
     *
     * @param object|null $graphData Custom graph data.
     * @param object      $schema    Schema manager with the computed page context.
     *
     * @return string
     */
    private function getArticleDescription( $graphData, $schema ): string {
        return ( $graphData && isset( $graphData->properties->description ) && ! empty( $graphData->properties->description ) )
            ? $graphData->properties->description 
            : ( $schema->context['description'] ?? '' );
    }

    /**
     * Gets the article body content.
     *
     * @param object|null $graphData Custom graph data.
     * @param WP_Post     $post      WordPress post object.
     *
     * @return string
     */
    private function getArticleBody( $graphData, WP_Post $post ): string {
        // Priority 1: Custom article body from graph data
        if ( $graphData && isset( $graphData->properties->articleBody ) && ! empty( $graphData->properties->articleBody ) ) {
            return wp_strip_all_tags( $graphData->properties->articleBody );
        }
        
        // Priority 2: Post content
        $content = $post->post_content;
        if ( ! empty( $content ) ) {
            return WordpressHelpers::extract_text_from_content( (string) $content );
        }
        
        return '';
    }

    /**
     * Builds author data structure.
     *
     * The author is resolved from the post's author, not via get_the_author_meta()
     * without an ID: schema is generated outside The Loop (wp_head, REST), where
     * the global $authordata is not set and the display name would come back empty.
     *
     * @param object|null $graphData Custom graph data.
     * @param WP_Post     $post      WordPress post object.
     *
     * @return array
     */
    private function buildAuthorData( $graphData, WP_Post $post ): array {
        $author = get_userdata( (int) $post->post_author );

        return [
            '@type' => 'Person',
            'name'  => ( $graphData && isset( $graphData->properties->author->name ) && ! empty( $graphData->properties->author->name ) )
                ? $graphData->properties->author->name
                : ( $author instanceof WP_User ? $author->display_name : '' ),
            'url'   => ( $graphData && isset( $graphData->properties->author->url ) && ! empty( $graphData->properties->author->url ) )
                ? $graphData->properties->author->url
                : ( $author instanceof WP_User ? get_author_posts_url( $author->ID ) : '' ),
        ];
    }

    /**
     * Builds publisher reference.
     *
     * @param array $options Plugin options.
     *
     * @return array
     */
    private function buildPublisherReference( array $options ): array {
        return [
            '@id' => trailingslashit( home_url() ) . '#' . $options['site_represents']
        ];
    }

    /**
     * Gets article image with fallback hierarchy.
     *
     * @param object|null $graphData Custom graph data.
     * @param WP_Post     $post      WordPress post object.
     *
     * @return array
     * @throws ReflectionException
     * @throws Exception
     */
    private function getArticleImage( $graphData, WP_Post $post ): array {
        return ( $graphData && isset( $graphData->properties->image ) && ! empty( $graphData->properties->image ) )
            ? $this->image( $graphData->properties->image ) 
            : $this->postImage( $post );
    }

    /**
     * Gets published date in W3C format.
     *
     * @param object|null $graphData Custom graph data.
     * @param WP_Post     $post      WordPress post object.
     *
     * @return string
     */
    private function getPublishedDate( $graphData, WP_Post $post ): string {
        return ( $graphData && isset( $graphData->properties->dates->datePublished ) && ! empty( $graphData->properties->dates->datePublished ) )
            ? mysql2date( DATE_W3C, $graphData->properties->dates->datePublished, false )
            : mysql2date( DATE_W3C, $post->post_date, false );
    }

    /**
     * Gets modified date in W3C format.
     *
     * @param object|null $graphData Custom graph data.
     * @param WP_Post     $post      WordPress post object.
     *
     * @return string
     */
    private function getModifiedDate( $graphData, WP_Post $post ): string {
        return ( $graphData && isset( $graphData->properties->dates->dateModified ) && ! empty( $graphData->properties->dates->dateModified ) )
            ? mysql2date( DATE_W3C, $graphData->properties->dates->dateModified, false )
            : mysql2date( DATE_W3C, $post->post_modified, false );
    }

    /**
     * Gets comment count safely.
     *
     * @param WP_Post $post WordPress post object.
     *
     * @return int
     */
    private function getCommentCount( WP_Post $post ): int {
        $commentCount = get_comment_count( $post->ID );
        return (int) ( $commentCount['approved'] ?? 0 );
    }

    /**
     * Builds webpage reference.
     *
     * @param object|null $graphData Custom graph data.
     * @param object      $schema    Schema context.
     *
     * @return array|string
     */
    private function buildWebpageReference( $graphData, $schema ) {
        return empty( $graphData ) 
            ? [ '@id' => $schema->context['url'] . '#webpage' ] 
            : '';
    }

    /**
     * Handles author reference for PersonAuthor graph.
     *
     * @param object|null $graphData Custom graph data.
     * @param object      $schema    Schema context.
     * @param array       &$data     Reference to data array.
     * @param WP_Post     $post      WordPress post object.
     */
    private function handleAuthorReference( $graphData, $schema, array &$data, WP_Post $post ): void {
        if ( ! $graphData || ! isset( $graphData->properties->author->name ) || empty( $graphData->properties->author->name ) ) {
            if ( isset( $schema->graphs ) && ! in_array( 'PersonAuthor', $schema->graphs, true ) ) {
                $schema->graphs[] = 'PersonAuthor';
            }

            $authorUrl = get_author_posts_url( $post->post_author );
            if ( $authorUrl ) {
                $data['author'] = array_merge($data['author'] ?? [], [
                    '@id' => esc_url( $authorUrl ) . '#author'
                ]);
            }
        }
    }

    /**
     * Adds keywords to data if provided.
     *
     * @param object|null $graphData Custom graph data.
     * @param array       &$data     Reference to data array.
     */
    private function addKeywords( $graphData, array &$data ): void {
        if ( $graphData && isset( $graphData->properties->keywords ) && ! empty( $graphData->properties->keywords ) ) {
            $keywords = json_decode( $graphData->properties->keywords, true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array( $keywords ) ) {
                $keywordValues = array_map( function ( $keywordObject ) {
                    return isset( $keywordObject['value'] ) ? sanitize_text_field( $keywordObject['value'] ) : '';
                }, $keywords );
                $filteredKeywords = array_filter( $keywordValues );
                if ( ! empty( $filteredKeywords ) ) {
                    $data['keywords'] = implode( ', ', $filteredKeywords );
                }
            }
        }
    }

    /**
     * Handles date inclusion settings.
     *
     * @param object|null $graphData Custom graph data.
     * @param array       &$data     Reference to data array.
     */
    private function handleDateInclusion( $graphData, array &$data ): void {
        if ( $graphData && isset( $graphData->properties->dates->include ) && ! $graphData->properties->dates->include ) {
            unset( $data['datePublished'], $data['dateModified'] );
        }
    }

    /**
     * Adds article sections from post taxonomies.
     *
     * @param WP_Post $post WordPress post object.
     * @param array   &$data Reference to data array.
     */
    private function addArticleSections( WP_Post $post, array &$data ): void {
        $postTaxonomies = get_post_taxonomies( $post );
        $postTerms = [];
        
        foreach ( $postTaxonomies as $taxonomy ) {
            $terms = get_the_terms( $post, $taxonomy );
            if ( is_array( $terms ) && ! is_wp_error( $terms ) ) {
                $postTerms = array_merge( $postTerms, wp_list_pluck( $terms, 'name' ) );
            }
        }

        if ( ! empty( $postTerms ) ) {
            $data['articleSection'] = implode( ', ', array_unique( $postTerms ) );
        }
    }

    /**
     * Adds pagination information if applicable.
     *
     * @param array &$data Reference to data array.
     */
    private function addPagination( array &$data ): void {
        $pageNumber = CoreHelper::determine_page_number();
        if ( $pageNumber > 1 ) {
            $data['pagination'] = $pageNumber;
        }
    }

    /**
     * Generates image data for the post with comprehensive fallback hierarchy.
     *
     * @param WP_Post $post WordPress post object.
     *
     * @return array Image schema data.
     * @throws ReflectionException
     * @throws Exception
     */
    private function postImage( WP_Post $post ): array {
        $options = $this->getOptions();

        // Priority 1: Featured image
        $featuredImage = $this->getFeaturedImage();
        if ( $featuredImage ) {
            return $featuredImage;
        }

        // Priority 2: First image from post content
        $contentImage = $this->extractFirstContentImage( $post->post_content );
        if ( $contentImage ) {
            return $contentImage;
        }

        // Priority 3: Organization logo or person avatar
        if ( 'organization' === $options['site_represents'] ) {
            $logo = $this->getOrganizationLogo();
            if ( $logo ) {
                return $logo;
            }
        } else {
            $avatar = $this->avatar( $post->post_author, 'articleImage' );
            if ( $avatar ) {
                return $avatar;
            }
        }

        // Priority 4: Theme custom logo
        $customLogo = $this->getCustomThemeLogo();
        if ( $customLogo ) {
            return $customLogo;
        }

        return [];
    }

    /**
     * Extracts first image from post content.
     *
     * @param string $content Post content.
     *
     * @return array|null Image data or null if none found.
     */
    private function extractFirstContentImage( string $content ): ?array {
        if ( empty( $content ) ) {
            return null;
        }

        preg_match_all( '#<img[^>]+src="([^">]+)"[^>]*alt="([^"]*)"#i', $content, $matches );
        
        if ( isset( $matches[1][0] ) ) {
            $imageUrl = $matches[1][0];
            $altText = $matches[2][0] ?? '';
            
            // Basic validation for image URL
            if ( filter_var( $imageUrl, FILTER_VALIDATE_URL ) ) {
                $imageData = [
                    '@type'      => 'ImageObject',
                    '@id'        => trailingslashit( home_url() ) . '#articleImage',
                    'url'        => esc_url( $imageUrl ),
                    'contentUrl' => esc_url( $imageUrl ),
                ];
                
                if ( ! empty( $altText ) ) {
                    $imageData['name'] = wp_strip_all_tags( $altText );
                    $imageData['caption'] = wp_strip_all_tags( $altText );
                }
                
                // Try to get local image dimensions only
                $imageDimensions = $this->getLocalImageDimensions( $imageUrl );
                if ( $imageDimensions ) {
                    $imageData['width']  = $imageDimensions['width'];
                    $imageData['height'] = $imageDimensions['height'];
                    
                    // Use same URL as thumbnail for content images
                    $imageData['thumbnailUrl'] = esc_url( $imageUrl );
                }
                
                return $imageData;
            }
        }

        return null;
    }

    /**
     * Gets organization logo with proper ID.
     *
     * @return array|null Logo data or null if unavailable.
     * @throws ReflectionException
     * @throws Exception
     */
    private function getOrganizationLogo(): ?array {
        $logo = ( new Graphs\KnowledgeGraph\KgOrganization() )->logo();
        if ( ! empty( $logo ) ) {
            $logo['@id'] = trailingslashit( home_url() ) . '#articleImage';
            return $logo;
        }
        return null;
    }

    /**
     * Gets custom theme logo.
     *
     * @return array|null Logo data or null if unavailable.
     * @throws ReflectionException
     * @throws Exception
     */
    private function getCustomThemeLogo(): ?array {
        if ( ! get_theme_support( 'custom-logo' ) ) {
            return null;
        }

        $imageId = get_theme_mod( 'custom_logo' );
        if ( $imageId ) {
            return $this->image( $imageId, 'articleImage' );
        }

        return null;
    }
}