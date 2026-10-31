<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Modules\ModuleLibrary\Links\LinkAnalyzer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Modules\ModuleBase\BaseModule;
use RankingCoach\Inc\Modules\ModuleManager;
use RankingCoach\Inc\Core\DB\DatabaseTablesManager;
use ReflectionException;

/**
 * Class LinkAnalyzer
 * 
 * Analyzes and extracts links from posts, storing them in a database for further analysis.
 */
class LinkAnalyzer extends BaseModule {

    public const MODULE_NAME = 'linkAnalyzer';

    /** @var LinkAnalyzerHooks $hooksComponent */
    public mixed $hooksComponent = null;

    /**
     * LinkAnalyzer constructor.
     *
     * @param ModuleManager $moduleManager
     *
     * @throws ReflectionException
     */
    public function __construct(ModuleManager $moduleManager)
    {
        $initialization = [
            'active' => true,
            'title' => 'Link Analyzer',
            'description' => 'Extracts and analyzes links from content, storing detailed information about each link for SEO analysis and optimization.',
            'version' => '1.0.0',
            'name' => 'linkAnalyzer',
            'priority' => 30,
            'dependencies' => [],
            'settings' => [
                ['key' => 'analyze_internal_links', 'type' => 'boolean', 'default' => true, 'description' => 'Extract and analyze internal links (links to other pages within your website).'],
                ['key' => 'analyze_external_links', 'type' => 'boolean', 'default' => true, 'description' => 'Extract and analyze external links (links to websites outside of your domain).'],
                ['key' => 'analyze_nofollow_links', 'type' => 'boolean', 'default' => true, 'description' => 'Extract and analyze links with the \'nofollow\' attribute.'],
                ['key' => 'analyze_sponsored_links', 'type' => 'boolean', 'default' => true, 'description' => 'Extract and analyze links with the \'sponsored\' attribute.'],
            ],
            'explain' => 'The Link Analyzer module extracts all links from posts and pages, storing detailed information about each link including URL, hostname, and whether it\'s an external link. This data can be used for SEO analysis, broken link detection, and understanding the linking structure of your website.'
        ];
        parent::__construct($moduleManager, $initialization);
    }
    
    /**
     * Register hooks for the LinkAnalyzer module.
     */
    public function initializeModule(): void
    {
        if(!$this->module_active) {
            return;
        }

        // Register the link analyzer data filter
        add_filter('beyondseo_analyzer/data', [$this, 'getData']);

        parent::initializeModule();
    }

    /**
     * Get inbound links for a specific post (links from other posts to this post).
     * 
     * @param int $post_id The post ID to get inbound links for
     * @return array Array of inbound links
     */
    protected function getInboundLinks(int $post_id): array
    {
        $tableName = $this->getTableName();
        $postsTable = $this->dbManager->prefixTable('posts');
        
        // Get the broken link checker table name
        $brokenLinkChecker = ModuleManager::instance()->brokenLinkChecker();
        $brokenLinkTableName = $brokenLinkChecker?->getTableName();
        
        // Get the post permalink to find inbound links
        $post_permalink = get_permalink($post_id);
        if (!$post_permalink) {
            return [];
        }
        
        $post_url_hash = sha1($post_permalink);
        
        // Build the query using the builder pattern
        $query = $this->dbManager->table($tableName, 'la')
            ->join($postsTable . ' AS p', 'la.post_id = p.ID');
            
        // Add fields to select
        if ($brokenLinkChecker && $brokenLinkTableName) {
            // Add join to broken link checker table
            $query->leftJoin($brokenLinkTableName . ' AS blc', 'la.link_status_id = blc.id')
                ->select([
                    'la.*', 
                    'p.post_title AS source_post_title', 
                    'p.guid AS source_url',
                    'blc.status',
                    'blc.scan_date'
                ]);
        } else {
            $query->select([
                'la.*', 
                'p.post_title AS source_post_title', 
                'p.guid AS source_url'
            ]);
        }
        
        // Add where conditions and execute
        $inbound_links = $query->where('la.url_hash', $post_url_hash)
            ->where('la.post_id', $post_id, '!=')
            ->output('ARRAY_A')
            ->get() ?: [];
        
        // Process inbound links to ensure the URL represents the source post
        foreach ($inbound_links as &$link) {
            // Store the original URL (which points to current post) in a separate field
            $link['target_url'] = $link['url'];
            // Replace URL with the source post's permalink
            $link['url'] = get_permalink($link['post_id']);
        }
        
        return $inbound_links;
    }

