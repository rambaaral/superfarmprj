<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Rss;

if ( !defined('ABSPATH') ) {
    exit;
}

use RankingCoach\Inc\Core\Settings\SettingsManager;

/**
 * Feed content processor helper class.
 */
class FeedContentProcessor {

    /**
     * Add RSS wrapper content.
     *
     * @param string $content
     * @param string $type
     * @return string
     */
    public function addRssWrapper( $content, $type ) {
        global $wp_query;
        $isHomePage = is_home();

        if ( $isHomePage ) {
            $wp_query->is_home = false;
        }

        $beforeContent = $this->parseTemplateTags(
            SettingsManager::instance()->rss->content->before,
            get_the_ID()
        );
        $afterContent = $this->parseTemplateTags(
            SettingsManager::instance()->rss->content->after,
            get_the_ID()
        );

        if ( $beforeContent || $afterContent ) {
            if ( 'excerpt' === $type ) {
                $content = wpautop( $content );
            }
            $content = $this->decodeHtmlEntities( $beforeContent ) . $content . $this->decodeHtmlEntities( $afterContent );
        }

        $wp_query->is_home = $isHomePage;

        return $content;
    }

    /**
     * Parse template tags.
     *
     * @param string $template
     * @param int $postId
     * @return string
     */
    private function parseTemplateTags( $template, $postId ) {
        if ( empty( $template ) ) {
            return '';
        }

        // Simple tag replacement - can be extended
        $template = str_replace( '{post_title}', get_the_title( $postId ), $template );
        $template = str_replace( '{post_url}', get_permalink( $postId ), $template );
        $template = str_replace( '{site_title}', get_bloginfo( 'name' ), $template );
        $template = str_replace( '{site_url}', get_home_url(), $template );

        return $template;
    }

    /**
     * Decode HTML entities.
     *
     * @param string $content
     * @return string
     */
    private function decodeHtmlEntities( $content ) {
        return html_entity_decode( $content, ENT_QUOTES, 'UTF-8' );
    }
}