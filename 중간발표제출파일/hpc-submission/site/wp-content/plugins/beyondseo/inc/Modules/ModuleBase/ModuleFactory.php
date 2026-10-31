<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Modules\ModuleBase;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Exception;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Core\MetaModulesManager;
use RankingCoach\Inc\Interfaces\MetaHeadBuilderInterface;
use RankingCoach\Inc\Modules\ModuleManager;
use ReflectionException;

/**
 * Class ModuleFactory
 */
class ModuleFactory {
	/**
	 * Creates a module instance.
	 *
	 * @param string $className The class name of the
	 * @param ModuleManager $manager The module manager.
	 *
	 * @return ModuleInterface|null
	 * @throws ReflectionException
	 * @throws Exception
	 */
	public function create(string $className, ModuleManager $manager): ?ModuleInterface {
		if (!class_exists($className)) {
			return null;
		}

		// Instantiate the module
		$module = new $className($manager);
		if(!($module instanceof ModuleInterface)) {
			return null;
		}

		// Check if the module has ability to build meta head content
		if($module instanceof MetaHeadBuilderInterface) {
			if(! WordpressHelpers::is_admin_request() ) {
				/** @var MetaModulesManager $metaModules */
                $metaModules = MetaModulesManager::getInstance();
				$metaModules->addModule($module->getModuleName());
			}
		}

		if (method_exists($module, 'initializeWithSettings')) {
			/** @var BaseModule $module */
			$module->initializeWithSettings();
		}

		return $module;
	}
}