    /**
     * Get outbound and external links for a specific post.
     *
     * @param int $post_id The post ID to get outbound links for
     * @return array Array with 'outbound' and 'external' keys
     * @throws \Exception
     */
    protected function getOutboundLinks(int $post_id): array
    {
        $tableName = $this->getTableName();
        
        // Get the broken link checker table name
        $brokenLinkChecker = ModuleManager::instance()->brokenLinkChecker();
        $brokenLinkTableName = $brokenLinkChecker?->getTableName();
        
        $result = [
            'outbound' => [],
            'external' => []
        ];
        
        // Build the query using the builder pattern
        $query = $this->dbManager->table($tableName, 'la');
        
        // Add fields to select and joins
        if ($brokenLinkChecker && $brokenLinkTableName) {
            $query->leftJoin($brokenLinkTableName . ' AS blc', 'la.link_status_id = blc.id')
                ->select(['la.*', 'blc.status', 'blc.scan_date']);
        } else {
            $query->select('*');
        }
        
        // Add where condition and execute
        $links = $query->where('post_id', $post_id)
            ->output('ARRAY_A')
            ->get() ?: [];
        
        // Separate outbound and external links
        foreach ($links as $link) {
            if ((int)$link['external'] === 1) {
                $result['external'][] = $link;
            } else {
                $result['outbound'][] = $link;
            }
        }
        
        return $result;
    }
    
    /**
     * Get link data for a specific post with broken link checker status.
     * 
     * @param int $post_id The post ID to get links for
     * @return array Array with inbound, outbound, and external links
     */
    public function getLinksForPost(int $post_id): array
    {
        // Get all links
        $inboundLinks = $this->getInboundLinks($post_id);
        $outboundLinks = $this->getOutboundLinks($post_id);
        
        // Combine all links to extract unique post IDs
        $allLinks = array_merge(
            $inboundLinks,
            $outboundLinks['outbound'],
            $outboundLinks['external']
        );
        
        // Extract unique post IDs from all links
        $uniquePostIds = [];
        foreach ($allLinks as $link) {
            $uniquePostIds[$link['post_id']] = [];
        }
        
        // Get permalinks for each unique post ID and extract only the URI part
        $postPermalinks = [];
        foreach (array_keys($uniquePostIds) as $postId) {
            $permalink = get_permalink($postId);
            if ($permalink) {
                // Parse the URL to extract only the URI part (path and query)
                $urlParts = wp_parse_url($permalink);
                $uri = $urlParts['path'] ?? '';
                // Only apply rtrim if the URI is not the home URL
                $postPermalinks[$postId] = ($uri === '/') ? $uri : rtrim($uri, '/');
            }
        }

        // Add source URI to each link
        $processedInbound = $this->addSourceUriToLinks($inboundLinks, $postPermalinks);
        $processedOutbound = $this->addSourceUriToLinks($outboundLinks['outbound'], $postPermalinks);
        $processedExternal = $this->addSourceUriToLinks($outboundLinks['external'], $postPermalinks);
        
        return [
            'inbound' => $processedInbound,
            'outbound' => $processedOutbound,
            'external' => $processedExternal
        ];
    }
    
    /**
     * Add source URI to each link based on post_id.
     * 
     * @param array $links Array of links
     * @param array $postPermalinks Array of post URIs indexed by post ID
     * @return array Processed links with source URIs
     */
    private function addSourceUriToLinks(array $links, array $postPermalinks): array
    {
        foreach ($links as &$link) {
            if (isset($link['post_id'], $postPermalinks[$link['post_id']])) {
                $link['source_uri'] = $postPermalinks[$link['post_id']];
            }
        }
        unset($link); // Unset the reference
        
        return $links;
    }

    /**
     * Get all links data.
     *
     * @param int $limit Optional limit for number of results
     * @param int $offset Optional offset for pagination
     * @return array Array containing limit, offset, count and data
     * @throws \Exception
     */
    public function getAllLinks(?int $post_id = null, int $limit = 100, int $offset = 0): array
    {
        $tableName = $this->getTableName();

        // Get the broken link checker table name
        $brokenLinkChecker = ModuleManager::instance()->brokenLinkChecker();
        $brokenLinkTableName = $brokenLinkChecker?->getTableName();

        // Get total count of links using the builder pattern
        $total_count = $this->dbManager->table($tableName)->count();

        // Build the query using the builder pattern
        $query = $this->dbManager->table($tableName, 'la');
        
        // Add fields to select and joins
        if ($brokenLinkChecker && $brokenLinkTableName) {
            $query->leftJoin($brokenLinkTableName . ' AS blc', 'la.link_status_id = blc.id')
                ->select(['la.*', 'blc.status']);
        } else {
            $query->select('*');
        }
        if ($post_id) {
            // If post_id is provided, filter by it
            $query->where('la.post_id', $post_id);
        }
        
        // Add order, limit, offset and execute
        $data = $query->orderBy('la.id', 'DESC')
            ->limit($limit, $offset)
            ->output('ARRAY_A')
            ->get() ?: [];

        return [
            'limit' => $limit,
            'offset' => $offset,
            'count' => $total_count,
            'data' => $data
        ];
    }

