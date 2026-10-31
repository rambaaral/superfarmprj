<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Links;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Modules\ModuleBase\BaseModule;
use RankingCoach\Inc\Modules\ModuleManager;
use ReflectionException;

/**
 * Class InternalLinkSuggestions
 */
class InternalLinkSuggestions extends BaseModule {

	/**
	 * InternalLinkSuggestions constructor.
	 *
	 * @param ModuleManager $moduleManager
	 *
	 * @throws ReflectionException
	 */
    public function __construct(ModuleManager $moduleManager) {
        $initialization = [
            'title' => 'Internal Link Suggestion Tool',
            'description' => 'Suggests relevant internal links based on content analysis, helping improve website SEO, site structure, and user navigation. Provides suggestions within the content editor and allows for customization of suggestion parameters.',
            'version' => '1.0.0',
            'name' => 'internalLinkSuggestions',
            'priority' => 19,
            'dependencies' => [],
            'settings' => [['key' => 'automatic_suggestions', 'type' => 'boolean', 'default' => True, 'description' => 'Enable automatic internal link suggestions within the content editor. When enabled, suggestions will appear as you type or edit content.'], ['key' => 'min_word_count', 'type' => 'integer', 'default' => 300, 'description' => 'The minimum word count for a post or page before internal link suggestions are generated.  This helps prevent suggestions for very short content.'], ['key' => 'max_suggestions', 'type' => 'integer', 'default' => 3, 'description' => 'Maximum number of internal link suggestions to display per post/page. Prevents overwhelming users with too many options.']],
            'explain' => 'As a user is writing a blog post about \'WordPress plugins,\' the Internal Link Suggestion Tool automatically suggests relevant internal links to other pages on the website, such as a page about \'SEO plugins\' or a review of a specific plugin. These suggestions appear within the content editor, making it easy for the user to add internal links with minimal disruption to their workflow. The number of suggestions is limited to the configured maximum (e.g., 3), and suggestions are only provided if the post\'s word count exceeds the configured minimum.',
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
