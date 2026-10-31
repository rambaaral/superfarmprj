<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Modules\ModuleLibrary\Links\LinkCounter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\DB\DatabaseManager;
use RankingCoach\Inc\Modules\ModuleBase\BaseModule;
use RankingCoach\Inc\Modules\ModuleManager;
use ReflectionException;

/**
 * Class LinkCounter
 */
class LinkCounter extends BaseModule {

	public const MODULE_NAME = 'linkCounter';

	/** @var int $linkCount The number of links on the page. */
	public int $linkCount = 0;

	/** @var int $internalLinks The number of internal links on the page. */
	public int $internalLinks = 0;

	/** @var int $externalLinks The number of external links on the page. */
	public int $externalLinks = 0;

	/** @var int $nofollowLinks The number of nofollow links on the page. */
	public int $nofollowLinks = 0;

	/** @var int $sponsoredLinks The number of sponsored links on the page. */
	public int $sponsoredLinks = 0;

	/**
	 * LinkCounter constructor.
	 *
	 * @param ModuleManager $moduleManager
	 *
	 * @throws ReflectionException
	 */
	public function __construct(ModuleManager $moduleManager)
	{
		$initialization = [
			'active' => false,
			'title' => 'Link Counter',
			'description' => 'Analyzes content to count internal and external links, providing statistics for SEO analysis and content optimization.  Configurable settings determine which types of links are included in the count.',
			'version' => '1.0.0',
			'name' => 'linkCounter',
			'priority' => 30,
			'dependencies' => [],
			'settings' => [
				['key' => 'count_internal_links', 'type' => 'boolean', 'default' => true, 'description' => 'Include internal links (links to other pages within your website) in the total link count.'],
				['key' => 'count_external_links', 'type' => 'boolean', 'default' => true, 'description' => 'Include external links (links to websites outside of your domain) in the total link count.'],
				['key' => 'count_nofollow_links', 'type' => 'boolean', 'default' => true, 'description' => 'Include links with the \'nofollow\' attribute in the count. These links are typically not followed by search engines.'],
				['key' => 'count_sponsored_links', 'type' => 'boolean', 'default' => true, 'description' => 'Include links with the \'sponsored\' attribute in the count. These links are typically paid or sponsored.']
			],
			'explain' => 'After a user publishes a blog post, the Link Counter module analyzes the content and counts the number of internal and external links. These counts are then displayed in the post editor or admin dashboard, allowing users to assess their linking strategy and make adjustments for better SEO. For instance, if the module detects a low number of internal links, it might suggest adding more relevant internal links to improve site navigation and distribute link equity.'
		];
		parent::__construct($moduleManager, $initialization);
	}

	/**
	 * Create necessary SQL tables if they don't already exist.
	 * @param string $table_name
	 * @param string $charset_collate
	 * @return string
	 * @noinspection SqlNoDataSourceInspection
	 */
	protected function getTableSchema(string $table_name, string $charset_collate): string
	{
        if(!$this->isActive()) {
            return '';
        }
		return "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            page_id bigint(20) NOT NULL,
            link_count int(11) NOT NULL,
            internal_links int(11) NOT NULL,
            external_links int(11) NOT NULL,
            nofollow_links int(11) NOT NULL,
            sponsored_links int(11) NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
	}

	// retrieve the latest row from the database
	/**
	 * Get the latest row from the database.
	 * @return array|object|null
	 * @noinspection SqlNoDataSourceInspection
	 */
	protected function getLatestRow(): object|array|null {
		$dbManager = DatabaseManager::getInstance();
		$tableName = $dbManager->db()->prefixTable($this->getTableName());
		$query = "SELECT * FROM {$tableName} ORDER BY id DESC LIMIT 1";
		$result = $dbManager->db()->queryRaw($query);
		return is_array($result) && count($result) > 0 ? $result[0] : null;
	}

	/**
	 * Register hooks for the LinkCounter module.
	 */
	public function initializeModule(): void
	{
		if(!$this->module_active) {
			return;
		}

		// Register the link counter data filter
		add_filter('beyondseo_counter/data', fn() => $this->getLatestRow());

		parent::initializeModule();
	}

	/**
	 * Override to include specific data for ImageOptimizer module.
	 * @return array Custom data specific to ImageOptimizer.
	 */
	public function getData(): array {
		return apply_filters('beyondseo_counter/data', []);
	}

	/**
	 * Retrieves the name of the module.
	 * @return string The name of the module.
	 */
	public static function getModuleNameStatic(): string {
		return self::MODULE_NAME;
	}
}
