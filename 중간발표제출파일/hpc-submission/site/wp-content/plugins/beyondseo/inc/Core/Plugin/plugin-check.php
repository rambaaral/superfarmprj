<?php
declare(strict_types=1);

if (!defined('ABSPATH')) exit;

/**
 * Configure plugin-check tool to ignore third-party dependencies
 */
add_filter('wp_plugin_check_ignore_directories', function(array $directories): array {
    // Add shell directory to ignored directories
    $directories[] = 'shell';
    // Add tools directory to ignored directories
    $directories[] = 'tools';
    // Add react directory to ignored directories
    $directories[] = 'react';
    // Add react directory to ignored directories
    $directories[] = 'extension';

    return $directories;
}, 10, 1);

/**
 * Configure plugin-check tool to ignore specific third-party files
 * Additional file-level exclusions for external dependencies
 */
add_filter('wp_plugin_check_ignore_files', function(array $files): array {

    // Add shell directory to ignored directories
    $shell_files = glob(RANKINGCOACH_PLUGIN_DIR . 'shell/*.php');
    if ($shell_files) {
        foreach ($shell_files as $file) {
            // Convert absolute path to relative path from plugin root
            $relative_path = str_replace(RANKINGCOACH_PLUGIN_DIR, '', $file);
            $files[] = ltrim($relative_path, '/');
        }
    }

    // Add tools directory to ignored directories
    $tools_files = glob(RANKINGCOACH_PLUGIN_DIR . 'tools/*.php');
    if ($tools_files) {
        foreach ($tools_files as $file) {
            // Convert absolute path to relative path from plugin root
            $relative_path = str_replace(RANKINGCOACH_PLUGIN_DIR, '', $file);
            $files[] = ltrim($relative_path, '/');
        }
    }

    // Add react directory to ignored directories
    // Note: This assumes that the react directory contains all kind of files that should be ignored
    $react_files = glob(RANKINGCOACH_PLUGIN_DIR . 'react/**/*.*');
    if ($react_files) {
        foreach ($react_files as $file) {
            // Convert absolute path to relative path from plugin root
            $relative_path = str_replace(RANKINGCOACH_PLUGIN_DIR, '', $file);
            $files[] = ltrim($relative_path, '/');
        }
    }

    // Add extension directory to ignored directories
    $extension_files = glob(RANKINGCOACH_PLUGIN_DIR . 'extension/**/*.*');
    if ($extension_files) {
        foreach ($extension_files as $file) {
            // Convert absolute path to relative path from plugin root
            $relative_path = str_replace(RANKINGCOACH_PLUGIN_DIR, '', $file);
            $files[] = ltrim($relative_path, '/');
        }
    }

    // Add specific files to ignored files
    $specific_files = [
        '.DS_Store',
        '.gitignore',
        '.kilocodeignore',
    ];
    foreach ($specific_files as $file) {
        // Convert absolute path to relative path from plugin root
        $relative_path = str_replace(RANKINGCOACH_PLUGIN_DIR, '', RANKINGCOACH_PLUGIN_DIR . $file);
        $files[] = ltrim($relative_path, '/');
    }

    return $files;
}, 10, 1);