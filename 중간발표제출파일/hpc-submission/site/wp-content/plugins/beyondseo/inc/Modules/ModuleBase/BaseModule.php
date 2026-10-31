<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Modules\ModuleBase;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\Helpers\Traits\RcApiTrait;
use RankingCoach\Inc\Core\DB\DatabaseManager;
use RankingCoach\Inc\Modules\ModuleManager;
use ReflectionClass;
use ReflectionException;

/**
 * Class BaseModule
 */
abstract class BaseModule implements ModuleInterface {

	use RcApiTrait;
	use RcLoggerTrait;
	
	/** @var DatabaseManager $dbManager The database manager instance */
	protected DatabaseManager $dbManager;

	// Sub-components
	/** @var BaseSubmoduleSettings|null $settingsComponent The configuration component for the module. */
	public ?BaseSubmoduleSettings $settingsComponent = null;

	/** @var object|null $hooksComponent The hook component for the module. */
	public mixed $hooksComponent = null;

	/** @var object|null $apiComponent The API component for the module. */
	protected mixed $apiComponent = null;

	/** @var object|null $uiComponent The UI component for the module. */
	protected mixed $uiComponent = null;

	// Module manager reference
	/** @var ModuleManager $manager the module manager */
	protected ModuleManager $manager;

	// General module properties
	/** @var bool $module_installed the module installed status */
	protected bool $module_installed = false;

	/** @var string $module_title the module title */
	protected string $module_title;

	/** @var string $module_description the module description */
	protected string $module_description;

	/** @var string $module_version the module version */
	protected string $module_version;

	/** @var string $module_name the module name */
	protected string $module_name;

	/** @var array $module_settings the module settings */
	protected array $module_settings = [];

	/** @var string[] $module_dependencies the modules name that this module depends on */
	protected array $module_dependencies = [];

	/** @var int $module_priority the module priority */
	protected int $module_priority = 0;

	/** @var bool $module_active the module active status */
	protected bool $module_active = false;

	/**
	 * BaseModule constructor.
	 * @param ModuleManager $moduleManager
	 * @param array $config
	 * @throws ReflectionException
	 */
	public function __construct( ModuleManager $moduleManager, array $config = [] )
	{
		$this->manager = $moduleManager;
		$this->dbManager = DatabaseManager::getInstance();

		// Set module properties
		$this->module_active = $config['active'] ?? false;
		$this->module_title = $config['title'] ?? 'Default Title';
		$this->module_description = $config['description'] ?? 'Default Description';
		$this->module_version = $config['version'] ?? '1.0.0';
		$this->module_name = $config['name'] ?? strtolower(static::class);
		$this->module_dependencies = $config['dependencies'] ?? [];
		$this->module_priority = $config['priority'] ?? 0;
		$this->module_settings = $config['settings'] ?? [];

		// Load components dynamically based on file existence
		$moduleBaseName          = basename(str_replace('\\', '/', static::class));
		$this->settingsComponent = $this->loadSubcomponent($moduleBaseName, 'Settings', $config['settings'] ?? []);
		$this->hooksComponent     = $this->loadSubcomponent($moduleBaseName, 'Hooks');
		$this->apiComponent = $this->loadSubcomponent($moduleBaseName, 'Api');
		$this->uiComponent = $this->loadSubcomponent($moduleBaseName, 'Ui');
	}

	/**
	 * Check if the module is active.
	 * @return bool
	 */
	public function isActive(): bool
	{
		return $this->module_active;
	}

	/**
	 * Load a component for the module.
	 * @param string $moduleBaseName
	 * @param string $component The component to load.
	 * @param array|null $params
	 * @return object|null The component object if it was loaded successfully, null otherwise.
	 * @throws ReflectionException
	 */
	protected function loadSubcomponent(string $moduleBaseName, string $component, ?array $params = null): ?object
	{
		// Get the full class name of the child class that called this method
		$fullClassName = get_called_class();

		// Get the namespace of the child class using ReflectionClass to avoid string manipulation
		$reflection = new ReflectionClass($fullClassName);
		$moduleNamespace = $reflection->getNamespaceName();

		// Construct the full class name of the component
		$componentClass = $moduleNamespace . '\\' . $moduleBaseName . $component;

		// Get the base directory of the current module to construct the component path
		$baseDirectory = dirname($reflection->getFileName(), 2); // Adjust to the correct depth for your structure
		$componentPath = $baseDirectory . '/' . $moduleBaseName . '/' . $moduleBaseName . $component . '.php';

		// Check if the file exists and the class is defined
		if (file_exists($componentPath)) {
			require_once $componentPath;

			if (class_exists($componentClass)) {
				return new $componentClass($this, $params);
			}
		}

		return null;
	}

	/**
	 * Initialize subcomponents of the module.
	 */
	public function initializeModule(): void
	{
		if ($this->hooksComponent) {
			$this->hooksComponent->initializeHooks();
		}
		if ($this->apiComponent) {
			$this->apiComponent->initializeApi();
		}
		if ($this->uiComponent) {
			$this->uiComponent->initializeUi();
		}
	}

	// === Public Getters for Module Properties ===

	/**
	 * Retrieves the title of the module.
	 * @return string The title of the module.
	 */
	public function getTitle(): string {
		return $this->module_title;
	}

