<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Technical;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Modules\ModuleBase\BaseModule;
use RankingCoach\Inc\Modules\ModuleManager;
use ReflectionException;

/**
 * Class VoiceSearchOptimization
 */
class VoiceSearchOptimization extends BaseModule {

	/**
	 * VoiceSearchOptimization constructor.
	 *
	 * @param ModuleManager $moduleManager
	 *
	 * @throws ReflectionException
	 */
    public function __construct(ModuleManager $moduleManager) {
        $initialization = [
            'title' => 'Voice Search Optimization',
            'description' => 'Optimizes website content and structure for voice search by focusing on long-tail keywords, conversational language, FAQ schema markup, and mobile-friendliness. Improves visibility in voice search results and enhances user experience for voice search queries.',
            'version' => '1.0.0',
            'name' => 'voiceSearchOptimization',
            'priority' => 25,
            'dependencies' => [],
            'settings' => [['key' => 'enable_faq_schema', 'type' => 'boolean', 'default' => True, 'description' => 'Enable FAQPage schema markup to provide structured data for frequently asked questions, improving visibility in voice search results and rich snippets.'], ['key' => 'conversational_keyword_suggestions', 'type' => 'boolean', 'default' => True, 'description' => 'Provide suggestions for conversational and long-tail keywords within the content editor, helping users optimize content for voice search queries.'], ['key' => 'mobile_optimization_check', 'type' => 'boolean', 'default' => True, 'description' => 'Perform checks for mobile-friendliness, a crucial factor for voice search optimization, as most voice searches are performed on mobile devices.']],
            'explain' => 'When enabled, the module suggests using the FAQPage schema for content containing frequently asked questions. This helps search engines understand the question-and-answer format and increases the chances of appearing as a rich snippet or voice search answer. If a user is writing about \'best pizza in Chicago\', the module suggests long-tail, conversational keywords like \'Where can I find good pizza near me in Chicago?\' or \'What are the top-rated pizza places in downtown Chicago?\'.  It will also check for mobile-friendliness and remind users to optimize the website for mobile devices, which are predominantly used for voice search.',
        ];
        parent::__construct($moduleManager, $initialization);
    }

    /**
     * Registers the hooks for the module.
     * @return void
     */
	public function initializeModule(): void {
		parent::initializeModule();
    }

	/**
	 * Create necessary SQL tables if they don't already exist.
	 * @param string $table_name
	 * @param string $charset_collate
	 * @return string
	 * @noinspection SqlNoDataSourceInspection
	 */
	protected function getTableSchema(string $table_name, string $charset_collate): string {
		return '';
	}
}
