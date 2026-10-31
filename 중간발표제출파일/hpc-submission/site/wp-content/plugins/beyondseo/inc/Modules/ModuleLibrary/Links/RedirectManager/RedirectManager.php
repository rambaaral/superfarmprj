<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Links\RedirectManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
use RankingCoach\Inc\Core\DB\DatabaseTablesManager;
use RankingCoach\Inc\Modules\ModuleBase\BaseModule;
use RankingCoach\Inc\Modules\ModuleManager;
use ReflectionException;

/**
 * Class RedirectManager
 */
class RedirectManager extends BaseModule {

    /**
     * Table name constant for the redirects table
     */
    public const TABLE_NAME = DatabaseTablesManager::DATABASE_MOD_REDIRECTS;

	/**
	 * RedirectManager constructor.
	 *
	 * @param ModuleManager $moduleManager
	 *
	 * @throws ReflectionException
	 */
    public function __construct(ModuleManager $moduleManager) {
        $initialization = [
            'active' => true,
            'title' => 'Redirect Manager',
            'description' => 'Manages 301 and 302 redirects to prevent broken links, preserve SEO value, and improve user experience. Allows for creating, editing, and importing/exporting redirects. Integrates with the Broken Link Checker for automated redirect suggestions.',
            'version' => '1.0.0',
            'name' => 'redirectManager',
            'priority' => 15,
            'dependencies' => [],
            'settings' => [['key' => 'default_redirect_type', 'type' => 'string', 'default' => '301', 'description' => 'The default redirect type to use: \'301\' (permanent) or \'302\' (temporary).'], ['key' => 'auto_redirect_broken_links', 'type' => 'boolean', 'default' => False, 'description' => 'Automatically create redirects for broken links detected by the Broken Link Checker, using the most likely destination URL based on content similarity or historical data (if available).']],
            'explain' => 'When the \'Broken Link Checker\' module identifies a broken link, the Redirect Manager can automatically suggest a suitable redirection target based on content analysis or link history. The user can then review and approve the suggested redirect. The module also allows users to manually create redirects (301 or 302) for specific URLs, import redirects from a CSV file, and export existing redirects for backup or migration.  Using 301 redirects preserves SEO value by passing link equity from the old URL to the new one, preventing 404 errors and improving the user experience. ',
        ];
        parent::__construct($moduleManager, $initialization);
    }

    /**
     * This method is required by BaseModule but we're not using it anymore.
     * Tables are now created via migrations.
     *
     * @param string $table_name
     * @param string $charset_collate
     * @return string
     */
    protected function getTableSchema(string $table_name, string $charset_collate): string {
        // Tables are now created via migrations
        return '';
    }
	
	/**
	 * Get the table name for this module
	 *
	 * @return string The table name
	 */
	public function getTableName(): string {
		return self::TABLE_NAME;
	}
	
	/**
	 * Create a new redirect
	 *
	 * @param string $source_uri The source URL to redirect from
	 * @param string $destination_url The destination URL to redirect to
	 * @param int $redirect_code The HTTP redirect code (301 or 302)
	 * @param int $active Whether the redirect is active (0 or 1)
	 * @return int|false The ID of the new redirect, or false on failure
	 */
	public function createRedirect(string $source_uri, string $destination_url, int $redirect_code = 301, int $active = 1) {
		// Validate redirect code
		$redirect_code = in_array($redirect_code, [301, 302]) ? $redirect_code : 301;
		
		// Validate active status
		$active = in_array($active, [0, 1]) ? $active : 1;

        // Validate and extract URI from source_uri
        if (filter_var($source_uri, FILTER_VALIDATE_URL)) {
            $parsed_url = wp_parse_url($source_uri);
            $source_uri = rtrim($parsed_url['path'], '/');
        } else {
            $source_uri = rtrim($source_uri, '/');
        }
		
		// Check if source URI already exists using builder pattern
		$existing = $this->dbManager->table($this->getTableName())
            ->select('id')
            ->where('source_uri', $source_uri)
            ->output('ARRAY_A')
            ->get();
		
		if ($existing) {
			return false; // Redirect already exists
		}
		
		// Insert new redirect using builder pattern
		$data = [
			'source_uri' => $source_uri,
			'destination_url' => $destination_url,
			'redirect_code' => $redirect_code,
			'active' => $active,
		];
		
		return $this->dbManager->table($this->getTableName())
            ->insert()
            ->set($data)
            ->get();
	}
	
