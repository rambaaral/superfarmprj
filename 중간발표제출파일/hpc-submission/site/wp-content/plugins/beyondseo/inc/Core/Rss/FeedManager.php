<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Rss;

if ( !defined('ABSPATH') ) {
    exit;
}

use RankingCoach\Inc\Core\Settings\SettingsManager;

/**
 * Manages RSS feed content and cleanup functionality.
 */
class FeedManager {

    /**
     * Singleton instance.
     *
     * @var FeedManager|null
     */
    private static $instance = null;

    /**
     * Feed content processor.
     *
     * @var FeedContentProcessor
     */
    private $contentProcessor;

    /**
     * Feed cleanup handler.
     *
     * @var FeedCleanupHandler
     */
    private $cleanupHandler;

    /**
     * Class constructor.
     */
    public function __construct() {
        if ( is_admin() ) {
            return;
        }

        $this->contentProcessor = new FeedContentProcessor();
        $this->cleanupHandler = new FeedCleanupHandler();
    }

    /**
     * Get singleton instance.
     *
     * @return FeedManager
     */
    public static function getInstance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize the feed manager.
     */
    public function init()
    {
        $this->initializeHooks();
    }

    /**
     * Initialize WordPress hooks.
     */
    private function initializeHooks() {
        add_filter( 'the_content_feed', [ $this, 'processRssContent' ] );
        add_filter( 'the_excerpt_rss', [ $this, 'processRssExcerpt' ] );

        // If feed cleanup is disabled, return early.
        if ( ! $this->isFeedCleanupEnabled() ) {
            return;
        }

        $this->setupFeedCleanup();
    }

    /**
     * Check if feed cleanup is enabled.
     *
     * @return bool
     */
    private function isFeedCleanupEnabled() {
        return SettingsManager::instance()->rss->feeds->cleanupEnable;
    }

    /**
     * Setup feed cleanup functionality.
     */
    private function setupFeedCleanup() {
        // Control which feed links are visible.
        remove_action( 'wp_head', 'feed_links_extra', 3 );
        add_action( 'wp_head', [ $this, 'generateRssFeedLinks' ], 3 );

        if ( ! SettingsManager::instance()->rss->feeds->global ) {
            add_filter( 'feed_links_show_posts_feed', '__return_false' );
        }

        if ( ! SettingsManager::instance()->rss->feeds->globalComments ) {
            add_filter( 'feed_links_show_comments_feed', '__return_false' );
        }

        // Disable feeds that we no longer want on this site.
        add_action( 'wp', [ $this, 'handleFeedDisabling' ], -1000 );
    }

    /**
     * Process RSS excerpt content.
     *
     * @param  string $content The post excerpt.
     * @return string          The processed post excerpt.
     */
    public function processRssExcerpt( $content ) {
        return $this->processRssContent( $content, 'excerpt' );
    }

    /**
     * Process RSS content with before/after additions.
     *
     * @param  string $content The post content.
     * @param  string $type    Type of feed.
     * @return string          The processed post content.
     */
    public function processRssContent( $content, $type = 'complete' ) {
        $content = trim( $content );
        if ( empty( $content ) ) {
            return '';
        }

        if ( is_feed() ) {
            $content = $this->contentProcessor->addRssWrapper( $content, $type );
        }

        return $content;
    }

    /**
     * Handle feed disabling based on settings.
     *
     * @return void
     */
    public function handleFeedDisabling() {

        if ( ! is_feed() ) {
            return;
        }

        $feedType = get_query_var( 'feed' );
        $homeUrl = get_home_url();

        $this->cleanupHandler->processSpecificFeeds( $feedType, $homeUrl );
    }

    /**
     * Generate RSS feed links in head.
     *
     * @param  array $args The arguments to filter.
     * @return void
     */
    public function generateRssFeedLinks( $args = [] ) {
        $linkGenerator = new FeedLinkGenerator();
        $linkGenerator->generateFeedLinks( $args );
    }
}