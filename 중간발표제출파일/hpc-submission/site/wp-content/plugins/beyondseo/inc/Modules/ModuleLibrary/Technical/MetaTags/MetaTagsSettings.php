<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Modules\ModuleLibrary\Technical\MetaTags;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Modules\ModuleBase\BaseModule;
use RankingCoach\Inc\Modules\ModuleBase\BaseSubmoduleSettings;

/**
 * Class MetaTagsSettings
 */
class MetaTagsSettings extends BaseSubmoduleSettings {

	/** @var BaseModule The MetaTags module instance. */
	public BaseModule $module;

	/**
	 * MetaTagsSettings constructor.
	 * @param MetaTags $module
	 * @param array|null $params
	 */
	public function __construct(MetaTags $module, ?array $params = null) {
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