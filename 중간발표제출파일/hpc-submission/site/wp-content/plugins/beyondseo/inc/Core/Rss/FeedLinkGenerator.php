<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Rss;

if ( !defined('ABSPATH') ) {
    exit;
}

use RankingCoach\Inc\Core\Settings\SettingsManager;

/**
 * Feed link generator class.
 */
class FeedLinkGenerator {

    /**
     * Generate feed links.
     *
     * @param array $args
     */
    public function generateFeedLinks( $args = [] ) {
        $defaultArgs = $this->getDefaultArguments();
        $args = wp_parse_args( $args, $defaultArgs );

        $linkAttributes = $this->determineLinkAttributes( $args );

        if ( ! empty( $linkAttributes['title'] ) && ! empty( $linkAttributes['href'] ) ) {
            echo '<link rel="alternate" type="application/rss+xml" title="' .
                esc_attr( $linkAttributes['title'] ) . '" href="' .
                esc_url( $linkAttributes['href'] ) . '" />' . "\n";
        }
    }

    /**
     * Get default arguments.
     *
     * @return array
     */
    private function getDefaultArguments() {
        return [
            'separator'     => _x( '-', 'feed link', 'beyondseo'),

            /* translators: %1$s is the blog name, %2$s is the separator, %3$s is the post title */
            'singletitle'   => __( '%1$s %2$s %3$s Comments Feed', 'beyondseo'),

            /* translators: %1$s is the blog name, %2$s is the separator, %3$s is the category name */
            'cattitle'      => __( '%1$s %2$s %3$s Category Feed', 'beyondseo'),

            /* translators: %1$s is the blog name, %2$s is the separator, %3$s is the tag name */
            'tagtitle'      => __( '%1$s %2$s %3$s Tag Feed', 'beyondseo'),

            /* translators: %1$s is the blog name, %2$s is the separator, %3$s is the taxonomy name, %4$s is the term name */
            'taxtitle'      => __( '%1$s %2$s %3$s %4$s Feed', 'beyondseo'),

            /* translators: %1$s is the blog name, %2$s is the separator, %3$s is the author's display name */
            'authortitle'   => __( '%1$s %2$s Posts by %3$s Feed', 'beyondseo'),

            /* translators: %1$s is the blog name, %2$s is the separator, %3$s is the search query */
            'searchtitle'   => __( '%1$s %2$s Search Results for &#8220;%3$s&#8221; Feed', 'beyondseo'),

            /* translators: %1$s is the blog name, %2$s is the separator, %3$s is the post type name */
            'posttypetitle' => __( '%1$s %2$s %3$s Feed', 'beyondseo'),
        ];
    }

    /**
     * Determine link attributes based on current page.
     *
     * @param array $args
     * @return array
     */
    private function determineLinkAttributes( $args ) {
        $attributes = [ 'title' => null, 'href' => null ];

        if ( SettingsManager::instance()->rss->feeds->postComments && is_singular() ) {
            $attributes = $this->getPostCommentsLinkAttributes( $args );
        }

        if ( $this->shouldShowPostTypeArchiveLink() && is_post_type_archive() ) {
            $attributes = $this->getPostTypeArchiveLinkAttributes( $args );
        }

        if ( $this->shouldShowTaxonomyLink() ) {
            $attributes = $this->getTaxonomyLinkAttributes( $args );
        }

        if ( SettingsManager::instance()->rss->feeds->authors && is_author() ) {
            $attributes = $this->getAuthorLinkAttributes( $args );
        }

        if ( SettingsManager::instance()->rss->feeds->search && is_search() ) {
            $attributes = $this->getSearchLinkAttributes( $args );
        }

        return $attributes;
    }

    /**
     * Should show post type archive link.
     *
     * @return bool
     */
    private function shouldShowPostTypeArchiveLink() {
        $enabledArchives = SettingsManager::instance()->rss->feeds->archivesIncluded;
        $postType = $this->getCurrentPostType();

        return SettingsManager::instance()->rss->feeds->archivesAll ||
            in_array( $postType, $enabledArchives, true );
    }

