<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Modules;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Exception;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Core\MetaModulesManager;
use RankingCoach\Inc\Interfaces\MetaHeadBuilderInterface;
use RankingCoach\Inc\Modules\ModuleBase\BaseSubmoduleSettings;
use RankingCoach\Inc\Modules\ModuleBase\ModuleFactory;
use RankingCoach\Inc\Modules\ModuleBase\ModuleInterface;
use RankingCoach\Inc\Modules\ModuleLibrary\Analytics\AdvancedAnalytics;
use RankingCoach\Inc\Modules\ModuleLibrary\Analytics\CoreWebVitalsMonitor;
use RankingCoach\Inc\Modules\ModuleLibrary\Analytics\UserEngagementMetrics;
use RankingCoach\Inc\Modules\ModuleLibrary\Content\ContentAnalysis\ContentAnalysis;
use RankingCoach\Inc\Modules\ModuleLibrary\Content\ContentDuplicationChecker;
use RankingCoach\Inc\Modules\ModuleLibrary\Content\ContentScheduler;
use RankingCoach\Inc\Modules\ModuleLibrary\Content\TextOptimizer;
use RankingCoach\Inc\Modules\ModuleLibrary\Core\RankingCoachDashboard\RankingCoachDashboard;
use RankingCoach\Inc\Modules\ModuleLibrary\Keywords\KeywordRankTracker;
use RankingCoach\Inc\Modules\ModuleLibrary\Keywords\KeywordResearchTool;
use RankingCoach\Inc\Modules\ModuleLibrary\Keywords\SERPFeatureTracking;
use RankingCoach\Inc\Modules\ModuleLibrary\Links\BacklinkManager;
use RankingCoach\Inc\Modules\ModuleLibrary\Links\BrokenLinkChecker;
use RankingCoach\Inc\Modules\ModuleLibrary\Links\InternalLinkSuggestions;
use RankingCoach\Inc\Modules\ModuleLibrary\Links\LinkAnalyzer\LinkAnalyzer;
use RankingCoach\Inc\Modules\ModuleLibrary\Links\LinkCounter\LinkCounter;
use rankingCoach\inc\Modules\ModuleLibrary\Links\RedirectManager\RedirectManager;
use RankingCoach\Inc\Modules\ModuleLibrary\Local\LocalSEOEnhancements;
use RankingCoach\Inc\Modules\ModuleLibrary\Local\LocalSEOOptimizer;
use RankingCoach\Inc\Modules\ModuleLibrary\Media\ImageOptimizer;
use RankingCoach\Inc\Modules\ModuleLibrary\Media\VideoSEO;
use RankingCoach\Inc\Modules\ModuleLibrary\Performance\PageSpeed;
use RankingCoach\Inc\Modules\ModuleLibrary\Performance\PerformanceOptimizer;
use RankingCoach\Inc\Modules\ModuleLibrary\Schema\HreflangTagsInternational;
use RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\SchemaMarkup;
use RankingCoach\Inc\Modules\ModuleLibrary\Social\SocialMedia;
use RankingCoach\Inc\Modules\ModuleLibrary\Store\EcommerceSEOOptimizer;
use RankingCoach\Inc\Modules\ModuleLibrary\Technical\MetaTags\MetaTags;
use RankingCoach\Inc\Modules\ModuleLibrary\Technical\MobileSEOAnalyser;
use RankingCoach\Inc\Modules\ModuleLibrary\Technical\Sitemap;
use RankingCoach\Inc\Modules\ModuleLibrary\Technical\VoiceSearchOptimization;
use RankingCoach\Inc\Modules\ModuleLibrary\Tools\CompetitorAnalysis;
use RankingCoach\Inc\Modules\ModuleLibrary\Tools\SEOAudit;
use RankingCoach\Inc\Modules\ModuleLibrary\Tools\WebmasterTools;
use RankingCoach\Inc\Modules\ModuleLibrary\WordPress\SpecializedPluginIntegrations;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionException;
use RuntimeException;

