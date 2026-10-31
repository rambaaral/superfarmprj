<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Rss;

if ( !defined('ABSPATH') ) {
    exit;
}

use RankingCoach\Inc\Core\Settings\SettingsManager;

/**
 * Feed cleanup handler class.
 */
class FeedCleanupHandler {

    /**
     * Process specific feed types.
     *
     * @param string $feedType
     * @param string $homeUrl
     */
    public function processSpecificFeeds( $feedType, $homeUrl ) {
        $this->handleAtomFeed( $feedType, $homeUrl );
        $this->handleRdfFeed( $feedType, $homeUrl );
        $this->handleGlobalFeed( $homeUrl );
        $this->handleGlobalCommentsFeed( $homeUrl );
        $this->handleStaticBlogPageFeed( $homeUrl );
        $this->handlePostCommentFeeds( $homeUrl );
        $this->handleAttachmentFeeds( $feedType, $homeUrl );
        $this->handleAuthorFeeds();
        $this->handleSearchFeeds( $homeUrl );
        $this->handlePostTypeArchives();
        $this->handleTaxonomyFeeds( $homeUrl );
        $this->handlePaginatedFeeds( $homeUrl );
    }

    /**
     * Handle Atom feed.
     */
    private function handleAtomFeed( $feedType, $homeUrl ) {
        if ( ! SettingsManager::instance()->rss->feeds->atom && 'atom' === $feedType ) {
            $this->performFeedRedirect( $homeUrl );
        }
    }

    /**
     * Handle RDF feed.
     */
    private function handleRdfFeed( $feedType, $homeUrl ) {
        if ( ! SettingsManager::instance()->rss->feeds->rdf && 'rdf' === $feedType ) {
            $this->performFeedRedirect( $homeUrl );
        }
    }

    /**
     * Handle global feed.
     */
    private function handleGlobalFeed( $homeUrl ) {
        if ( ! SettingsManager::instance()->rss->feeds->global &&
            [ 'feed' => 'feed' ] === $GLOBALS['wp_query']->query ) {
            $this->performFeedRedirect( $homeUrl );
        }
    }

    /**
     * Handle global comments feed.
     */
    private function handleGlobalCommentsFeed( $homeUrl ) {
        if ( ! SettingsManager::instance()->rss->feeds->globalComments &&
            is_comment_feed() && ! ( is_singular() || is_attachment() ) ) {
            $this->performFeedRedirect( $homeUrl );
        }
    }

    /**
     * Handle static blog page feed.
     */
    private function handleStaticBlogPageFeed( $homeUrl ) {
        if ( ! SettingsManager::instance()->rss->feeds->staticBlogPage &&
            $this->getBlogPageId() === get_queried_object_id() ) {
            $this->performFeedRedirect( $homeUrl );
        }
    }

    /**
     * Handle post comment feeds.
     */
    private function handlePostCommentFeeds( $homeUrl ) {
        if ( ! SettingsManager::instance()->rss->feeds->postComments &&
            is_comment_feed() && is_singular() ) {
            $this->performFeedRedirect( $homeUrl );
        }
    }

    /**
     * Handle attachment feeds.
     */
    private function handleAttachmentFeeds( $feedType, $homeUrl ) {
        if ( ! SettingsManager::instance()->rss->feeds->attachments &&
            'feed' === $feedType && get_query_var( 'attachment', false ) ) {
            $this->performFeedRedirect( $homeUrl );
        }
    }

    /**
     * Handle author feeds.
     */
    private function handleAuthorFeeds() {
        if ( ! SettingsManager::instance()->rss->feeds->authors && is_author() ) {
            $this->performFeedRedirect( get_author_posts_url( (int) get_query_var( 'author' ) ) );
        }
    }

    /**
     * Handle search feeds.
     */
    private function handleSearchFeeds( $homeUrl ) {
        if ( ! SettingsManager::instance()->rss->feeds->search && is_search() ) {
            $this->performFeedRedirect( esc_url( trailingslashit( $homeUrl ) . '?s=' . get_search_query() ) );
        }
    }

    /**
     * Handle post type archives.
     */
    private function handlePostTypeArchives() {
        $enabledArchives = SettingsManager::instance()->rss->feeds->archivesIncluded;
        $postType = $this->getCurrentPostType();

        if ( ! SettingsManager::instance()->rss->feeds->archivesAll &&
            ! in_array( $postType, $enabledArchives, true ) && is_post_type_archive() ) {
            $this->performFeedRedirect( get_post_type_archive_link( $postType ) );
        }
    }

    /**
     * Handle taxonomy feeds.
     */
    private function handleTaxonomyFeeds( $homeUrl ) {
        $enabledTaxonomies = SettingsManager::instance()->rss->feeds->taxonomiesIncluded;
        $term = get_queried_object();

        if ( is_a( $term, 'WP_Term' ) &&
            ! SettingsManager::instance()->rss->feeds->taxonomiesAll &&
            ! in_array( $term->taxonomy, $enabledTaxonomies, true ) &&
            ( is_category() || is_tag() || is_tax() ) ) {

            $termUrl = get_term_link( $term, $term->taxonomy );
            if ( is_wp_error( $termUrl ) ) {
                $termUrl = $homeUrl;
            }
            $this->performFeedRedirect( $termUrl );
        }
    }

    /**
     * Handle paginated feeds.
     */
    private function handlePaginatedFeeds( $homeUrl ) {
        if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
            return;
        }

        if ( ! SettingsManager::instance()->rss->feeds->paginated &&
            preg_match( '/(\d+\/|(?<=\/)page\/\d+\/)$/', (string) sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) ) {
            $this->performFeedRedirect( $homeUrl );
        }
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

    /**
     * Get blog page ID.
     *
     * @return int
     */
    private function getBlogPageId() {
        return (int) get_option( 'page_for_posts' );
    }

    /**
     * Handle BuddyPress feeds.
     */
    private function handleBuddyPressFeeds( $enabledArchives ) {
        // BuddyPress specific logic would go here
        // Simplified for this example
    }

    /**
     * Perform feed redirect.
     *
     * @param string $url
     */
    private function performFeedRedirect( $url ) {
        if ( empty( $url ) ) {
            return;
        }

        header_remove( 'Content-Type' );
        header_remove( 'Last-Modified' );
        header_remove( 'Expires' );

        $cacheControl = 'public, max-age=604800, s-maxage=604800, stale-while-revalidate=120, stale-if-error=14400';
        if ( is_user_logged_in() ) {
            $cacheControl = 'private, max-age=0';
        }

        header( 'Cache-Control: ' . $cacheControl, true );
        wp_safe_redirect( $url, 301, 'RSS_MANAGER' );
    }
}