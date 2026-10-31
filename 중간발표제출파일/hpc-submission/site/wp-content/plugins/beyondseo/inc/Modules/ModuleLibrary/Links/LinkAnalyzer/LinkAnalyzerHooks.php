<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Modules\ModuleLibrary\Links\LinkAnalyzer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use DOMDocument;
use Exception;
use RankingCoach\Inc\Modules\ModuleBase\BaseSubmoduleHooks;
use RankingCoach\Inc\Modules\ModuleLibrary\Links\BrokenLinkChecker;
use RankingCoach\Inc\Modules\ModuleManager;

/**
 * Class LinkAnalyzerHooks
 * 
 * Handles hooks for the LinkAnalyzer module to extract and store links from posts.
 */
class LinkAnalyzerHooks extends BaseSubmoduleHooks {

    /** @var LinkAnalyzer The LinkAnalyzer module instance. */
    public LinkAnalyzer $module;

    public const SCAN_ALL_POSTS_HOOK = 'beyondseo_link_analyzer_scan_all_posts_hook';

    /**
     * LinkAnalyzerHooks constructor.
     * @param LinkAnalyzer $module
     * @param array|null $params
     */
    public function __construct(LinkAnalyzer $module, ?array $params = null) {
        $this->module = $module;
        parent::__construct($module, $params);
    }

    /**
     * Initializes the hooks for the submodule.
     */
    public function initializeHooks(): void {
        // Hook into post saving, updating, and deletion
        add_action('save_post', [$this, 'analyzeLinks']);
        add_action('post_updated', [$this, 'analyzeLinks']);
        add_action('before_delete_post', [$this, 'deleteLinks']);
        
        // Hook into trash and untrash actions
        add_action('wp_trash_post', [$this, 'deleteLinks']);
        add_action('untrash_post', [$this, 'analyzeLinks']);
        add_action(self::SCAN_ALL_POSTS_HOOK, [$this, 'scanAllPosts']);
    }

    /**
     * Analyze and extract links from a post.
     * 
     * @param int $post_id The post ID to analyze
     */
    public function analyzeLinks(int $post_id): void
    {
        if (!$this->module->isModuleInstalled()) {
            return;
        }

        // Ignore revisions and autosaves
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        // Get post content
        $post = get_post($post_id);
        if (!$post || !isset($post->post_content) || empty($post->post_content)) {
            return;
        }

        // Delete existing links for this post before adding new ones
        $this->deleteLinks($post_id);

        // Extract links from content
        $links = $this->extractLinks($post_id, $post->post_content);
        
        if (!empty($links)) {
            $this->storeLinks($links);
        }
    }

    /**
     * Extract links from post content.
     * 
     * @param int $post_id The post ID
     * @param string $content The post content
     * @return array Array of extracted links
     */
    protected function extractLinks(int $post_id, string $content): array
    {
        // Decode HTML entities in content
        $content = html_entity_decode($content);
        
        // Strip data URIs to prevent issues
        $content = preg_replace('/data:[^;]+;base64,[^"]+/', '', $content);

        $dom = new DOMDocument();
        // Suppress warnings for malformed HTML
        libxml_use_internal_errors(true);

        // Ensure proper UTF-8 encoding for DOMDocument
        // Add UTF-8 meta tag if not present to help DOMDocument handle encoding correctly
        if (!str_contains($content, '<meta') || !str_contains($content, 'charset')) {
            $content = '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">' . $content;
        }

        $dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        $links = [];
        $anchor_elements = $dom->getElementsByTagName('a');
        
        foreach ($anchor_elements as $anchor) {
            $href = $anchor->getAttribute('href');
            
            // Skip empty, javascript, mailto and tel links
            if (empty($href) || 
                strpos($href, 'javascript:') === 0 || 
                strpos($href, 'mailto:') === 0 || 
                strpos($href, 'tel:') === 0) {
                continue;
            }
            
            // Parse URL
            $parsed_url = $this->parseUrl($href);
            if (empty($parsed_url['host'])) {
                continue;
            }
            
            // Check if it's an internal or external link
            $is_internal = $parsed_url['host'] === $this->getHostname();
            
            // Skip internal links if not analyzing them
            if ($is_internal && !$this->module->settingsComponent->getSetting('analyze_internal_links')) {
                continue;
            }
            
            // Skip external links if not analyzing them
            if (!$is_internal && !$this->module->settingsComponent->getSetting('analyze_external_links')) {
                continue;
            }
            
            // Get hostname without www
            $hostname = preg_replace('/^www\./i', '', $parsed_url['host']);
            
            // Format URL without query parameters and fragments
            $url = $this->getUrlWithoutParamsAndFragment($parsed_url);
            
            // Sanitize URL
            $url = esc_url_raw($url);
            
            // Create URL hash
            $url_hash = sha1($url);
            $hostname_hash = sha1($hostname);
            
            // Extract the link text (anchor text)
            $link_text = '';
            if ($anchor->textContent) {
                $link_text = trim($anchor->textContent);
            }
            
            // Check and get link_status_id from BrokenLinkChecker
            $link_status_id = $this->checkAndUpdateLinkStatus($url, $url_hash);
            
            // Create link data
            $link_data = [
                'post_id' => $post_id,
                'url' => $url,
                'url_hash' => $url_hash,
                'hostname' => $hostname,
                'hostname_hash' => $hostname_hash,
                'external' => !$is_internal ? 1 : 0,
                'link_status_id' => $link_status_id,
                'link_text' => $link_text
            ];
            
            $links[] = $link_data;
        }
        
        return $links;
    }

