<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Modules\ModuleBase;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;

/**
 * Class BaseSubmoduleSettings
 */
abstract class BaseSubmoduleSettings {

	use RcLoggerTrait;

	/** @var array|null Default settings configuration */
	protected ?array $defaultSettings;

	/** @var string The key to store settings in the WordPress options table */
	protected string $settingsKey;

	/** @var BaseModule $module The module instance */
	protected BaseModule $module;

	/**
	 * @param BaseModule $module
	 * @param array|null $params
	 */
	public function __construct(BaseModule $module,  ?array $params = [] ) {
		$this->defaultSettings = $params;
		$this->module = $module;
		$this->settingsKey = $module::getOptionKeySettings() ?? null;
	}

	/**
	 * Initializes settings
	 */
	abstract public function initializeSettings(): void;

	/**
	 * Initializes settings by saving defaults if no settings are found in the database.
	 */
	public function init(): void {
		if(!$this->settingsKey) {
			return;
		}
		$currentSettings = get_option( $this->settingsKey, [] );
		if ( ! empty( $this->defaultSettings ) && get_option( $this->settingsKey ) === false ) {
			foreach ( $this->defaultSettings as $setting ) {
				$key = $setting['key'];
				if ( ! isset( $currentSettings[ $key ] ) ) {
					$currentSettings[ $key ] = $setting['default'];
				}
			}
			update_option( $this->settingsKey, $currentSettings );
		}
	}

    /**
     * Uninstalls the settings from the database.
     * @return void
     */
    public function uninstall(): void
    {
        if(!$this->settingsKey) {
            return;
        }
        delete_option( $this->settingsKey );
    }

	/**
	 * Retrieves the value of a specific setting by key.
	 *
	 * @param string $key The key of the setting to retrieve.
	 *
	 * @return mixed|null The setting value, or null if not found.
	 */
	public function getSetting( string $key, mixed $default = null ): mixed {
		if(!$this->settingsKey) {
			return $default;
		}
		$settings = get_option( $this->settingsKey, $this->defaultSettings );

		return $settings[ $key ] ?? null;
	}

	/**
	 * Updates a specific setting by key and saves it to the database.
	 *
	 * @param string $key The key of the setting to update.
	 * @param mixed $value The value to set for the specified key.
	 *
	 * @return bool True if the update was successful, false otherwise.
	 */
	public function updateSetting( string $key, mixed $value ): bool {
		if(!$this->settingsKey) {
			return false;
		}
		$settings         = get_option( $this->settingsKey, [] );
		$settings[ $key ] = $value;

		return update_option( $this->settingsKey, $settings );
	}

	/**
	 * Retrieves the full settings array, including both stored values and configuration metadata.
	 *
	 * @return array The array of all settings with metadata.
	 */
	public function getAllSettingsWithMetadata(): array {
		$allSettings     = [];
		if(!$this->settingsKey) {
			return $allSettings;
		}
		$currentSettings = get_option( $this->settingsKey, $allSettings );

		foreach ( $this->defaultSettings as $setting ) {
			$key                 = $setting['key'];
			$allSettings[ $key ] = [
				'value'       => $currentSettings[ $key ] ?? $setting['default'],
				'type'        => $setting['type'],
				'description' => $setting['description'],
			];
		}

		return $allSettings;
	}

	/**
	 * Retrieves all settings for the LinkCounter module.
	 *
	 * @return array An associative array of all settings.
	 */
	public function getAllSettings(): array {
		if(!$this->settingsKey) {
			return $this->defaultSettings;
		}
		return get_option( $this->settingsKey, $this->defaultSettings );
	}

	/**
	 * Resets all settings to the default values.
	 *
	 * @return bool True if the reset was successful, false otherwise.
	 */
	public function resetSettings(): bool {
		if(!$this->settingsKey) {
			return false;
		}
		$defaults = array_column( $this->defaultSettings, 'default', 'key' );

		return update_option( $this->settingsKey, $defaults );
	}
}
