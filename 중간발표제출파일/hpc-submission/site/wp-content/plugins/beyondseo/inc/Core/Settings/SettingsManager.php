<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Settings;

if ( !defined('ABSPATH') ) {
    exit;
}

use RankingCoach\Inc\Core\PluginSettings;
use RankingCoach\Inc\Interfaces\SettingsManagerInterface;

/**
 * Class SettingsManager
 * @property object $rss
 * @property object $sitemap
 * @property object $plans
 * @property string $site_represents
 * @property bool $enable_breadcrumbs
 * @property object $breadcrumb_settings
 * @property string $top_toolbar_menu_name
 * @property bool $enable_robots_txt
 * @property bool $include_sitemap_in_robots
 * @property object $supported_languages
 * @property object $allowed_countries
 */
class SettingsManager implements SettingsManagerInterface {

    /** @var self|null Singleton instance of the class. */
	protected static ?self $instance = null;

    /** @var PluginSettings Plugin settings instance. */
    protected PluginSettings $settings;

    /** @var array<string> List of immutable setting keys that cannot be modified after initialization. */
    protected array $immutableSettings = [
        'separators'
    ];

    /**
	 * Private constructor to initialize settings.
	 */
	private function __construct() {
		$this->settings = PluginSettings::instance();
	}

	/**
	 * Retrieves the Singleton instance of this class.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Registers default options on plugin activation.
	 * Ensures that any missing defaults are set.
	 */
	public function registerDefaultSettings(): void {
		$saved_options = get_option($this->settings->get_option_key(), []);

		// Fetch default values from PluginSettings dynamically
		$default_values = $this->settings->get_all();

		// Merge defaults with saved options without overwriting existing settings
		foreach ($default_values as $key => $value) {
			if (!array_key_exists($key, $saved_options)) {
				$saved_options[$key] = $value;
			}
		}

		// Update the database only if changes are made
		update_option($this->settings->get_option_key(), $saved_options);

		// Load the merged settings into the PluginSettings instance
        // Assuming this method reloads data from the database
		$this->settings->load_settings();
	}

	/**
	 * Get all options from PluginSettings.
	 *
	 * @return array Associative array of all settings.
	 */
	public function get_options(): array {
		return $this->settings->get_all();
	}

	/**
	 * Get a specific option from PluginSettings.
	 * For immutable settings, always returns the default value from PluginSettings.
	 *
	 * @param string $option The key of the setting to retrieve.
	 * @param mixed|null $default The default value to return if the setting is not found.
	 * @return mixed The setting value or the default value.
	 */
	public function get_option(string $option, mixed $default = null, bool $returnAsObject = false): mixed {
		if ($this->isImmutableSetting($option)) {
			// For immutable settings, always return the default from PluginSettings
			$get = $this->settings->getDefault($option) ?? $default;
		} else {
			$get = $this->settings->get($option) ?? $default;
		}
		
        if ($returnAsObject) {
            return json_decode(json_encode($get), false);
        }
        return $get;
	}

	/**
	 * Update a specific option in PluginSettings.
	 * Prevents modification of immutable settings.
	 *
	 * @param string $key The key of the setting to update.
	 * @param mixed $value The value to set.
	 * @return void
	 * @throws \InvalidArgumentException When attempting to modify an immutable setting.
	 */
	public function update_option(string $key, mixed $value): void {
		if ($this->isImmutableSetting($key)) {
			return;
		}
		$this->settings->set($key, $value);
	}

    /**
     * Check if a specific option exists in PluginSettings.
     *
     * @param string $key
     * @return bool
     */
    public function has_option(string $key): bool {
        return $this->settings->get($key) !== null;
    }

	/**
	 * Reset all settings to their default values.
	 *
	 * @return void
	 */
	public function resetToDefaults(): void {
		$this->settings->reset_to_defaults();
	}

	/**
	 * Magic method to allow direct property access to settings.
	 * For immutable settings, always returns default values.
	 * Converts arrays to objects recursively for nested access.
	 *
	 * @param string $name The property name to access.
	 * @return mixed The setting value, converted to object if it's an array.
	 */
	public function __get(string $name): mixed {
		if ($this->isImmutableSetting($name)) {
			$value = $this->settings->getDefault($name);
		} else {
			$value = $this->settings->get($name);
		}
		
		if ($value === null) {
			return null;
		}
		
		return $this->convertArrayToObject($value);
	}

	/**
	 * Convert arrays to objects recursively while preserving other data types.
	 *
	 * @param mixed $data The data to convert.
	 * @return mixed The converted data.
	 */
	private function convertArrayToObject(mixed $data): mixed {
		if (is_array($data)) {
			$object = new \stdClass();
			foreach ($data as $key => $value) {
				$object->{$key} = $this->convertArrayToObject($value);
			}
			return $object;
		}
		
		return $data;
	}

	/**
	 * Magic method to check if a property exists.
	 *
	 * @param string $name The property name to check.
	 * @return bool True if the property exists, false otherwise.
	 */
	public function __isset(string $name): bool {
		return $this->settings->get($name) !== null;
	}

	/**
	 * Check if a setting key is immutable.
	 *
	 * @param string $key The setting key to check.
	 * @return bool True if the setting is immutable, false otherwise.
	 */
	protected function isImmutableSetting(string $key): bool {
		return in_array($key, $this->immutableSettings, true);
	}

	/**
	 * Get the list of immutable setting keys.
	 *
	 * @return array<string> Array of immutable setting keys.
	 */
	public function getImmutableSettings(): array {
		return $this->immutableSettings;
	}

	/**
	 * Check if a setting can be modified.
	 *
	 * @param string $key The setting key to check.
	 * @return bool True if the setting can be modified, false if immutable.
	 */
	public function canModifySetting(string $key): bool {
		return !$this->isImmutableSetting($key);
	}

	/**
	 * Synchronize new settings from WPSettings to wp_options without altering existing values.
	 * This method adds only new settings that don't exist in the database.
	 * Existing settings values are never modified.
	 *
	 * @return void
	 */
	public function syncNewSettingsFromDefaults(): void {
		$saved_options = get_option($this->settings->get_option_key(), []);
		$default_values = $this->settings->get_all();
		$changes_made = false;

		// Add only new settings that don't exist in saved options
		foreach ($default_values as $key => $default_value) {
			if (!array_key_exists($key, $saved_options)) {
				$saved_options[$key] = $default_value;
				$changes_made = true;
			}
		}

		// Update database only if new settings were added
		if ($changes_made) {
			update_option($this->settings->get_option_key(), $saved_options);
			// Reload settings to reflect the changes
			$this->settings->load_settings();
		}
	}

    /**
     * Load available variables for SEO templates.
     * This method populates the `variables` property with available WordPress variables.
     *
     * @return void
     */
    public function loadVariables(): void
    {
        $this->settings->load_variables();
    }
}
