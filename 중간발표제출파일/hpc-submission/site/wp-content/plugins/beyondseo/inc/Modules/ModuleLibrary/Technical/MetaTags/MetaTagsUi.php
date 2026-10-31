<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Modules\ModuleLibrary\Technical\MetaTags;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Modules\ModuleBase\BaseSubmoduleUi;

/**
 * Class MetaTagsUi
 */
class MetaTagsUi extends BaseSubmoduleUi {

	/** @var MetaTags The MetaTags module instance. */
	public MetaTags $module;

	/**
	 * LinkCounterUi constructor.
	 *
	 * @param MetaTags $module
	 * @param array|null $params
	 */
	public function __construct( MetaTags $module, ?array $params = null ) {
		$this->module = $module;
		parent::__construct( $module, $params );
	}

	/**
	 * Initializes the UI for the submodule.
	 * @return void
	 */
	public function initializeUi(): void {
		/** @var MetaTags $module */
		$module = $this->module;
	}
}