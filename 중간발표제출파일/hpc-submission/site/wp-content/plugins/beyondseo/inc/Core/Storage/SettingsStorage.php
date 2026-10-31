<?php

declare(strict_types=1);

namespace RankingCoach\Inc\Core\Storage;

use RankingCoach\Inc\Core\Base\BaseConstants;
use RuntimeException;
use Throwable;

if (!defined('ABSPATH')) {
    exit;
}

final class SettingsStorage
{
    public function read(): array
    {
        global $wpdb;
        $rows = $this->fetchRows($wpdb->prepare(
            "SELECT option_id, CASE WHEN OCTET_LENGTH(option_value) <= 1048576 THEN option_value ELSE NULL END AS option_value, autoload FROM `{$wpdb->options}` WHERE option_name = %s LIMIT 2",
            BaseConstants::OPTION_PLUGIN_SETTINGS
        ));
        if (count($rows) > 1) {
            throw new RuntimeException('ambiguous_destination_option');
        }
        $value = [];
        if ($rows !== []) {
            $raw = $rows[0]['option_value'];
            if (!is_string($raw) || !is_serialized($raw)) {
                throw new RuntimeException('malformed_destination_settings');
            }
            $value = @unserialize($raw, ['allowed_classes' => false, 'max_depth' => 17]);
            if (!is_array($value) || !$this->isSafeValue($value)) {
                throw new RuntimeException('malformed_destination_settings');
            }
        }
        return ['rows' => $rows, 'value' => $value];
    }

