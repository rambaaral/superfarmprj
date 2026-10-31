<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RuntimeException;

/**
 * Class TemplateRenderer
 */
class TemplateRenderer {

	/**
	 * Renders a template with the given context.
	 *
	 * @param string $templatePath
	 * @param array $context
	 *
	 * @return string
	 */
	public static function render(string $templatePath, array $context): string
	{
		if (!file_exists($templatePath)) {
			throw new RuntimeException(esc_html('Template not found: ' . $templatePath));
		}
        ob_start();
        extract($context);
		include $templatePath;
		return ob_get_clean();
	}
}