/**
 * Class ModuleManager
 *
 * @method WebmasterTools webmasterTools() Search Console Integration - Integrates with Google Search Console and Bing.
 * @method SEOAudit seoAudit() SEO Audit Tool - Performs comprehensive SEO audits.
 * @method CompetitorAnalysis competitorAnalysis() Competitor SEO Analysis - Analyzes competitor websites.
 * @method RankingCoachDashboard rankingcoachDashboard() RankingCoach Dashboard - Central module for SEO reporting.
 * @method SpecializedPluginIntegrations specializedPluginIntegrations() Specialized Plugin Integrations - Integrates with non-SEO plugins.
 * @method ContentAnalysis contentAnalysis() SEO Content Analysis - Analyzes content for SEO performance.
 * @method ContentDuplicationChecker contentDuplicationChecker() Content Duplication Checker - Identifies duplicate content issues.
 * @method ContentScheduler contentScheduler() Content Update Scheduler - Schedules content updates and revisions.
 * @method TextOptimizer textOptimizer() Content Optimizer - Optimizes content for search engines.
 * @method LocalSEOOptimizer localSeoOptimizer() Local SEO Optimizer - Optimizes for local search visibility.
 * @method LocalSEOEnhancements localSeoGmb() Local SEO Enhancements - Google My Business integration.
 * @method HreflangTagsInternational internationalSeo() International SEO - Manages hreflang tags.
 * @method SchemaMarkup schemaMarkup() Structured Data Markup - Adds schema.org markup.
 * @method SocialMedia socialMedia() Social Media Integration - Enhances social media sharing.
 * @method BacklinkManager backlinkManager() Backlink Manager - Tracks and monitors backlinks.
 * @method RedirectManager redirectManager() Redirect Manager - Manages 301 and 302 redirects.
 * @method BrokenLinkChecker brokenLinkChecker() Broken Link Checker - Scans for broken links.
 * @method InternalLinkSuggestions internalLinkSuggestions() Internal Link Suggestion Tool - Suggests relevant internal links.
 * @method LinkCounter linkCounter() Link Counter - Counts internal and external links.
 * @method LinkAnalyzer linkAnalyzer() Link Analyzer - Analyzes post links.
 * @method PageSpeed pageSpeed() Page Speed Optimizer - Improves page loading speed.
 * @method PerformanceOptimizer performanceOptimizer() Website Performance Optimizer - Implements performance best practices.
 * @method KeywordRankTracker keywordRankTracker() Keyword Rank Tracker - Tracks keyword ranking performance.
 * @method KeywordResearchTool keywordResearchTool() Keyword Research Tool - Discovers keyword opportunities.
 * @method SERPFeatureTracking serpFeatureTracking() SERP Feature Tracking - Tracks SERP feature appearances.
 * @method Sitemap sitemap() XML Sitemap Generator - Creates and submits XML sitemaps.
 * @method MobileSEOAnalyser mobileSeoAnalyzer() Mobile SEO Analyzer - Ensures mobile optimization.
 * @method VoiceSearchOptimization voiceSearchOptimization() Voice Search Optimization - Optimizes for voice search.
 * @method MetaTags metaTags() Meta Tags Manager - Manages and optimizes meta tags.
 * @method UserEngagementMetrics userEngagementMetrics() User Engagement Metrics Tracker - Tracks user engagement.
 * @method AdvancedAnalytics advancedAnalytics() Advanced SEO Analytics - Provides in-depth performance data.
 * @method CoreWebVitalsMonitor coreWebVitalsMonitor() Core Web Vitals Monitor - Monitors web vitals metrics.
 * @method EcommerceSEOOptimizer ecommerceSeoOptimizer() E-commerce SEO Optimizer - Specialized for e-commerce sites.
 * @method VideoSEO videoSeo() Video SEO Optimizer - Enhances video content visibility.
 * @method ImageOptimizer imageOptimizer() Image Optimizer - Optimizes images for better performance.
 */
class ModuleManager {

	use RcLoggerTrait;

	/**
	/**
	 * The list of modules to be installed.
	 * @var array
	 */
	protected array $modules = [];

	/**
	 * The list of installed modules.
	 * @var array
	 */
	public array $installedModules = [];

	/**
	 * The singleton instance of the class.
	 * @var ModuleManager|null
	 */
	private static ?ModuleManager $instance = null;

	/**
	 * Module factory instance.
	 */
	private ModuleFactory $moduleFactory;

	/**
	 * The module libraries directory.
	 * @var string
	 */
	private string $modulesDirectory;

	/**
	 * Path to the module metadata file.
	 * @var string
	 */
	protected string $metadataFilePath;

    /**
     * Provides a singleton instance of the class.
     *
     * @param ModuleFactory|null $moduleFactory
     * @param string $moduleDirectory
     * @return ModuleManager The singleton instance of the class.
     * @throws Exception
     */
	public static function instance(?ModuleFactory $moduleFactory = null, string $moduleDirectory = RANKINGCOACH_PLUGIN_MODULES_LIBRARY_DIR): ModuleManager {
		if (self::$instance === null) {
            $moduleFactory = $moduleFactory ?? new ModuleFactory();
			self::$instance = new self($moduleFactory, $moduleDirectory);
		}
		return self::$instance;
	}