    /**
     * Should show taxonomy link.
     *
     * @return bool
     */
    private function shouldShowTaxonomyLink() {
        $enabledTaxonomies = SettingsManager::instance()->rss->feeds->taxonomiesIncluded;
        $term = get_queried_object();

        return $term && isset( $term->taxonomy ) &&
            ( SettingsManager::instance()->rss->feeds->taxonomiesAll ||
                in_array( $term->taxonomy, $enabledTaxonomies, true ) ) &&
            ( is_category() || is_tag() || is_tax() );
    }

    /**
     * Get post comments link attributes.
     *
     * @param array $args
     * @return array
     */
    private function getPostCommentsLinkAttributes( $args ) {
        $post = get_post( 0 );
        $title = null;
        $href = null;

        if ( comments_open() || pings_open() || 0 < $post->comment_count ) {
            $title = sprintf( $args['singletitle'], get_bloginfo( 'name' ), $args['separator'],
                the_title_attribute( [ 'echo' => false ] ) );
            $href = get_post_comments_feed_link( $post->ID );
        }

        return [ 'title' => $title, 'href' => $href ];
    }

    /**
     * Get post type archive link attributes.
     *
     * @param array $args
     * @return array
     */
    private function getPostTypeArchiveLinkAttributes(array $args ): array
    {
        $postTypeObject = get_post_type_object( $this->getCurrentPostType() );
        $title = sprintf( $args['posttypetitle'], get_bloginfo( 'name' ), $args['separator'], $postTypeObject?->labels->name );
        $href = get_post_type_archive_feed_link( $postTypeObject?->name );

        return [ 'title' => $title, 'href' => $href ];
    }

    /**
     * Get taxonomy link attributes.
     *
     * @param array $args
     * @return array
     */
    private function getTaxonomyLinkAttributes(array $args ): array
    {
        $term = get_queried_object();
        $title = null;
        $href = null;

        if ( is_category() ) {
            $title = sprintf( $args['cattitle'], get_bloginfo( 'name' ), $args['separator'], $term?->name );
            $href = get_category_feed_link( $term?->term_id );
        } elseif ( is_tag() ) {
            $title = sprintf( $args['tagtitle'], get_bloginfo( 'name' ), $args['separator'], $term?->name );
            $href = get_tag_feed_link( $term?->term_id );
        } elseif ( is_tax() ) {
            $taxonomy = get_taxonomy( $term?->taxonomy );
            $title = sprintf( $args['taxtitle'], get_bloginfo( 'name' ), $args['separator'], $term?->name, $taxonomy->labels->singular_name );
            $href = get_term_feed_link( $term?->term_id, $term?->taxonomy );
        }

        return [ 'title' => $title, 'href' => $href ];
    }

    /**
     * Get author link attributes.
     *
     * @param array $args
     * @return array
     */
    private function getAuthorLinkAttributes( $args ) {
        $authorId = (int) get_query_var( 'author' );
        $title = sprintf( $args['authortitle'], get_bloginfo( 'name' ), $args['separator'],
            get_the_author_meta( 'display_name', $authorId ) );
        $href = get_author_feed_link( $authorId );

        return [ 'title' => $title, 'href' => $href ];
    }

    /**
     * Get search link attributes.
     *
     * @param array $args
     * @return array
     */
    private function getSearchLinkAttributes( $args ) {
        $title = sprintf( $args['searchtitle'], get_bloginfo( 'name' ), $args['separator'], get_search_query( false ) );
        $href = get_search_feed_link();

        return [ 'title' => $title, 'href' => $href ];
    }

    /**
     * Get current post type.
     *
     * @return string
     */
    private function getCurrentPostType() {
        $postType = get_query_var( 'post_type' );
        if ( is_array( $postType ) ) {
            $postType = reset( $postType );
        }
        return $postType;
    }
}
