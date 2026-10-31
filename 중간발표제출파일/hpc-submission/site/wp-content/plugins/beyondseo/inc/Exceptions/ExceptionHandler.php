<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Exception;
use JetBrains\PhpStorm\NoReturn;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\Helpers\Traits\RcApiTrait;
use RankingCoach\Inc\Core\PluginConfiguration;

/**
 * Singleton ExceptionHandler
 */
class ExceptionHandler {

	use RcLoggerTrait;
	use RcApiTrait;

	/**
	 * Returns the singleton instance of ExceptionHandler.
	 *
	 * @return ExceptionHandler
	 */
	private static ?self $instance = null;

	private string $errorStylePath;
	private string $errorTemplatePath;
	private string $plugin;
	private bool $hasError = false;

	/**
	 * Private constructor to prevent direct instantiation
	 */
	private function __construct(string $plugin)
	{
		$this->plugin = $plugin;

		// Configure paths relative to the `assets` directory
		$this->errorStylePath = plugins_url('assets/css/error-styles.css', __FILE__); // Keep URL for enqueuing styles
		$this->errorTemplatePath = plugin_dir_path(__FILE__) . 'templates/error-template.php'; // File system path for template rendering
	}

	/**
	 * Register error hooks
	 */
	public static function registerErrorHooks(string $plugin): void
	{
		$instance = self::getInstance($plugin);
		add_action('admin_enqueue_scripts', [$instance, 'enqueueErrorStyles']);
        // Force enqueue immediately if scripts already passed
        if (did_action('admin_enqueue_scripts')) {
            $instance->enqueueErrorStyles();
        }
	}

	/**
	 * Get the singleton instance
	 */
	public static function getInstance(string $plugin): self
	{
		if (self::$instance === null) {
			self::$instance = new self($plugin);
		}
		return self::$instance;
	}

	/**
	 * Handle an exception and render an error page
	 * @throws Exception
	 */
	#[NoReturn]
	public function error(?Exception $exception = null, ?string $additionalContent = null): void
	{
		if($additionalContent) {
			$additionalContent = json_encode($additionalContent, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
		}

		if($exception === null) {
			return;
		}

		// Build the error context, means the data that will be displayed on the error page
		$context = $this->buildErrorContext($exception, $additionalContent);

		// Log the exception in the error log and custom log file
		$this->logException($exception, $context, $additionalContent);

		$triggeredOnRequestContext = false;
		// Check if the request is an AJAX request
		if (defined('DOING_AJAX') && DOING_AJAX) {
			$triggeredOnRequestContext = true;
		}

		// Check for a Postman request (Postman often sets a custom user agent or headers)
		$user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
		if ( str_contains( $user_agent, 'PostmanRuntime' ) ) {
			$triggeredOnRequestContext = true;
		}

        if($exception instanceof BaseException && $exception->throwException) {
            $triggeredOnRequestContext = true;
        }

        if($triggeredOnRequestContext) {
            throw new Exception(esc_html($exception->getMessage() . ($additionalContent ? ' >>> ' . $additionalContent : '')));
        }

		// Render the error page, based on the error context and the additional content
		$this->renderErrorPage($context);
	}

	/**
	 * Enqueue the error styles
	 */
	public function enqueueErrorStyles(): void
	{
		if ( ! $this->hasError ) {
			return;
		}
		wp_enqueue_style('rankingcoach-error-styles', $this->errorStylePath, [], '1.0.0');
	}

	/**
	 * Enqueue inline styles using WordPress best practices
	 *
	 * @param string $styles The CSS styles to add inline
	 */
	private function enqueueInlineStyles(string $styles): void
	{
		// Ensure the main error styles are enqueued first
		$this->hasError = true;
		if (!wp_style_is('rankingcoach-error-styles', 'enqueued')) {
			$this->enqueueErrorStyles();
		}

		// Add the custom inline styles to the error styles handle
		$sanitized_styles = wp_strip_all_tags($styles);
		if (!empty($sanitized_styles)) {
			wp_add_inline_style('rankingcoach-error-styles', $sanitized_styles);
		}
	}

	/**
	 * Build the error context
	 */
	private function buildErrorContext(Exception $exception, ?string $additionalData = null): array
	{
		$context = ErrorContextFactory::create($exception);
		$context['plugin_name'] = RANKINGCOACH_NAME ?? 'Plugin';
		$context['additional_data'] = $additionalData;
		return $context;
	}

	/**
	 * Log the exception
	 */
	private function logException(Exception $exception, array $context, ?string $additionalContent): void
	{
		$message = $this->buildExceptionMessage($exception, $context, $additionalContent);
		$this->log($message, 'ERROR');
	}

	/**
	 * Render the error page
	 */
	#[NoReturn]
	private function renderErrorPage(array $context): void
	{
        $this->hasError = true;
        ExceptionHandler::registerErrorHooks(RANKINGCOACH_PLUGIN_BASENAME);

        // Add inline styles if provided by the exception
        if (!empty($context['styles'])) {
            $this->enqueueInlineStyles($context['styles']);
        }

        // Ignore PHPCS warnings for output escaping, as the template is designed to handle it
        $content = TemplateRenderer::render($this->errorTemplatePath, $context);
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        wp_die($content, esc_html($context['title']), ['back_link' => true]);
	}
}
