<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Modules\ModuleLibrary\Links\LinkCounter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use DOMDocument;
use RankingCoach\Inc\Core\DB\DatabaseManager;
use RankingCoach\Inc\Modules\ModuleBase\BaseSubmoduleHooks;

/**
 * Class LinkCounterHooks
 */
class LinkCounterHooks extends BaseSubmoduleHooks {

	/** @var LinkCounter The LinkCounter module instance. */
	public LinkCounter $module;

	/**
	 * LinkCounterSettings constructor.
	 * @param LinkCounter $module
	 * @param array|null $params
	 */
	public function __construct(LinkCounter $module, ?array $params = null) {
		$this->module = $module;
		parent::__construct($module, $params);
	}

	/**
	 * Initializes the hooks for the submodule.
	 */
	public function initializeHooks(): void {
		//add_action('save_post', [$this, 'countLinks']);
	}

	/**
	 * Count the number of links on the current page.
	 */
	public function countLinks(int $post_id): void
	{
		if (!$this->module->isModuleInstalled()) {
			return;
		}

		// ignore the revision
		if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
			return;
		}

		$content = get_post_field('post_content', [ 'ID' => $post_id, 'post_type' => 'post', 'post_status' => 'publish' ]);
		if (!$content) {
			$this->module->linkCount = $this->module->internalLinks = $this->module->externalLinks = $this->module->nofollowLinks = $this->module->sponsoredLinks = 0;
			return;
		}

		$dom = new DOMDocument();

        // Suppress warnings for malformed HTML
        libxml_use_internal_errors(true);
        $dom->loadHTML(@mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors(false);

		$links = $dom->getElementsByTagName('a');

		foreach ($links as $link) {
			$href = $link->getAttribute('href');
			$rel = $link->getAttribute('rel');

			if ( $this->module->settingsComponent->getSetting('count_internal_links') && str_contains( $href, get_home_url() ) ) {
				$this->module->internalLinks++;
			} elseif ( $this->module->settingsComponent->getSetting('count_external_links') && ! str_contains( $href, get_home_url() ) ) {
				$this->module->externalLinks++;
			}

			if ( $this->module->settingsComponent->getSetting('count_nofollow_links') && str_contains( $rel, 'nofollow' ) ) {
				$this->module->nofollowLinks++;
			}

			if ( $this->module->settingsComponent->getSetting('count_sponsored_links') && str_contains( $rel, 'sponsored' ) ) {
				$this->module->sponsoredLinks++;
			}
		}

		$this->module->linkCount = $this->module->internalLinks + $this->module->externalLinks + $this->module->nofollowLinks + $this->module->sponsoredLinks;
		$this->insertData($post_id);
	}

	/**
	 * Insert the link counts into the database.
	 * @param int $post_id
	 */
	protected function insertData(int $post_id): void
	{
		$dbManager = DatabaseManager::getInstance();
		$table_name = $this->module->getTableName();

		$data = [
			'page_id' => $post_id,
			'link_count' => $this->module->linkCount,
			'internal_links' => $this->module->internalLinks,
			'external_links' => $this->module->externalLinks,
			'nofollow_links' => $this->module->nofollowLinks,
			'sponsored_links' => $this->module->sponsoredLinks
		];

		$dbManager->db()->table($table_name)->insert()->set($data)->get();
	}
}
