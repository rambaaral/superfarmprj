<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core;

if ( !defined('ABSPATH') ) {
    exit;
}

use Exception;
use RankingCoach\Inc\Core\Helpers\CoreHelper;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Traits\SingletonTrait;

/**
 * Class HeadManager
 * Extends HeadMetaManager to manage document title and additional logic.
 */
class HeadManager extends HeadMetaManager {

	use SingletonTrait;

	protected static ?string $documentTitle = null;
	protected array $headElements = [];

	/**
	 * Initialize controller and set up necessary hooks
	 */
	public function __construct() {
		// Skip initialization for non-frontend requests
        if (WordpressHelpers::is_admin_request()) {
            return;
        }

		// Register hooks with appropriate priorities
		$this->initializeHooks();
	}

	/**
	 * Initialize necessary WordPress hooks
	 */
	private function initializeHooks(): void {
		add_action('wp', [ $this, 'configureTitleHooks' ], 9999);
		add_action('wp_head', [ $this, 'renderHeadElements' ], 0);
	}

	/**
	 * Adds a new head element to the collection
	 * *
	 * * @param string $element HTML element to be added to document head
 */
	public function addHeaderElement(string $element): void {
		$this->headElements[] = $element;
	}

    /**
     * Generates and returns the document title
     *
     * @param string $defaultTitle Fallback title from WordPress
     * @return string Processed and sanitized document title
     * @throws Exception
     */
	public static function retrieveDocumentTitle(string $defaultTitle = ''): string {
		if (isset(self::$documentTitle)) {
			return self::$documentTitle;
		}

		$rawTitle = WordpressHelpers::retrieve_title();
		self::$documentTitle = CoreHelper::encode_output_html(
			!empty($rawTitle) ? $rawTitle : $defaultTitle
		);

		return CoreHelper::sanitize_regex_pattern(self::$documentTitle);
	}
}