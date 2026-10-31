<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Admin\AdminManager;

/**
 * MissingTokenException
 */
class MissingTokenException extends BaseException
{

	/**
	 * Get the title for the error.
	 *
	 * @return string
	 */
	public function getTitle(): string
	{
		return __('Refresh Token Missing', 'beyondseo');
	}

	/**
	 * Get the description for the error.
	 *
	 * @return string
	 */
	public function getDescription(): string
	{
		return __('A refresh token is required to continue. This token allows secure and seamless interactions with the external API.', 'beyondseo');
	}

	/**
	 * Get the reasons for the error.
	 *
	 * @return array
	 */
	public function getReasons(): array
	{
		return [
			__('The token might have been removed or expired.', 'beyondseo'),
			__('Configuration might be missing for API integration.', 'beyondseo'),
			__('Ensure the token generation process has been completed.', 'beyondseo'),
		];
	}

	/**
	 * Determine if the footer should be shown.
	 *
	 * @return bool
	 * @noinspection PhpMissingParentCallCommonInspection
	 */
	public function shouldShowFooter(): bool
	{
		return true;
	}

	/**
	 * Get the footer content.
	 *
	 * @return string
	 * @noinspection PhpMissingParentCallCommonInspection
	 */
	public function getFooter(): string {
        return '';
	}

	/**
	 * Get additional styles for the error page.
	 *
	 * @return string
	 * @noinspection PhpMissingParentCallCommonInspection
	 */
	public function getStyles(): string
	{
		return '
            .rc-error-body ul {
                color: #ff6f61;
                font-weight: bold;
                padding: 10px;
				color: chocolate;
                font-weight: bold;
            }
            .rc-error-body ul li {
				margin-bottom: 3px;
            }
            .rc-error-header {
                font-size: 1.5em;
                text-align: center;
                color: #e74c3c;
            }
        ';
	}
}
