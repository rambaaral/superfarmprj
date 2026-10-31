<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Links;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Exception;
use RankingCoach\Inc\Core\DB\DatabaseTablesManager;
use RankingCoach\Inc\Modules\ModuleBase\BaseModule;
use RankingCoach\Inc\Modules\ModuleManager;
use ReflectionException;
use WP_Error;
use WP_Post;
use WpOrg\Requests\Requests;

/**
 * Class BrokenLinkChecker
 */
class BrokenLinkChecker extends BaseModule {
    
    /**
     * Request method for link checking
     */
    private const REQUEST_METHOD = 'HEAD';
    
    /**
     * Request timeout in seconds
     */
    private const REQUEST_TIMEOUT = 5;
    
    /**
     * Maximum number of redirects to follow
     */
    private const REQUEST_REDIRECTION = 5;
    
    /**
     * Whether to verify SSL certificates
     */
    private const REQUEST_SSL_VERIFY = false;
    
    /**
     * Whether requests should be blocking
     */
    private const REQUEST_BLOCKING = false;
    
    /**
     * User agent string format
     */
    private const USER_AGENT_FORMAT = 'WordPress/%s; %s';

	/**
	 * BrokenLinkChecker constructor.
	 *
	 * @param ModuleManager $moduleManager
	 *
	 * @throws ReflectionException
	 */
    public function __construct(ModuleManager $moduleManager) {
        $initialization = [
            'active' => true,
            'title' => 'Broken Link Checker',
            'description' => 'Regularly scans the website for broken links (internal and external) and notifies the administrator. Helps maintain site health and improve SEO by identifying and fixing broken links.',
            'version' => '1.0.0',
            'name' => 'brokenLinkChecker',
            'priority' => 14,
            'dependencies' => [],
            'settings' => [['key' => 'notify_admin', 'type' => 'boolean', 'default' => True, 'description' => 'Send email notifications to the administrator when broken links are detected.'], ['key' => 'check_frequency', 'type' => 'string', 'default' => 'weekly', 'description' => 'How often to check for broken links: \'daily\', \'weekly\', or \'monthly\'.'], ['key' => 'broken_link_threshold', 'type' => 'integer', 'default' => 5, 'description' => 'The minimum number of broken links that must be detected before sending a notification. This helps prevent notification overload for minor issues.']],
            'explain' => 'The Broken Link Checker module scans the website every week (as per the default setting).  If it finds more than five broken links based on the configured threshold, it sends an email notification to the site administrator with a list of the broken links.  The administrator can then take action to fix these links, improving site health, user experience, and SEO.',
        ];
        parent::__construct($moduleManager, $initialization);
    }

    /**
     * Registers the hooks for the module.
     * @return void
     */
	public function initializeModule(): void {
		parent::initializeModule();
		
		if ($this->isActive()) {
		    // Add hook to check links when a post is saved
		    add_action('save_page', [$this, 'handlePostSave'], 20, 3);
		}
    }

    /**
     * Handle post save event to check links in the post.
     *
     * @param int $post_id The post ID
     * @param WP_Post $post The post object
     * @param bool $update Whether this is an update or a new post
     * @return void
     * @throws Exception
     */
    public function handlePostSave(int $post_id, WP_Post $post, bool $update): void
    {
        // Skip revisions and auto-saves
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        
        // Skip if post is not published
        if ($post->post_status !== 'publish') {
            return;
        }
        
        // Check all links in the post
        $this->checkPageAllLinks($post_id);
    }
    
    /**
     * Maximum number of links to process per cron execution to avoid
     * saturating PHP-FPM workers on sites with many links.
     */
    private const MAX_LINKS_PER_RUN = 30;

    /**
     * Maximum execution time budget (in seconds) for a single run of checkAllLinks().
     * Prevents long-running scans from blocking WP-Cron workers indefinitely.
     */
    private const MAX_EXECUTION_TIME_SECONDS = 15;

    /**
     * Check all links in the database and update their status.
     * 
     * @return void
     */
    public function checkAllLinks($linkIds = []): void
    {
        $tableName = DatabaseTablesManager::DATABASE_MOD_BROKEN_LINK_CHECKER;
        $sevenDaysAgo = gmdate('Y-m-d H:i:s', time() - (7 * DAY_IN_SECONDS));

        // Get URLs that are not currently marked broken, and were either never
        // scanned or scanned more than 7 days ago. Limit the batch size to avoid
        // processing the entire link table in a single cron run.
        $urls = $this->dbManager->table($tableName)
            ->where('status', 'broken', '!=')
            ->whereOr(function($query) use ($sevenDaysAgo) {
                $query->whereNull('scan_date')
                    ->where('scan_date', $sevenDaysAgo, '<');
            })
            ->limit(self::MAX_LINKS_PER_RUN);

        if (count($linkIds) > 0) {
            $urls->whereIn('id', $linkIds);
        }
        $urls->select(['id', 'url', 'urlHash'])->output('ARRAY_A');
        $urlsArray = $urls->get();
        
        if (!$urlsArray) {
            return;
        }
        
        $startTime = microtime(true);

        // Process URLs in batches for parallel processing
        $batches = array_chunk($urlsArray, 10);
        
        foreach ($batches as $batch) {
            $this->processUrlBatch($batch);

            // Stop processing further batches if we've exceeded the execution time budget
            if ((microtime(true) - $startTime) > self::MAX_EXECUTION_TIME_SECONDS) {
                break;
            }
        }
    }
    
