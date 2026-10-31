<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core;

if ( !defined('ABSPATH') ) {
    exit;
}

use RankingCoach\Inc\Traits\SingletonTrait;
use JsonSerializable;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\DB\DatabaseManager;
use RankingCoach\Inc\Core\Helpers\CoreHelper;
use RankingCoach\Inc\Interfaces\PluginConfigurationInterface;
use Throwable;

/**
 * Class Configuration
 */
class PluginConfiguration implements PluginConfigurationInterface, JsonSerializable
{
    use SingletonTrait;
    use RcLoggerTrait;

    private ?array $pluginData = null;

    /**
     * Returns the plugin data.
     *
     * @return array
     */
    public function getPluginData(): array {
        if ($this->pluginData === null) {
            if (!function_exists('get_plugin_data')) {
                require_once(ABSPATH . 'wp-admin/includes/plugin.php');
            }
            $this->pluginData = get_plugin_data(RANKINGCOACH_FILE, false, false);
        }

        return $this->pluginData;
    }
    /**
     * Returns the plugin file.
     *
     * @return string
     */
    public function getPluginFile(): string {
        return RANKINGCOACH_FILE;
    }

	/**
	 * Returns the plugin version.
	 *
	 * @return string
	 */
	public function getPluginVersion(): string {
		return RANKINGCOACH_VERSION;
	}

	/**
	 * Returns the plugin name.
	 *
	 * @return string
	 */
	public function getPluginName(): string {
		return RANKINGCOACH_BRAND_NAME;
	}

	/**
	 * Returns the plugin basename.
	 *
	 * @return string
	 */
	public function getPluginBasename(): string {
		return RANKINGCOACH_PLUGIN_BASENAME;
	}

	/**
	 * Returns the plugin directory.
	 *
	 * @return string
	 */
	public function getPluginDir(): string {
		return RANKINGCOACH_PLUGIN_DIR;
	}

	/**
	 * Returns the plugin URL.
	 *
	 * @return string
	 */
	public function getPluginUrl(): string {
		return RANKINGCOACH_PLUGIN_URL;
	}

	/**
	 * Returns the plugin environment.
	 *
	 * @return string
	 */
	public function getPluginEnvironment(): string {
		return RANKINGCOACH_ENVIRONMENT;
	}

	/**
	 * Returns the plugin namespace.
	 *
	 * @return string
	 */
	public function getPluginNamespace(): string {
		return RANKINGCOACH_NAMESPACE;
	}

	/**
	 * Returns the configuration as an array.
	 *
	 * @return array
	 */
	public function jsonSerialize(): array {
		return [
			'plugin_file' => $this->getPluginFile(),
			'plugin_version' => $this->getPluginVersion(),
			'plugin_name' => $this->getPluginName(),
			'plugin_basename' => $this->getPluginBasename(),
			'plugin_dir' => $this->getPluginDir(),
			'plugin_url' => $this->getPluginUrl(),
			'plugin_environment' => $this->getPluginEnvironment(),
			'plugin_namespace' => $this->getPluginNamespace(),
		];
	}

    /**
     * Remove all options with 'rankingcoach_' prefix and related transients
     *
     * @return static
     */
    public function removeOptions(): static
    {
        $optionNames = BaseConstants::getOptionNames();
        $optionNames = CoreHelper::filterExcludedOptions($optionNames);
        foreach ($optionNames as $optionName) {
            delete_option($optionName);
        }

        // Performance: Clear relevant caches
        $this->clearRelatedCaches();

        // Remove options with start with 'rankingcoach_', 'rc_', 'bseo_' prefix
        $this->removePrefixedOptions();

        // Remove transients with specified patterns: _rankingcoach_,  _rc_, _bseo_
        $this->removeTransients();

        return $this;
    }

    /**
     * Consolidates logic to delete prefixed keys from a specific table, excluding specific keys.
     *
     * @param string $tableName The table name ('options' or 'sitemeta').
     * @param string $columnName The column name ('option_name' or 'meta_key').
     * @param array $patterns LIKE patterns (e.g. ['rc_%', 'bseo_%']).
     * @param array $excludedKeys Keys to NEVER delete.
     * @return void
     */
    private function deleteDataFromTable(string $tableName, string $columnName, array $patterns, array $excludedKeys = []): void
    {
        try {
            $dbManager = DatabaseManager::getInstance();
            $query = $dbManager->table($tableName)->select($columnName);

            $query->whereOr(function ($q) use ($columnName, $patterns) {
                foreach ($patterns as $pattern) {
                    $q->where($columnName, $pattern, 'LIKE');
                }
            });

            if (!empty($excludedKeys)) {
                $query->whereNotIn($columnName, $excludedKeys);
            }

            $rows = $query->get();

            if (!empty($rows)) {
                $values = [];
                foreach ($rows as $row) {
                    $values[] = is_object($row) ? $row->$columnName : $row[$columnName];
                }

                $dbManager->table($tableName)
                    ->delete()
                    ->whereIn($columnName, $values)
                    ->get();

                $cacheGroup = ($tableName === 'sitemeta' || $tableName === 'site-options') ? 'site-options' : 'options';
                wp_cache_delete_multiple($values, $cacheGroup);
            }
        } catch (Throwable $e) {
            $this->log("Error deleting data from {$tableName}: " . $e->getMessage(), 'ERROR');
        }
    }