	/**
	 * Retrieves the description of the module.
	 * @return string The description of the module.
	 */
	public function getDescription(): string {
		return $this->module_description;
	}

	/**
	 * Retrieves the version of the module.
	 * @return string The version of the module.
	 */
	public function getVersion(): string {
		return $this->module_version;
	}

	/**
	 * Retrieves the name of the module.
	 * @return string The name of the module.
	 */
	public function getModuleName(): string {
		return $this->module_name;
	}

	/**
	 * Retrieves the option key for the settings of the module.
	 * @return string|null The option key for the settings of the module.
	 */
	public static function getOptionKeySettings(): ?string {
		$moduleName = strtolower(static::class);
		if (method_exists(static::class, 'getModuleNameStatic')) {
			$moduleName = static::getModuleNameStatic();
		}
		return RANKINGCOACH_FILENAME . '_module_(' . $moduleName . ')_settings';
	}

	/**
	 * Retrieves the settings of the module.
	 * @return array The settings of the module.
	 */
	public function getSettings(): array {
		return $this->module_settings;
	}

	/**
	 * Retrieves the dependencies of the module.
	 * @return string[] The dependencies of the module.
	 */
	public function getDependencies(): array {
		return $this->module_dependencies;
	}

	/**
	 * Retrieves the priority of the module.
	 * @return int The priority of the module.
	 */
	public function getPriority(): int {
		return $this->module_priority;
	}

	// === Dependency and Installation Checks ===

	/**
	 * Checks if the module has dependencies.
	 * @return bool True if the module has dependencies, false otherwise.
	 */
	public function hasDependencies(): bool {
		return !empty($this->module_dependencies);
	}

	/**
	 * Checks if the module is installed.
	 * @return bool True if the module is installed, false otherwise.
	 */
	public function isModuleInstalled(): bool {
		return $this->manager->is_module_installed($this->module_name);
	}

	/**
	 * Checks if the module is blocked by dependencies.
	 * @return bool True if the module is blocked by dependencies, false otherwise.
	 */
	public function blockedByDependencies(): bool {
		$dependencies = $this->getDependencies();
		foreach ($dependencies as $dependency) {
			if (!$this->manager->is_module_installed($dependency)) {
				return true;
			}
		}
		return false;
	}

	// === Module Installation ===

	/**
	 * Installs the module.
	 * @return bool True if the module was installed, false otherwise.
	 */
	public function install(): bool
	{
		if (!$this->blockedByDependencies()) {
			//$this->manager->set_module_installed($this->module_name);
			$this->module_installed = true;
			if ($this->settingsComponent) {
				$this->settingsComponent->initializeSettings();
			}
		}
		return $this->module_installed;
	}
	
	/**
	 * Creates the module's database table if it doesn't exist.
	 * This method is deprecated and kept for backward compatibility.
	 * Tables should now be created via migrations.
	 * 
	 * @deprecated Tables should now be created via migrations
	 * @return void
	 */
	protected function createModuleTable(): void
	{
		// Tables are now created via migrations
		// This method is kept for backward compatibility
		$this->log('Module tables should now be created via migrations', 'NOTICE');
	}

	// === Module Data Management ===

	/**
	 * Retrieves the data for the module.
	 * @return array The data for the module.
	 */
	public function getModuleData(): array {
		return [
			'active'        => $this->module_active ?? false,
			'name'          => $this->getModuleName(),
			'title'         => $this->getTitle(),
			'description'   => $this->getDescription(),
			'version'       => $this->getVersion(),
			'settings'      => $this->getSettings(),
			'dependencies'  => $this->getDependencies(),
			'priority'      => $this->getPriority(),
			'class'         => get_class($this)
		];
	}

	// === Protected/Internal Helpers ===

	/**
	 * Creates the table for the module.
	 */
	public function getTableName(): string {
        return '';
    }

    /**
     * Uninstalls the module.
     */
    public function uninstall(): void {
        // check if table exists
        if ($this->dbManager->tableExists($this->getTableName()) ?? false) {
            $this->dbManager->queryRaw('DROP TABLE IF EXISTS ' . $this->getTableName());
        }
    }

    /**
     * Get the hooks component.
     * @return mixed
     */
    public function getHooksComponent(): mixed
    {
        return $this->hooksComponent;
    }

    /**
     * Get the UI component.
     * @return mixed
     */
    public function getUiComponent(): mixed
    {
        return $this->uiComponent;
    }

    /**
     * Get the API component.
     * @return mixed
     */
    public function getApiComponent(): mixed
    {
        return $this->apiComponent;
    }

    /**
     * Get the settings component.
     * @return ?BaseSubmoduleSettings
     */
    public function getSettingsComponent(): ?BaseSubmoduleSettings
    {
        return $this->settingsComponent;
    }
    
    /**
     * Get the SQL schema for creating the table.
     * This method is deprecated and should not be used anymore.
     * Tables should now be created via migrations.
     *
     * @deprecated Tables should now be created via migrations
     * @param string $tableName The name of the table.
     * @param string $charsetCollate The character set and collation for the table.
     * @return string The SQL query to create the table.
     */
    protected function getTableSchema(string $tableName, string $charsetCollate): string
    {
        // This method is now optional and should return an empty string by default
        // Child classes can override it for backward compatibility
        return '';
    }
}
