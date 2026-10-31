<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Modules\ModuleLibrary\Links\LinkCounter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Modules\ModuleBase\BaseModule;
use RankingCoach\Inc\Modules\ModuleBase\BaseSubmoduleSettings;

/**
 * Class LinkCounterSettings
 */
class LinkCounterSettings extends BaseSubmoduleSettings {

	/** @var BaseModule The LinkCounter module instance. */
	public BaseModule $module;

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
	 * Initializes settings
	 */
	public function initializeSettings(): void {
		parent::init();
	}
}