	/**
	 * ModuleManager constructor.
     *
	 * @throws Exception
	 */
	public function __construct(ModuleFactory $moduleFactory, string $modulesDirectory) {
		$this->moduleFactory = $moduleFactory;
		$this->modulesDirectory = $modulesDirectory;
		$this->metadataFilePath = $modulesDirectory . DIRECTORY_SEPARATOR . 'modules.json';
	}

    /**
     * Magic method to handle calls to the class that are not defined.
     *
     * @param string $name The name of the method called.
     * @param array $arguments The arguments passed to the method.
     * @return ModuleInterface|null The result of the method call.
     */
    public function __call(string $name, array $arguments = []) {
        // I want if someone calls a method that does match with a module name, it should load that module
        // and return the instance of that module
        return $this->get_module($name) ?? null;
    }

	/**
	 * Generates a single checksum value for all relevant module files in a given directory.
     *
	 * @param string $directory The root directory path to scan for module files.
	 * @return string A single MD5 checksum representing the combined state of all module files.
	 */
	protected function generate_checksum(string $directory): string {
		$filePaths = [];
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
		foreach ($iterator as $file) {
			if ($file->isFile() && $file->getExtension() === 'php') {
				$filePaths[] = $file->getPathname();
			}
		}
		sort($filePaths);
		$checksum = '';
		foreach ($filePaths as $path) {
			$checksum .= md5_file($path);
		}
		return md5($checksum);
	}

	// get module get_module by name

	/**
     * @todo add a better description or delete this comment
     * Returns an instance of ModuleInterface by the module name
     *
	 * @param string $moduleName
	 * @return ModuleInterface|null
	 */
	public function get_module(string $moduleName): ?ModuleInterface {
		foreach ($this->modules as $module) {
			if ($module->getModuleName() === $moduleName) {
				return $module;
			}
		}
		return null;
	}

	/**
	 * Loads all modules from the ModuleLibrary directory or cache, ensuring consistency with the WordPress database options.
     *
	 * @param string|null $context Optional context for module loading, which can influence conditional loading behaviors.
	 * @return void
	 * @throws Exception If a module fails to load from the filesystem or cache, an exception is thrown to capture the error.
	 */
	public function load_modules(?string $context = null): void {
		$this->modules = [];

		// TODO implement loading modules from metadata file
		//$metadata = $this->load_modules_metadata();

		$cachedData = get_option(BaseConstants::OPTION_LOADED_MODULES, []);
		$currentChecksum = $this->generate_checksum($this->modulesDirectory);

		// Verify that the cached data is consistent with the database option for installed modules
		if (!empty($cachedData) && isset($cachedData['checksum']) && $cachedData['checksum'] === $currentChecksum) {
			$installedModulesInDb = get_option(BaseConstants::OPTION_INSTALLED_MODULES, []);
			// Check if cached installed modules match the database installed modules
			if ($this->are_installed_modules_consistent($cachedData['modules'], $installedModulesInDb)) {
				$this->load_modules_from_cache($cachedData['modules'], $context);
			} else {
				// Inconsistency found, force reload from filesystem
				$this->log('Checksum mismatch or cache miss. Reloading modules from filesystem.', 'DEBUG');
				$this->load_modules_from_filesystem();
			}
		}
		//$this->modules = [];

		// If no valid cached modules were loaded, load from the filesystem
		if (empty($this->modules)) {
			$this->log('Checksum mismatch detected or load modules happens for the first time. Reloading modules from filesystem.', 'DEBUG');
			$this->load_modules_from_filesystem();
		}

		// Save loaded module data to the database for future use
		$this->save_loaded_modules();
	}

	/**
	 * Checks if the cached installed modules are consistent with the installed modules stored in the database.
     *
	 * @param array $cachedModules       An array of cached modules, where each element includes module data such as name and settings.
	 * @param array $installedModulesInDb   The installed modules retrieved from the WordPress database.
	 * @return bool True if the cached installed modules are consistent with the database installed modules; otherwise, false.
	 */
	private function are_installed_modules_consistent(array $cachedModules, array $installedModulesInDb): bool {
		// Extract the names of installed modules from cached modules
		$cachedInstalledModules = array_column($cachedModules, 'name');

		// Compare cached installed modules with the database installed modules
		return
			empty(array_diff($cachedInstalledModules, array_keys($installedModulesInDb))) &&
            empty(array_diff(array_keys($installedModulesInDb), $cachedInstalledModules));
	}

