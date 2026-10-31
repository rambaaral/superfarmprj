<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Content\ContentAnalysis;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Seo\Entities\Keywords\Keyword;
use RankingCoach\Inc\Core\Seo\Entities\Keywords\Keywords;
use RankingCoach\Inc\Core\Seo\Entities\Keywords\WPAdditionalKeywords;
use RankingCoach\Inc\Core\Seo\Entities\Keywords\WPPrimaryKeyword;
use RankingCoach\Inc\Core\Seo\Entities\ContentAnalysis\WPKeywordsAnalysis;
use Exception;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\Helpers\Traits\RcApiTrait;
use RankingCoach\Inc\Exceptions\HttpApiException;
use RankingCoach\Inc\Modules\ModuleBase\BaseModule;
use RankingCoach\Inc\Modules\ModuleManager;
use ReflectionException;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class ContentAnalysis
 */
class ContentAnalysis extends BaseModule {

	use RcApiTrait;
	use RcLoggerTrait;

	public const MODULE_NAME = 'contentAnalysis';

	/** @var int|null The content (page/post) ID. */
	protected ?int $contentId = null;

	/**
	 * ContentAnalysis constructor.
	 *
	 * @param ModuleManager $moduleManager
	 *
	 * @throws ReflectionException
	 */
    public function __construct(ModuleManager $moduleManager) {
        $initialization = [
			'active' => true,
            'title' => 'SEO Content Analysis',
            'description' => 'Performs in-depth analysis of content to assess SEO performance, focusing on keyword density, readability, content length, and other relevant factors. Provides actionable recommendations for content improvement.',
            'version' => '1.0.0',
            'name' => 'contentAnalysis',
            'priority' => 13,
            'dependencies' => [],
            'settings' => [['key' => 'check_keyword_density', 'type' => 'boolean', 'default' => True, 'description' => 'Analyze keyword density to ensure optimal keyword usage and avoid keyword stuffing.'], ['key' => 'check_readability', 'type' => 'boolean', 'default' => True, 'description' => 'Analyze content readability to assess its complexity and suggest improvements for better user engagement.'], ['key' => 'min_content_length', 'type' => 'integer', 'default' => 300, 'description' => 'Minimum recommended content length (in words).  Content shorter than this might be flagged for improvement.']],
            'explain' => 'After a user finishes writing a blog post, this module analyzes the content.  It checks the keyword density based on the target keywords identified by the \'Text Optimizer\' module, assesses the readability level using metrics like Flesch-Kincaid score, and evaluates content length. If the keyword density is too high (potential keyword stuffing), the module provides a warning and suggests reducing keyword usage.  If the readability is poor, it might suggest shorter sentences and simpler vocabulary.  The module also checks if the content meets the minimum recommended length and suggests adding more content if it\'s too short.',
        ];
        parent::__construct($moduleManager, $initialization);
    }

    /**
     * Registers the hooks for the module.
     * @return void
     */
	public function initializeModule(): void {
		if(!$this->module_active) {
			return;
		}

		parent::initializeModule();
    }

	/**
	 * Retrieves the name of the module.
	 * @return string The name of the module.
	 */
	public static function getModuleNameStatic(): string {
		return self::MODULE_NAME;
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

	/**
	 * Process the keywords.
	 *
	 * @param WPKeywordsAnalysis $keywords
	 * @param mixed $proposedKeywords
	 *
	 * @return void
	 */
	public function processKeywords(WPKeywordsAnalysis &$keywords, mixed $proposedKeywords): void {
		$this->processExistingKeywords($keywords->existingKeywords, $proposedKeywords->existingKeywords ?? null, Keyword::class);
		$this->processKeywordObject($keywords->primaryKeywordFromExisting, $proposedKeywords->matchPrimaryKeywordFromExisting ?? null);
		$this->processKeywordObject($keywords->primaryKeywordFromContent, $proposedKeywords->matchPrimaryKeywordFromContent ?? null);
		$this->processAdditionalKeywords($keywords->additionalKeywordsFromContent, $proposedKeywords->additionalKeywordsFromContent ?? null, Keyword::class);
		$this->processAdditionalKeywords($keywords->additionalKeywordsFromExisting, $proposedKeywords->additionalKeywordsFromExisting ?? null, Keyword::class);
	}

    /**
     * Process the keyword list.
     *
     * @param WPAdditionalKeywords|null $keywords
     * @param mixed $proposedKeywords
     * @param string $className
     *
     * @return void
     */
    public function processAdditionalKeywords(?WPAdditionalKeywords &$keywords, mixed $proposedKeywords, string $className): void {
        if($proposedKeywords && get_class($proposedKeywords) && is_array($proposedKeywords->elements)) {
            $keywords = new WPAdditionalKeywords();
            foreach ($proposedKeywords->elements as $proposedKeyword) {
                $keyword = new $className();
                $keyword->setName($proposedKeyword->name);
                $keywords->add($keyword);
            }
        }
    }


	/**
	 * Process the keyword list.
	 *
	 * @param Keywords|null $keywords
	 * @param mixed $proposedKeywords
	 * @param string $className
	 *
	 * @return void
	 */
	public function processExistingKeywords(?Keywords &$keywords, mixed $proposedKeywords, string $className): void {
		if($proposedKeywords && get_class($proposedKeywords) && is_array($proposedKeywords->elements)) {
			$keywords = new Keywords();
			foreach ($proposedKeywords->elements as $proposedKeyword) {
				$keyword = new $className();
				$keyword->setName($proposedKeyword->name);
				$keywords->add($keyword);
			}
		}
	}

	/**
	 * Process the keyword object.
	 *
	 * @param WPPrimaryKeyword|null $keyword
	 * @param mixed $proposedKeyword
	 *
	 * @return void
	 */
	protected function processKeywordObject(?WPPrimaryKeyword &$keyword, mixed $proposedKeyword): void {
		if($proposedKeyword && get_class($proposedKeyword)) {
			$keyword = new WPPrimaryKeyword(
				$proposedKeyword->relevance_score ?? null,
				$proposedKeyword->intent ?? null,
				$proposedKeyword->density ?? null,
			);
			$keyword->setName($proposedKeyword->name);
		}
	}
}
