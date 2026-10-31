<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Modules\ModuleBase;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;

/**
 * Class BaseSubmoduleUi
 */
abstract class BaseSubmoduleUi {

	use RcLoggerTrait;

	/**
	 * @param BaseModule $module
	 * @param array|null $params
	 */
	public function __construct(BaseModule $module, ?array $params = null) {
		// Implement constructor
	}

	/**
	 * Initializes the UI
	 * @return void
	 */
	abstract public function initializeUi(): void;
}