	/**
	 * Loads modules from cached settings, validating each module class and ensuring it meets the required interface.
     *
	 * @param array       $loadedModules Array of module data retrieved from the cache. Each item contains metadata.
	 *                                   such as the module class name and other settings.
	 * @param string|null $context       Optional context for module loading that can influence conditional behaviors
	 *                                   in loading specific modules based on context.
	 * @return void
	 * @throws RuntimeException If a module fails to load or instantiate properly, an exception is logged and thrown
	 *                          to indicate a critical failure in the loading process.
	 */
	protected function load_modules_from_cache(array $loadedModules, ?string $context = null): void {
		foreach ($loadedModules as $moduleData) {
			$className = $moduleData['class'];

			try {
				if (!class_exists($className)) {
					$this->log_and_notify_admin("Failed to load module: Class $className does not exist.");
					continue;
				}

				$module = $this->create_module_instance($className);
				if (!$module) {
					$this->log_and_notify_admin("Failed to create an instance of module class: $className");
					continue;
				}

				$this->modules[]                                  = $module;
				$this->installedModules[$module->getModuleName()] = $module->install();
			} catch ( Exception $e) {
				// Log the error and notify admin if in admin context
				$this->log_and_notify_admin($e->getMessage());
				continue;
			}
		}
	}

	/**
	 * Loads the modules metadata from a JSON file, parsing it to an associative array for module configuration.
     *
	 * @return array The parsed metadata as an associative array, with each module’s data organized for easy access.
	 * @throws Exception If the metadata file is not found, cannot be read, or contains invalid JSON, an exception is thrown
	 *                   with an appropriate error message to aid in debugging and error resolution.
	 */
	protected function get_modules_from_json(): array {
		if (!file_exists($this->metadataFilePath)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new Exception( 'Modules metadata file not found: ' . $this->metadataFilePath);
		}

		$metadataContent = file_get_contents($this->metadataFilePath);
		if ($metadataContent === false) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new Exception( 'Failed to read modules metadata file: ' . $this->metadataFilePath);
		}