	/**
	 * Get a redirect by ID
	 *
	 * @param int $id The ID of the redirect
	 * @return object|null The redirect object, or null if not found
	 */
	public function getRedirect(int $id): mixed
    {
		return $this->dbManager->table($this->getTableName())
            ->select('*')
            ->where('id', $id)
            ->get();
	}
	
	/**
	 * Get all redirects with optional pagination
	 *
	 * @param int $limit Optional limit for pagination
	 * @param int $offset Optional offset for pagination
	 * @return array Array containing redirects and pagination info
	 */
	public function getRedirects(int $limit = 100, int $offset = 0): array {
		// Get all redirects using builder pattern
		$redirects = $this->dbManager->table($this->getTableName())
            ->select('*')
            ->orderBy('id', 'ASC')
            ->limit($limit, $offset)
            ->output('ARRAY_A')
            ->get() ?: [];

		return [
			'redirects' => $redirects,
		];
	}
	
	/**
	 * Update an existing redirect
	 *
	 * @param int $id The ID of the redirect to update
	 * @param array $data The data to update
	 * @return bool True on success, false on failure
	 */
	public function updateRedirect(int $id, array $data): bool {
		// Check if redirect exists using builder pattern
		$existing = $this->dbManager->table($this->getTableName())
            ->select('id')
            ->where('id', $id)
            ->output('ARRAY_A')
            ->get();
		
		if (empty($existing)) {
			return false; // Redirect not found
		}
		
		// Prepare data for update
		$update_data = [];
		
		// Only update fields that were provided
		if (isset($data['source_uri'])) {
			$update_data['source_uri'] = $data['source_uri'];
			
			// Check if the new source URI already exists (excluding current record) using builder pattern
			$duplicate = $this->dbManager->table($this->getTableName())
                ->select('id')
                ->where('source_uri', $data['source_uri'])
                ->where('id', $id, '!=')
                ->output('ARRAY_A')
                ->get();
			
			if (!empty($duplicate)) {
				return false; // Duplicate source URI
			}
		}
		
		if (isset($data['destination_url'])) {
			$update_data['destination_url'] = $data['destination_url'];
		}
		
		if (isset($data['redirect_code'])) {
			$update_data['redirect_code'] = in_array((int)$data['redirect_code'], [301, 302]) 
				? (int)$data['redirect_code'] 
				: 301;
		}
		
		if (isset($data['active'])) {
			$update_data['active'] = in_array((int)$data['active'], [0, 1]) 
				? (int)$data['active'] 
				: 1;
		}
		
		// If no data to update, return success
		if (empty($update_data)) {
			return true;
		}
		
		// Update redirect using builder pattern
		$result = $this->dbManager->table($this->getTableName())
            ->update()
            ->set($update_data)
            ->where('id', $id)
            ->get();
		
		return $result !== false;
	}
	
	/**
	 * Delete a redirect
	 *
	 * @param int $id The ID of the redirect to delete
	 * @return bool True on success, false on failure
	 */
	public function deleteRedirect(int $id): bool {
		// Check if redirect exists using builder pattern
		$existing = $this->dbManager->table($this->getTableName())
            ->select('id')
            ->where('id', $id)
            ->output('ARRAY_A')
            ->get();
		
		if (empty($existing)) {
			return false; // Redirect not found
		}
		
		// Delete redirect using builder pattern
		$result = $this->dbManager->table($this->getTableName())
            ->delete()
            ->where('id', $id)
            ->get();
		
		return $result !== false;
	}
	
	/**
	 * Find a redirect by source URI
	 *
	 * @param string $source_uri The URI to find a redirect for
	 * @return object|null The redirect object if found, null otherwise
	 */
	public function findRedirectByUrl(string $source_uri): mixed
    {
		// Only get active redirects using builder pattern
		return $this->dbManager->table($this->getTableName())
            ->select('*')
            ->where('source_uri', $source_uri)
            ->where('active', 1)
            ->first();
	}
	
	/**
	 * Atomically increment the hit_count for a redirect
	 *
	 * @param int $id The ID of the redirect to update
	 * @return bool True on success, false on failure
	 */
	public function incrementHitCount(int $id): bool
	{
		// First get the current hit_count
		$redirect = $this->dbManager->table($this->getTableName())
			->select('hit_count')
			->where('id', $id)
			->first();

		if (!$redirect) {
			return false;
		}
		
		// Increment the hit_count
		$newHitCount = (int)$redirect->hit_count + 1;
		
		// Update using the builder pattern
		$result = $this->dbManager->table($this->getTableName())
			->update()
			->set(['hit_count' => $newHitCount])
			->where('id', $id)
			->get();

		return $result !== false;
	}


}
