<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Modules\ModuleBase;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Modules\ModuleManager;

/**
 * Interface ModuleInterface
 */
interface ModuleInterface {

	/**
	 * ModuleInterface constructor.
	 * @param ModuleManager $moduleManager
	 */
	public function __construct(ModuleManager $moduleManager);

	/**
	 * Activates the module.
	 * @return void
	 */
	public function initializeModule(): void;

	/**
	 * Returns the name of the module.
	 * @return string
	 */
	public function getModuleName(): string;

	/**
	 * Returns if the module is active.
	 * @return bool
	 */
	public function isModuleInstalled(): bool;
}