		$metadata = json_decode($metadataContent);
		if (json_last_error() !== JSON_ERROR_NONE) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new Exception( 'Failed to parse modules metadata file: ' . json_last_error_msg());
		}

		return $metadata->modules;
	}


	/**
	 * Creates an instance of a module class, verifying that it implements `ModuleInterface`.
	 *
	 * @param string $className The fully qualified class name to instantiate, typically derived from the module directory structure.
	 *
	 * @return ModuleInterface|null The created module instance if successful, or null if instantiation fails or the class does
	 *                              not meet the required interface.
	 * @throws ReflectionException
	 */
	protected function create_module_instance(string $className): ?ModuleInterface {
		if (class_exists($className)) {
			return $this->moduleFactory->create($className, $this);
		}
		$this->log("Class $className does not exist.", 'DEBUG');
		return null;
	}

	/**
	 * Loads a single module from the filesystem based on the full path to the module class file.
	 *
	 * @param string $modulePath The full path to the module class file, including the class name.
	 *
	 * @return ModuleInterface|null The loaded module instance if successful, or null if the module fails to load or instantiate.
	 */
	protected function load_module_from_filesystem(string $modulePath, string $class): ?ModuleInterface {
		$className = RANKINGCOACH_PLUGIN_MODULES_DIR . $modulePath;

		try {
			if (!file_exists($className)) {
				$this->log_and_notify_admin("Failed to load module: File $class does not exist.");
				return null;
			}

			$required = require_once $className;
			if (!$required) {
				$this->log_and_notify_admin("Failed to load module: File $class does not exist.");
				return null;
			}

			// Instantiate the module
			$module = new $class($this);

			if(!class_exists($class)) {
				$this->log_and_notify_admin("Failed to load module: Class $class does not exist.");
				return null;
			}

			if(!($module instanceof ModuleInterface)) {
				$this->log_and_notify_admin("Failed to load module: File $className does not instance of ModuleInterface.");
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

			return $module;
		} catch ( Exception $e) {
			// Log the error and notify admin if in admin context
			$this->log_and_notify_admin($e->getMessage());
		}
		return null;
	}

	/**
	 * Loads modules directly from the filesystem and caches them in the WordPress database.
     *
	 * @return void
	 * @throws RuntimeException If an error occurs while creating an instance of a module class, an exception is thrown with
	 *                          the relevant message. Errors during module instantiation are logged.
	 */
	protected function load_modules_from_filesystem(): void {
		// Step 1: Load module files
		$moduleFiles = $this->load_module_files($this->modulesDirectory);

		// Step 2: Instantiate modules
		foreach ($moduleFiles as $className) {
			try {
				if(!class_exists($className)) {
					$this->log_and_notify_admin("Failed to load module: Class $className does not exist.");
					continue;
				}

				$module = $this->create_module_instance( $className );
				if(!$module) {
					$this->log_and_notify_admin("Failed to create an instance of module class: $className");
					continue;
				}

				$this->modules[] = $module;
			} catch ( Exception $e) {
				// Log the error and notify admin if in admin context
				$this->log_and_notify_admin($e->getMessage());
				continue;
			}
		}

		// Sort the modules by priority in descending order
		usort($this->modules, function($a, $b) {
			$priorityComparison = $b->getPriority() <=> $a->getPriority();
			return $priorityComparison !== 0 ? $priorityComparison : strcmp($a->getModuleName(), $b->getModuleName());
		});

		// Save loaded module data to the database for future use
		$this->save_loaded_modules();
	}

	/**
	 * Loads all PHP files within a specified directory, filtering them to ensure only valid module classes are loaded.
     *
	 * @param string $directory The directory path to scan for PHP files.
	 * @return array An array of valid module class names.
	 * @throws RuntimeException Logs and throws an exception if a class does not implement ModuleInterface or is missing.
	 */
	protected function load_module_files(string $directory): array {
		$moduleFiles = [];

		// Use RecursiveDirectoryIterator to handle files within subdirectories
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
		foreach ($iterator as $file) {
			// Check if the file is a PHP file and not a directory
			if ($file->isFile() && $file->getExtension() === 'php') {
				$relativePath = str_replace($this->modulesDirectory, '', $file->getPathname());
				$className = "RankingCoach\\Inc\\Modules\\ModuleLibrary\\" . str_replace('/', '\\', substr($relativePath, 0, -4));

				// Check if the class exists and implements ModuleInterface
				if (class_exists($className) && is_subclass_of($className, ModuleInterface::class)) {
					$moduleFiles[] = $className;
				}
			}
		}

		return $moduleFiles;
	}

	/**
	 * Checks if all dependencies of a given module are met, allowing the module to be loaded or installed.
     *
	 * @param ModuleInterface $module The module to check for dependency satisfaction, implementing `ModuleInterface`.
	 * @return bool True if all dependencies are met and the module can proceed; false if dependencies are missing.
	 */
	protected function dependencies_are_met(ModuleInterface $module): bool {
		return !$module->blockedByDependencies();
	}

	/**
	 * Retrieves the settings for a specified module, verifying consistency with the cached data.
     *
	 * @param string $moduleName The unique name of the module for which to retrieve settings.
	 * @return array The settings array for the specified module if found in the cache and valid; otherwise, an empty array.
	 */
	public function get_settings_for_module(string $moduleName): array {
		$cachedData = get_option(BaseConstants::OPTION_LOADED_MODULES, []);
		$currentChecksum = $this->generate_checksum($this->modulesDirectory);

		if (!empty($cachedData) && isset($cachedData['checksum']) && $cachedData['checksum'] === $currentChecksum) {
			foreach ($cachedData['modules'] as $moduleData) {
				if ($moduleData['name'] === $moduleName) {
					return $moduleData['settings'] ?? [];
				}
			}
		}
		return [];
	}

	/**
	 * Installs all registered modules, marking them as installed within the WordPress database.
     *
	 * @return ModuleManager
	 * @throws Exception
	 */
	public function initialize(): self {

		// Load modules using the provided factory and directory.
		$this->load_modules();

		// Install each module and save the installed state.
		/** @var ModuleInterface $module */
		foreach ($this->modules as $module) {
			$this->installedModules[$module->getModuleName()] = $module->install();
		}

		// Save installed modules
		$this->save_installed_modules();

        return $this;
	}

	/**
	 * Executes all registered modules, initializing them by registering their necessary hooks.
     *
	 * @return void
	 * @throws Exception If an error occurs while loading or initializing a module, an exception is thrown to halt
	 *                   the process and signal a critical failure in module registration.
	 */
	public function run_modules(): void {
		$this->load_modules();
		/** @var ModuleInterface $module */
		foreach ($this->modules as $module) {
			$module->initializeModule();
		}
	}

	/**
	 * Saves the current module configuration and installed module data to the WordPress database for future use.
     *
	 * @return void
	 */
	protected function save_loaded_modules(): void {
		$moduleData = [];
		/** @var ModuleInterface $module */
		foreach ($this->modules as $module) {
			$moduleData[] = $module->getModuleData();
		}

		$checksum = $this->generate_checksum($this->modulesDirectory);
		update_option(BaseConstants::OPTION_LOADED_MODULES, [
			'checksum' => $checksum,
			'modules' => $moduleData,
		], 'off');
	}

	/**
	 * Checks if a specified module is currently installed.
     *
	 * @param string $moduleName The unique name of the module to check for installation status.
	 * @return bool True if the module is installed, false otherwise or not found in the installed modules list.
	 */
	public function is_module_installed( string $moduleName ): bool {
		$this->load_installed_modules();
		return ( $this->installedModules[$moduleName] ?? false) === true;
	}

	/**
	 * Sets a specified module as installed by updating the `$installedModules` property.
     *
	 * @param string $moduleName The unique name of the module to set as installed.
	 * @return void
	 */
	public function set_module_installed( string $moduleName ): void {
		$this->load_installed_modules();
		$this->installedModules[$moduleName] = true;
		$this->save_installed_modules();
	}

	/**
	 * Loads the installed modules from the WordPress options into the `$installedModules` property.
	 *
	 * @return void
	 */
	protected function load_installed_modules(): void {
		$this->installedModules = get_option(BaseConstants::OPTION_INSTALLED_MODULES, []);
	}

	/**
	 * Saves the current state of `$installedModules` to the WordPress options.
	 *
	 * @return void
	 */
	protected function save_installed_modules(): void {
		update_option(BaseConstants::OPTION_INSTALLED_MODULES, $this->installedModules);
	}

	/**
	 * Generates a list of all module names currently loaded.
	 * @return array
	 * @throws Exception
	 */
	public function get_modules_names(): array {
		$modules = [];
		$cachedData = get_option(BaseConstants::OPTION_LOADED_MODULES, []);

		if (!empty($cachedData) ) {
			foreach ($cachedData['modules'] as $moduleData) {
				$modules[] = $moduleData['name'];
			}
		}
		return $modules;
	}

	/**
	 * Retrieves data from all loaded modules by calling a standardized method on each module.
	 *
	 * @return array An associative array where the key is the module name, and the value is the module's data.
	 * @throws Exception
	 */
	public function get_modules_data(): array {
		$this->load_modules();
		$modulesData = [];

		// Iterate over all loaded modules
		foreach ($this->modules as $module) {
			try {

				$modulesData[$module->getModuleName()] = $module->getModuleData();
			} catch (Exception $e) {
				// Handle errors gracefully, log, and continue
				$this->log("Error retrieving data for module {$module->getModuleName()}: " . $e->getMessage(), 'ERROR');
				$modulesData[$module->getModuleName()] = [
					'error' => 'Failed to retrieve data for this module',
				];
			}
		}

		return $modulesData;
	}

	/**
	 * Retrieves the data for a single module by name.
	 *
	 * @param string $moduleName The unique name of the module to retrieve data for.
	 * @return array|null The module data if found, or null if the module is not loaded or an error occurs.
	 * @throws Exception
	 */
	protected function find_module(string $moduleName): ?object
	{
		$modules = $this->get_modules_from_json();
		foreach ($modules as $module) {
			if ($module->name === $moduleName) {
				return $module;
			}
		}
		return null;
	}

	/**
	 * Get one module data by instance
	 * @throws Exception
	 */
	public function loadModule(string $moduleName, bool $returnInfo = false): mixed {
		$module = $this->find_module($moduleName);
		if (!$module) {
			return null;
		}

		$loadedModule = $this->load_module_from_filesystem($module->file, $module->class);
		if (!$loadedModule) {
			return null;
		}

		return $returnInfo ? $loadedModule->getModuleData() : $loadedModule;
	}

    /**
     * Uninstalls all modules.
     * @throws Exception
     */
    public function uninstall(): void {
        $this->load_modules();
        foreach ($this->modules as $module) {

            $module->uninstall();

            if($module->settingsComponent instanceof BaseSubmoduleSettings && method_exists($module->settingsComponent, 'uninstall'))
            {
                $module->settingsComponent->uninstall();
            }
        }
    }
}