    /**
     * Override to include specific data for LinkAnalyzer module.
     * @return array Custom data specific to LinkAnalyzer.
     * @throws \Exception
     */
    public function getData(): array {
        return $this->getAllLinks(10, 0);
    }
    
    /**
     * Get the table name for this module
     * 
     * @return string The table name with prefix
     */
    public function getTableName(): string
    {
        return $this->dbManager->prefixTable(DatabaseTablesManager::DATABASE_MOD_LINK_ANALYZER);
    }

    /**
     * Store extracted links in the database.
     * 
     * @param array $links Array of link data to store
     */
    public function storeLinks(array $links): void
    {
        $table_name = $this->getTableName();
        
        foreach ($links as $link) {
            $data = [
                'post_id' => $link['post_id'],
                'url' => $link['url'],
                'url_hash' => $link['url_hash'],
                'hostname' => $link['hostname'],
                'hostname_hash' => $link['hostname_hash'],
                'external' => $link['external'],
                'link_text' => $link['link_text'] ?? ''
            ];
            
            // Add link_status_id if available
            if (isset($link['link_status_id']) && $link['link_status_id'] !== null) {
                $data['link_status_id'] = $link['link_status_id'];
            }
            
            // Use the builder pattern for insert
            $query = $this->dbManager->table($table_name)
                ->insert()
                ->set($data);
                
            $result = $query->get();
            
            if ($result === false) {
                $this->log('Error inserting link: ' . $this->dbManager->db()->last_error, 'ERROR');
            }
        }
    }

    /**
     * Delete links for a specific post.
     * 
     * @param int $post_id The post ID to delete links for
     */
    public function deleteLinks(int $post_id): void
    {
        if (!$this->isModuleInstalled()) {
            return;
        }
        
        $table_name = $this->getTableName();
        
        // Use the builder pattern for delete
        $this->dbManager->table($table_name)
            ->delete()
            ->where('post_id', $post_id)
            ->get();
    }

    /**
     * Get posts to scan based on scannable post types and public statuses.
     * 
     * @param int $limit Number of posts to retrieve
     * @param int $offset Offset for pagination
     * @return array Array of post objects
     */
    public function getPostsToScan(int $limit = 50, int $offset = 0): array
    {
        $postsTable = $this->dbManager->prefixTable('posts');
        
        // Get public post statuses
        $post_statuses = $this->getPublicPostStatuses();
        
        // Get scannable post types
        $post_types = $this->getScannablePostTypes();
        
        // Build the query using the builder pattern
        $query = $this->dbManager->table($postsTable, 'p')
            ->select(['DISTINCT p.ID', 'p.post_content', 'p.post_type', 'p.post_status'])
            ->whereIn('p.post_status', $post_statuses)
            ->whereIn('p.post_type', $post_types)
            ->where('p.post_content', '', '!=')
            ->orderBy('p.ID', 'DESC')
            ->limit($limit, $offset);

        return $query->get() ?: [];
    }

    /**
     * Get public post statuses.
     * 
     * @return array Array of public post-status slugs
     */
    protected function getPublicPostStatuses(): array
    {
        $public_statuses = get_post_stati(['public' => true]);
        $private_statuses = get_post_stati(['private' => true]);
        
        return array_merge($public_statuses, $private_statuses);
    }
    
    /**
     * Get scannable post types.
     * 
     * @return array Array of scannable post type slugs
     */
    protected function getScannablePostTypes(): array
    {
        $post_types = get_post_types(['public' => true]);
        
        // Remove attachment post-type as it typically doesn't contain content with links
        if (isset($post_types['attachment'])) {
            unset($post_types['attachment']);
        }
        
        // Allow filtering of scannable post-types
        return apply_filters('beyondseo_analyzer_scannable_post_types', $post_types);
    }

    /**
     * Remove all links from the database.
     *
     * @throws \Exception
     */
    public function removeAllLinks(): void
    {
        $table_name = $this->getTableName();

        // Delete all links from the table using the builder pattern
        $this->dbManager->table($table_name)
            ->truncate()
            ->get();

        /**
         * Delete all links from the broken link checker table if it exists
         */
        $brokenLinkChecker = ModuleManager::instance()->brokenLinkChecker();
        if ($brokenLinkChecker) {
            $brokenLinkTableName = $brokenLinkChecker->getTableName();
            $this->dbManager->table($brokenLinkTableName)
                ->truncate()
                ->get();
        }
    }

    /**
     * Retrieves the hookComponenet
     * @return LinkAnalyzerHooks
     */
    public function getHooksComponent(): LinkAnalyzerHooks
    {
        return parent::getHooksComponent();
    }

    /**
     * Retrieves the name of the module.
     * @return string The name of the module.
     */
    public static function getModuleNameStatic(): string {
        return self::MODULE_NAME;
    }
}