    /**
     * Create request array for a URL
     * 
     * @param string $url The URL to create a request for
     * @param bool $blocking Whether the request should be blocking
     * @return array The request array
     */
    private function createRequest(string $url, ?bool $blocking = null): array
    {
        try {
            $host = wp_parse_url($url, PHP_URL_HOST);
        } catch (\Exception $e) {
            $host = null;
        }
        $isLocalhost = $host && (in_array($host, ['localhost', '127.0.0.1', '::1'])
                || str_contains($host, 'localhost'));

        return [
            'url' => $url,
            'method' => self::REQUEST_METHOD,
            'timeout' => self::REQUEST_TIMEOUT,
            'redirection' => self::REQUEST_REDIRECTION,
            'sslverify' => !$isLocalhost && self::REQUEST_SSL_VERIFY,
            'blocking' => $blocking ?? self::REQUEST_BLOCKING,
            'headers' => [
                'User-Agent' => sprintf(self::USER_AGENT_FORMAT, get_bloginfo('version'), get_bloginfo('url')),
            ],
        ];
    }
    
    /**
     * Send requests in parallel or sequentially
     * 
     * @param array $requests Array of request arrays
     * @return array Array of responses
     */
    private function sendRequests(array $requests): array
    {
        // Send parallel requests using WordPress HTTP API
        $responses = [];
        
        // Use wp_http_supports to check if parallel requests are supported
        if (wp_http_supports(['parallel' => true])) {
            // Use parallel requests
            $responses = Requests::request_multiple($requests);
        } else {
            // Fall back to sequential requests if parallel not supported
            foreach ($requests as $key => $request) {
                // Make blocking for sequential
                $request['blocking'] = true; // Override the blocking setting for sequential requests
                $responses[$key] = wp_remote_request($request['url'], $request);
            }
        }
        
        return $responses;
    }
    
    /**
     * Process a batch of URLs in parallel.
     * 
     * @param array $urls Array of URL data from the database
     * @return void
     */
    private function processUrlBatch(array $urls): void
    {
        // Create an array of requests
        $requests = [];

        foreach ($urls as $urlData) {
            if (!isset($urlData['url'])) {
                continue;
            }
            
            $url = $urlData['url'];
            $requests[] = $this->createRequest($url);
        }
        
        // Send requests
        $responses = $this->sendRequests($requests);
        
        // Process responses and update database
        foreach ($urls as $index => $urlData) {
            if (!isset($urlData['url'], $urlData['id'])) {
                continue;
            }
            
            $url = $urlData['url'];
            $id = (int) $urlData['id'];
            
            // Get the response for this URL
            $response = $responses[$index] ?? null;
            
            // Check if the URL is active or broken
            $status = $this->determineUrlStatus($url, $response);
            
            // Update the database
            $this->updateUrlStatus($id, $status);
        }
    }
    
    /**
     * Determine if a URL is active or broken based on the response.
     * 
     * @param string $url The URL that was checked
     * @param array|object|WP_Error|null $response The response from wp_remote_request
     * @return string 'active' or 'broken'
     */
    private function determineUrlStatus(string $url, $response): string
    {
        // Check if the response is empty or an error
        if (empty($response) || is_wp_error($response)) {
            return 'broken';
        }

        /**
         * Check if the response is an object and retrieve the status code
         * Handle different response types (WP_Error, WpOrg\Requests\Response, or array from wp_remote_request)
         */
        $status_code = 500; // Default status code
        
        if (is_object($response)) {
            // For WpOrg\Requests\Response objects
            if (property_exists($response, 'status_code')) {
                $status_code = $response->status_code;
            }
        } elseif (is_array($response) && isset($response['response']['code'])) {
            // For wp_remote_request response arrays
            $status_code = $response['response']['code'];
        }

        // Consider 200, 301, and 302 as active
        if (in_array($status_code, [200, 301, 302], true)) {
            return 'active';
        }

        // Any other status code is considered broken
        return 'broken';
    }
    
    /**
     * Update the status of a URL in the database.
     * 
     * @param int $id The ID of the URL in the database
     * @param string $status The new status ('active' or 'broken')
     * @return void
     */
    private function updateUrlStatus(int $id, string $status): void
    {
        $tableName = DatabaseTablesManager::DATABASE_MOD_BROKEN_LINK_CHECKER;
        
        $this->dbManager->update(
            $tableName,
            [
                'status' => $status,
                'scan_date' => current_time('mysql')
            ],
            ['id' => $id]
        );
    }
    
