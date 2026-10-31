<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Modules\ModuleLibrary\Links\LinkAnalyzer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Modules\ModuleBase\BaseSubmoduleSettings;
use RankingCoach\Inc\Modules\ModuleBase\BaseModule;

/**
 * Class LinkAnalyzerSettings
 * 
 * Handles settings for the LinkAnalyzer module.
 */
class LinkAnalyzerSettings extends BaseSubmoduleSettings {

    /** @var LinkAnalyzer The LinkAnalyzer module instance. */
    public BaseModule $module;

    protected ?array $defaultSettings = [
        'analyze_internal_links' => true,
        'analyze_external_links' => true,
        'analyze_nofollow_links' => true,
        'analyze_sponsored_links' => true,
    ];

    protected string $settingsKey = 'link_analyzer_settings';

    /**
     * LinkAnalyzerSettings constructor.
     * @param LinkAnalyzer $module
     * @param array|null $params
     */
    public function __construct(LinkAnalyzer $module, ?array $params = null) {
        $this->module = $module;
        parent::__construct($module, $params);
    }

    /**
     * Initialize settings for the LinkAnalyzer module.
     */
    public function initializeSettings(): void {
        $this->init();
    }
}
