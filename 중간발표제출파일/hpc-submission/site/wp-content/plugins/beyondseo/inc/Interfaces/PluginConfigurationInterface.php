<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Interface ConfigurationInterface
 */
interface PluginConfigurationInterface
{
    /**
     * Returns the plugin file.
     *
     * @return string
     */
    public function getPluginFile(): string;

    /**
     * Returns the plugin version.
     *
     * @return string
     */
    public function getPluginVersion(): string;
}