<?php

namespace RankingCoach\Inc\Core\Frontend\ViteApp;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Frontend\ViteApp\Assets\Assets;
use RankingCoach\Inc\Core\Frontend\ViteApp\Core\Config;
use RankingCoach\Inc\Core\Frontend\ViteApp\Core\Hooks;
use Exception;

/**
 * Class App
 */
class ReactApp {
    /** @var ReactApp|null Used to store a Class instance */
    private static ?ReactApp $instance = null;

    /** @var object Used to store a Assets instance */
    private object $assets;

    /** @var object Used to store a Config instance */
    private object $config;

    /** @var array Used to store a Integrations instance */
    private static array $approvedIntegrations = [
        'add_new' => true,
        'edit' => true,
        'float' => true,
        'onboarding' => true,
        'activation' => true,
        'registration' => true,
        'generalSettings' => true,
        'support' => true,
        'elementor' => true,
        'postlist' => true,
        'scoreOrbHeaderButton' => true,
        'gutenbergSidebar' => true,
        'upsell' => true,
        'setupWizard' => true,
        'dashboardReview' => true
    ];

	public static array $loadComponents = [];

    /**
     * Class App constructor
     * Initializes a few modules needed
     */
    private function __construct(array $components = []) {

        defined('RANKINGCOACH_REACT_VERSION')       || define( 'RANKINGCOACH_REACT_VERSION', '1.0.0' );
        defined('RANKINGCOACH_REACT_ROOT')          || define( 'RANKINGCOACH_REACT_ROOT', RANKINGCOACH_PLUGIN_URL . 'react');
        defined('RANKINGCOACH_REACT_PATH')          || define( 'RANKINGCOACH_REACT_PATH', RANKINGCOACH_PLUGIN_DIR . 'react');
        defined('RANKINGCOACH_REACT_URI')           || define( 'RANKINGCOACH_REACT_URI', home_url( RANKINGCOACH_REACT_ROOT ) );
        defined('RANKINGCOACH_REACT_HMR_HOST')      || define( 'RANKINGCOACH_REACT_HMR_HOST', 'http://localhost.wordpress/' );
        defined('RANKINGCOACH_REACT_HMR_URI')       || define( 'RANKINGCOACH_REACT_HMR_URI', RANKINGCOACH_REACT_HMR_HOST . RANKINGCOACH_REACT_ROOT );
        defined('RANKINGCOACH_REACT_ASSETS_PATH')   || define( 'RANKINGCOACH_REACT_ASSETS_PATH', RANKINGCOACH_REACT_PATH . DIRECTORY_SEPARATOR . 'dist' );
        defined('RANKINGCOACH_REACT_ASSETS_URI')    || define( 'RANKINGCOACH_REACT_ASSETS_URI', RANKINGCOACH_REACT_ROOT . '/dist' );

	    self::$loadComponents = $components;

        $this->assets       = self::init( new Assets() );
        $this->config       = self::init( new Config() );
    }

    /**
     * Init
     * Initializes the modules requested!
     *
     * @param object $module
     * @return object
     */
    public static function init( object $module ): object {
        return Hooks::init( $module );
    }

    /**
     * Get
     * Returns an instance of App class if the instance does not exist already
     *
     * @param array $components
     * @param int|null $postId
     * @return ReactApp|null
     */
    public static function get(array $components = [], ?int $postId = null): ?ReactApp {
        // Detect all possible components to load and compare with the requested ones
        // to avoid loading unnecessary components
        // If the $components coming with un-included components, ignore them

        $permitted = array_keys(self::getApprovedIntegrations());

        $filteredComponents = array_intersect($components, $permitted);

        if( !empty($filteredComponents) ) {
            if ( empty( self::$instance ) ) {
                self::$instance = new ReactApp($filteredComponents);
            } else {
                // Merge new components with existing ones
                self::$loadComponents = array_unique(array_merge(self::$loadComponents, $filteredComponents));
            }
        }

        return self::$instance ?? new ReactApp();
    }

    /**
     * Assets
     * Attributes an instance of class Assets to a class property
     *
     * @return object
     */
    public function assets(): object {
        return $this->assets;
    }

    /**
     * Config
     * Attributes an instance of class Config to a class property
     *
     * @return object
     */
    public function config(): object {
        return $this->config;
    }

    /**
     * Integrations
     * Attributes an instance of class Integrations to a class property
     *
     * @param bool $onlyTrue
     * @return array
     */
    public static function getApprovedIntegrations(bool $onlyTrue = true): array {
        $all = self::$approvedIntegrations;
        if($onlyTrue) {
            return array_filter($all, function($value) {
                return $value === true;
            });
        }
        return $all;
    }

    /**
     * Wake Up
     *
     * @return void
     * @throws Exception
     * @todo add the description
     */
    public function __wakeup(): void {
        throw new Exception( 'Cannot unserialize a singleton.' );
    }

    /**
     * Clone
     *
     * @return void
     * @todo add the description
     */
    private function __clone(): void {
    }
}
