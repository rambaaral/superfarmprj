<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Modules\ModuleLibrary\Links\LinkCounter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Modules\ModuleBase\BaseSubmoduleUi;

/**
 * Class LinkCounterUi
 */
class LinkCounterUi extends BaseSubmoduleUi {

	/** @var LinkCounter The LinkCounter module instance. */
	public LinkCounter $module;

	/**
	 * LinkCounterUi constructor.
	 * @param LinkCounter $module
	 * @param array|null $params
	 */
	public function __construct(LinkCounter $module, ?array $params = null) {
		$this->module = $module;
		parent::__construct($module, $params);
	}

	/**
	 * Initializes the UI logic.
	 */
	public function initializeUi(): void {
		add_action('admin_notices', [$this, 'renderView']);
	}

	/**
	 * Renders a view of the link count in the admin dashboard.
	 */
	public function renderView(): void
	{
		$data = apply_filters( 'beyondseo_counter/data', null );
		echo sprintf(
			'<div class="notice notice-info"><p>Total links: %d | Internal: %d | External: %d | Nofollow: %d | Sponsored: %d</p></div>',
			esc_html($data->link_count ?? 0),
			esc_html($data->internal_links ?? 0),
			esc_html($data->external_links ?? 0),
			esc_html($data->nofollow_links ?? 0),
			esc_html($data->sponsored_links ?? 0)
		);
	}
}