    /**
     * Remove all transients with rankingcoach patterns
     *
     * @return void
     */
    private function removeTransients(): void
    {
        $prefixes = CoreHelper::getOptionPrefixes();
        $excludedKeys = CoreHelper::getExcludedOptionKeys();
        
        $patterns = [];
        foreach ($prefixes as $prefix) {
            $patterns[] = '_transient_' . $prefix . '%';
            $patterns[] = '_transient_timeout_' . $prefix . '%';
        }

        $excludedTransients = [];
        foreach ($excludedKeys as $key) {
            $excludedTransients[] = '_transient_' . $key;
            $excludedTransients[] = '_transient_timeout_' . $key;
        }

        $this->deleteDataFromTable('options', 'option_name', $patterns, $excludedTransients);

        if (is_multisite()) {
            $sitePatterns = [];
            foreach ($prefixes as $prefix) {
                $sitePatterns[] = '_site_transient_' . $prefix . '%';
                $sitePatterns[] = '_site_transient_timeout_' . $prefix . '%';
            }

            $excludedSiteTransients = [];
            foreach ($excludedKeys as $key) {
                $excludedSiteTransients[] = '_site_transient_' . $key;
                $excludedSiteTransients[] = '_site_transient_timeout_' . $key;
            }

            $this->deleteDataFromTable('sitemeta', 'meta_key', $sitePatterns, $excludedSiteTransients);
        }

        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('transient');
            wp_cache_flush_group('site-transient');
        }
    }

    /**
     * Remove all WordPress options with specified prefixes using secure database operations
     * Implements WordPress security best practices and optimized database queries
     *
     * @return void
     */
    private function removePrefixedOptions(): void
    {
        try {
            $dbManager = DatabaseManager::getInstance();
            $prefixes = CoreHelper::getOptionPrefixes();
            $optionPrefixes = array_map(fn($p) => $p . '%', $prefixes);
            $excludedKeys = CoreHelper::getExcludedOptionKeys();

            if (!current_user_can('manage_options')) {
                return;
            }

            $dbManager->beginTransaction();

            $this->deleteDataFromTable('options', 'option_name', $optionPrefixes, $excludedKeys);

            if (is_multisite()) {
                $this->removeMultisitePrefixedOptions($optionPrefixes, $excludedKeys);
            }

            $dbManager->commit();
            $this->log('Successfully removed all prefixed options during plugin cleanup');

        } catch (Throwable $e) {
            try {
                DatabaseManager::getInstance()->rollback();
            } catch (Throwable) {
            }
            $this->log('Critical error during option cleanup: ' . $e->getMessage());
        }
    }

    /**
     * Remove prefixed options from multisite network tables
     *
     * @param array $optionPrefixes Array of option prefixes to remove
     * @param array $excludedKeys Array of option keys to exclude
     * @return void
     */
    private function removeMultisitePrefixedOptions(array $optionPrefixes, array $excludedKeys = []): void
    {
        if (!current_user_can('manage_network_options')) {
            return;
        }

        // Clean network-wide meta
        $this->deleteDataFromTable('sitemeta', 'meta_key', $optionPrefixes, $excludedKeys);

        // Clean each site's options
        $sites = get_sites(['number' => 0]);
        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);
            $this->deleteDataFromTable('options', 'option_name', $optionPrefixes, $excludedKeys);
            restore_current_blog();
        }
    }

    /**
     * Clear WordPress caches related to options and plugin data
     *
     * @return void
     */
    private function clearRelatedCaches(): void
    {
        try {
            if (function_exists('wp_cache_flush_group')) {
                wp_cache_flush_group('options');
                wp_cache_flush_group('site-options');
                wp_cache_flush_group('transient');
                wp_cache_flush_group('site-transient');
                wp_cache_flush_group('posts');
                wp_cache_flush_group('terms');
                wp_cache_flush_group('users');
                wp_cache_flush_group('user_meta');
                wp_cache_flush_group('post_meta');
                wp_cache_flush_group('term_meta');
            }

            if (function_exists('opcache_reset') && opcache_get_status(false)['opcache_enabled']) {
                opcache_reset();
            }

            flush_rewrite_rules(false);

            $dbManager = DatabaseManager::getInstance();
            if (method_exists($dbManager->db()->db, 'flush')) {
                $dbManager->db()->db->flush();
            }

            $this->log('Cache clearing completed successfully');
        } catch (Throwable $e) {
            $this->log('Error during cache cleanup: ' . $e->getMessage());
        }
    }
}