    /**
     * Store extracted links in the database.
     * 
     * @param array $links Array of link data to store
     */
    protected function storeLinks(array $links): void
    {
        $this->module->storeLinks($links);
    }

    /**
     * Delete links for a specific post.
     * 
     * @param int $post_id The post ID to delete links for
     */
    public function deleteLinks(int $post_id): void
    {
        $this->module->deleteLinks($post_id);
    }

    /**
     * Parse a URL and handle relative URLs.
     * 
     * @param string $url The URL to parse
     * @return array Parsed URL components
     */
    protected function parseUrl(string $url): array
    {
        $parsed_url = wp_parse_url($url);
        if (empty($parsed_url)) {
            return [];
        }

        // If the URL is relative, add the hostname of the site
        if (empty($parsed_url['host'])) {
            $parsed_url['host'] = $this->getHostname();
            $parsed_url['scheme'] = wp_parse_url(get_site_url(), PHP_URL_SCHEME);
        }

        return $parsed_url;
    }

    /**
     * Get URL without query parameters and fragments.
     * 
     * @param array $parsed_url The parsed URL components
     * @return string The URL without query parameters and fragments
     */
    protected function getUrlWithoutParamsAndFragment(array $parsed_url): string
    {
        $url = '';
        if (!empty($parsed_url['scheme'])) {
            $url .= $parsed_url['scheme'] . '://';
        }

        $url .= $parsed_url['host'];

        if (!empty($parsed_url['path'])) {
            $url .= $parsed_url['path'];
        }

        return $url;
    }

    /**
     * Get the site's hostname.
     * 
     * @return string The site's hostname
     */
    protected function getHostname(): string
    {
        static $site_url = null;
        if (null === $site_url) {
            $site_url = wp_parse_url(get_site_url(), PHP_URL_HOST);
        }

        return $site_url;
    }

    /**
     * Check if a URL exists in the BrokenLinkChecker table and return its ID.
     * If it doesn't exist, insert it and return the new ID.
     *
     * @param string $url The URL to check
     * @param string $url_hash The hash of the URL
     * @return int|null The ID of the link status or null if BrokenLinkChecker is not available
     */
    protected function checkAndUpdateLinkStatus(string $url, string $url_hash): ?int
    {
        // Get the BrokenLinkChecker module
        $brokenLinkChecker = ModuleManager::instance()->brokenLinkChecker();
        if (!$brokenLinkChecker instanceof BrokenLinkChecker) {
            return null;
        }

        // Use the BrokenLinkChecker's getOrCreateLinkStatus method
        try {
            return $brokenLinkChecker->getOrCreateLinkStatus($url, $url_hash);
        } catch (Exception $e) {
            $this->log('Error checking/updating link status: ' . $e->getMessage(), 'ERROR');
            return null;
        }
    }

    /**
     * Scan all posts for links when the module is activated.
     * This will analyze all public posts of scannable post types.
     * @throws Exception
     */
    public function scanAllPosts(): void
    {

        if (!$this->module->isModuleInstalled()) {
            return;
        }

        $this->module->removeAllLinks();
        
        // Get posts to scan in batches
        $posts_per_batch = apply_filters('beyondseo_analyzer_posts_per_batch', 50);
        $offset = 0;
        $total_processed = 0;
        
        while (true) {
            $posts = $this->module->getPostsToScan($posts_per_batch, $offset);
            
            if (empty($posts)) {
                break; // No more posts to process
            }
            
            foreach ($posts as $post) {
                $this->analyzeLinks((int) $post->ID);
                $total_processed++;
            }

            $this->log('Processed batch of ' . count($posts) . " posts. Total processed: $total_processed", 'DEBUG');
            $offset += $posts_per_batch;
        }
        
        $this->log("Completed full site link scan. Total posts processed: $total_processed", 'DEBUG');
    }
}
