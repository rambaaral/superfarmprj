<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\Admin\Pages;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Admin\AdminManager;
use RankingCoach\Inc\Core\Admin\AdminPage;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\ChannelFlow\Traits\FlowGuardTrait;
use RankingCoach\Inc\Core\Frontend\ViteApp\ReactApp;
use RankingCoach\Inc\Core\Helpers\JavaScriptHelper;
use RankingCoach\Inc\Traits\SingletonTrait;

/**
 * Represents a page for general settings.
 * @method GeneralSettingsPage getInstance(): static
 */
class GeneralSettingsPage extends AdminPage {

	use SingletonTrait;
    use RcLoggerTrait;
    use FlowGuardTrait;

	public string $name = 'generalSettings';

	public static AdminManager|null $managerInstance = null;

	   /**
	    * Flag to track if access control was already handled by the load-{$page_hook} hook.
	    * When true, page_content() will skip the redirect logic since it was already processed.
	    *
	    * @var bool
	    */
	   private bool $accessControlHandled = false;

    /**
     * GeneralSettingsPage constructor.
     * Initializes the GeneralSettingsPage instance and sets up the necessary hooks.
     */
	public function __construct() {
		add_action('current_screen', function($screen) {
			// Ensure the screen object is available
			if (!is_object($screen) || !isset($screen->id)) {
				return;
			}

			if (
                $screen->base === RANKINGCOACH_BRAND_SLUG . '_page_rankingcoach-' . $this->name ||
                $screen->base === 'admin_page_rankingcoach-' . $this->name
            ) {
				ReactApp::get([
					'generalSettings'
				]);
			}
		});
        parent::__construct();
	}

	/**
	 * @return string
	 */
	public function page_name(): string
	{
		return $this->name;
	}

	/**
	 * Handles the generation or processing of page content within the application.
	 *
	 * @return void
	 */
	public function page_content(): void {

        // If access control was already handled by the load-{$page_hook} hook,
        // skip the redirect logic here (it runs before headers are sent in that pattern).
        if (!$this->accessControlHandled) {
            // Perform access checks and redirect if necessary.
        }

		echo wp_kses('<div id="generalSettings-rankingcoach-page" class="beyondseo" style="position: fixed; top: 0; left: 0; width: 100vw; background: #fff; height: 100vh; justify-items: center; align-content: center; overflow-y: scroll;"></div>', [
			'div' => [
				'id' => [],
				'class' => [],
				'style' => []
			]
		]); //z-index: 1000 for full screen

		// Add login session expiration handler script
		      // This script will handle the login modal state and refresh the page when the modal is closed.
		      // Is not a mistake to add this script here.
		      // The behaviour is happening on the login modal, which is opened when the user session expires.
		JavaScriptHelper::enqueueLoginSessionExpirationScript();
	}

    /**
    * Handle access control before headers are sent.
    *
    * This method is designed to be called from the WordPress `load-{$page_hook}` action,
    * which fires BEFORE any output is sent. This allows us to perform redirects using
    * wp_safe_redirect()
    *
    * @return void
    */
    public function handleAccessControl(): void
    {
      // Mark that access control has been handled, so page_content() skips redirect logic
      $this->accessControlHandled = true;
    }
}