    public function merge(array $current, array $paths): array
    {
        $result = $current;
        $keys = array_keys($paths);
        sort($keys, SORT_STRING);
        foreach ($keys as $index => $path) {
            if (!is_string($path) || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]*(?:\.[a-zA-Z][a-zA-Z0-9_]*)*$/D', $path)
                || !$this->isSafeValue($paths[$path])) {
                throw new RuntimeException('invalid_settings_patch');
            }
            foreach (array_slice($keys, 0, $index) as $previous) {
                if (str_starts_with($path, $previous . '.')) {
                    throw new RuntimeException('overlapping_settings_patch');
                }
            }
            $parts = explode('.', $path);
            $leaf = array_pop($parts);
            $node = &$result;
            foreach ($parts as $part) {
                if (array_key_exists($part, $node) && !is_array($node[$part])) {
                    throw new RuntimeException('malformed_destination_settings_path');
                }
                if (!array_key_exists($part, $node)) {
                    $node[$part] = [];
                }
                $node = &$node[$part];
            }
            $node[$leaf] = $paths[$path];
            unset($node);
        }
        return $result;
    }

    public function save(array $expected, array $value, bool $deferNotifications = false): ?callable
    {
        global $wpdb;
        if (!isset($expected['rows'], $expected['value']) || !is_array($expected['rows'])
            || count($expected['rows']) > 1 || !$this->isSafeValue($value)) {
            throw new RuntimeException('invalid_settings_snapshot');
        }
        $value = sanitize_option(BaseConstants::OPTION_PLUGIN_SETTINGS, $value);
        $value = apply_filters('pre_update_option_' . BaseConstants::OPTION_PLUGIN_SETTINGS, $value, $expected['value'], BaseConstants::OPTION_PLUGIN_SETTINGS);
        $value = apply_filters('pre_update_option', $value, BaseConstants::OPTION_PLUGIN_SETTINGS, $expected['value']);
        if (!is_array($value) || !$this->isSafeValue($value)) {
            throw new RuntimeException('invalid_settings_patch');
        }
        $raw = serialize($value);
        if (strlen($raw) > 1048576) {
            throw new RuntimeException('destination_settings_payload_too_large');
        }
        $rows = $expected['rows'];
        if ($rows !== [] && $rows[0]['option_value'] === $raw) {
            if ($this->read()['rows'] !== $rows) {
                throw new RuntimeException('concurrent_change');
            }
            return null;
        }
        $indexes = $this->fetchRows("SHOW INDEX FROM `{$wpdb->options}`");
        $unique = [];
        foreach ($indexes as $index) {
            if ((int) $index['Non_unique'] === 0) {
                $unique[$index['Key_name']][] = $index;
            }
        }
        $ready = false;
        foreach ($unique as $index) {
            $ready = $ready || (count($index) === 1 && $index[0]['Column_name'] === 'option_name' && $index[0]['Sub_part'] === null);
        }
        if (!$ready) {
            throw new RuntimeException('destination_unique_key_missing');
        }
        if (!$deferNotifications) {
            if ($rows === []) {
                do_action('add_option', BaseConstants::OPTION_PLUGIN_SETTINGS, $value);
            } else {
                do_action('update_option', BaseConstants::OPTION_PLUGIN_SETTINGS, $expected['value'], $value);
            }
        }
        if ($rows === []) {
            $result = $this->execute($wpdb->prepare(
                "INSERT INTO `{$wpdb->options}` (option_name, option_value, autoload) VALUES (%s, %s, %s)",
                BaseConstants::OPTION_PLUGIN_SETTINGS, $raw, 'yes'
            ));
        } else {
            $row = $rows[0];
            $result = $this->execute($wpdb->prepare(
                "UPDATE `{$wpdb->options}` SET option_value = %s WHERE option_id = %d AND option_name = %s AND BINARY option_value = BINARY %s AND BINARY autoload = BINARY %s",
                $raw, (int) $row['option_id'], BaseConstants::OPTION_PLUGIN_SETTINGS, $row['option_value'], $row['autoload']
            ));
        }
        if ($result !== 1) {
            $current = $this->read();
            throw new RuntimeException($current['rows'] !== $rows ? 'concurrent_change' : 'write_failed');
        }
        $notify = static function () use ($rows, $expected, $value): void {
            wp_cache_delete(BaseConstants::OPTION_PLUGIN_SETTINGS, 'options');
            wp_cache_delete('alloptions', 'options');
            wp_cache_delete('notoptions', 'options');
            if ($rows === []) {
                do_action('add_option_' . BaseConstants::OPTION_PLUGIN_SETTINGS, BaseConstants::OPTION_PLUGIN_SETTINGS, $value);
                do_action('added_option', BaseConstants::OPTION_PLUGIN_SETTINGS, $value);
            } else {
                do_action('update_option_' . BaseConstants::OPTION_PLUGIN_SETTINGS, $expected['value'], $value, BaseConstants::OPTION_PLUGIN_SETTINGS);
                do_action('updated_option', BaseConstants::OPTION_PLUGIN_SETTINGS, $expected['value'], $value);
            }
        };
        if ($deferNotifications) {
            return $notify;
        }
        $notify();
        return null;
    }

    private function fetchRows(string $sql): array
    {
        global $wpdb;
        $previous = $wpdb->suppress_errors(true);
        try {
            $rows = $wpdb->get_results($sql, ARRAY_A);
            if ($wpdb->last_error !== '' || !is_array($rows)) {
                throw new RuntimeException('destination_read_failed');
            }
            return $rows;
        } catch (Throwable $error) {
            throw new RuntimeException('destination_read_failed');
        } finally {
            $wpdb->suppress_errors($previous);
        }
    }

    private function execute(string $sql): int|false
    {
        global $wpdb;
        $previous = $wpdb->suppress_errors(true);
        try {
            $result = $wpdb->query($sql);
            return $wpdb->last_error !== '' ? false : $result;
        } catch (Throwable $error) {
            throw new RuntimeException('write_failed');
        } finally {
            $wpdb->suppress_errors($previous);
        }
    }

    private function isSafeValue(mixed $value, int $depth = 0): bool
    {
        if ($depth > 16 || is_object($value) || is_resource($value) || (is_float($value) && !is_finite($value))) {
            return false;
        }
        if (is_array($value)) {
            foreach ($value as $item) {
                if (!$this->isSafeValue($item, $depth + 1)) {
                    return false;
                }
            }
        }
        return true;
    }
}
