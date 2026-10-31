<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Technical\CustomSidebar;

if (!defined('ABSPATH')) {
    exit;
}

use RankingCoach\Inc\Modules\ModuleBase\BaseModule;
use RankingCoach\Inc\Modules\ModuleManager;
use ReflectionException;

/**
 * Class CustomSidebar
 * 
 * Handles the integration of SEO sidebar into various WordPress editors
 * (Gutenberg, Elementor, Classic Editor)
 * 
 * @package RankingCoach\Inc\Modules\ModuleLibrary\Technical\CustomSidebar
 */
class CustomSidebar extends BaseModule
{
    public const MODULE_NAME = 'customSidebar';

    /**
     * CustomSidebar constructor.
     * 
     * @param ModuleManager $moduleManager
     * @throws ReflectionException
     */
    public function __construct(ModuleManager $moduleManager)
    {
        $initialization = [
            'active' => true,
            'title' => 'SEO Sidebar',
            'description' => 'Adds a SEO sidebar to the WordPress editor for managing SEO metadata.',
            'version' => '1.0.0',
            'name' => self::MODULE_NAME,
            'priority' => 10,
            'dependencies' => [],
            'settings' => [],
        ];
        parent::__construct($moduleManager, $initialization);
    }

    /**
     * Registers the hooks for the module.
     * 
     * @return void
     */
    public function initializeModule(): void
    {
        if (!$this->module_active) {
            return;
        }

        parent::initializeModule();
    }

    /**
     * Retrieves the name of the module.
     * 
     * @return string The name of the module.
     */
    public static function getModuleNameStatic(): string
    {
        return self::MODULE_NAME;
    }
}