    /**
     * Update the status of a URL in the database using its hash.
     * 
     * @param string $urlHash The hash of the URL in the database
     * @param string $status The new status ('active' or 'broken')
     * @return void
     */
    private function updateUrlStatusByHash(string $urlHash, string $status): void
    {
        $tableName = DatabaseTablesManager::DATABASE_MOD_BROKEN_LINK_CHECKER;
        
        $this->dbManager->update(
            $tableName,
            [
                'status' => $status,
                'scan_date' => current_time('mysql')
            ],
            ['urlHash' => $urlHash]
        );
    }

    
    /**
     * Check links provided as an array of arrays containing id, url, and hash.
     * Updates the status in the table based on urlHash.
     * 
     * @param array $linksData Array of arrays, each containing 'id', 'url', and 'hash' keys
     * @return void
     */
    public function checkPostLinks(array $linksData): void
    {
        if (empty($linksData)) {
            return;
        }
        
        // Process URLs in batches for parallel processing
        $batches = array_chunk($linksData, 10);
        
        foreach ($batches as $batch) {
            $this->processLinksBatch($batch);
        }
    }
    
    /**
     * Process a batch of links data in parallel.
     * 
     * @param array $linksData Array of link data, each containing 'url' and 'hash'
     * @return void
     */
    private function processLinksBatch(array $linksData): void
    {
        // Create an array of requests
        $requests = [];
        
        foreach ($linksData as $linkData) {
            if (!isset($linkData['url']) || empty($linkData['url'])) {
                continue;
            }
            
            $url = $linkData['url'];
            $requests[] = $this->createRequest($url, true); // Make blocking for accurate status determination
        }
        
        // Send requests
        $responses = $this->sendRequests($requests);
        
        // Process responses and update database
        foreach ($linksData as $index => $linkData) {
            if (!isset($linkData['url'], $linkData['hash'])) {
                continue;
            }
            
            $url = $linkData['url'];
            $urlHash = $linkData['hash'];
            
            // Get the response for this URL
            $response = $responses[$index] ?? null;
            
            // Check if the URL is active or broken
            $status = $this->determineUrlStatus($url, $response);
            
            // Update the database based on urlHash
            $this->updateUrlStatusByHash($urlHash, $status);
        }
    }
    
    /**
     * Get or create a link status entry for a URL.
     * 
     * @param string $url The URL to check
     * @param string $urlHash The hash of the URL
     * @return int The ID of the link status entry
     */
    public function getOrCreateLinkStatus(string $url, string $urlHash): int
    {
        $tableName = DatabaseTablesManager::DATABASE_MOD_BROKEN_LINK_CHECKER;
        
        // Check if the URL already exists
        $link_status = $this->dbManager->table($tableName)
            ->where('urlHash', $urlHash)
            ->select('id')
            ->first();
            
        $link_status_id = $link_status->id ?? null;
        
        // If the URL doesn't exist, insert it
        if (!$link_status_id) {
            $link_status_id = $this->dbManager->insert(
                $tableName,
                [
                    'url' => $url,
                    'urlHash' => $urlHash,
                    'status' => 'unscanned',
                    'scan_date' => null
                ]
            );
        }
        
        return (int) $link_status_id;
    }

    /**
     * Check all links for a specific page/post and update their status.
     *
     * @param int|null $post_id The post ID to check links for, or null to use current post
     * @return void
     * @throws Exception
     */
    public function checkPageAllLinks(?int $post_id = null): void
    {
        // If no post ID provided, try to get it from the global context
        if ($post_id === null) {
            global $post;
            $post_id = $post->ID ?? 0;
            
            if ($post_id === 0) {
                return; // No valid post ID found
            }
        }
        
        $moduleManager = ModuleManager::instance();
        $linkAnalyzer = $moduleManager->linkAnalyzer();
        
        if (!$linkAnalyzer) {
            return; // LinkAnalyzer module not available
        }
        
        // Get all links for the post from LinkAnalyzer
        $links = $linkAnalyzer->getLinksForPost($post_id);
        
        if (empty($links)) {
            return;
        }
        
        $linksData = [];
        
        foreach ($links as $link) {
            // Skip empty URLs
            if (empty($link->url)) {
                continue;
            }
            
            // Create a hash for the URL
            $urlHash = md5($link->url);
            
            // Add to the links data array
            $linksData[] = [
                'url' => $link->url,
                'hash' => $urlHash
            ];
            
            // Create or get the link status entry
            $this->getOrCreateLinkStatus($link->url, $urlHash);
        }
        
        // Check all links
        $this->checkPostLinks($linksData);
    }

    public function getTableName(): string
    {
        return $this->dbManager->prefixTable(DatabaseTablesManager::DATABASE_MOD_BROKEN_LINK_CHECKER);
    }
}
