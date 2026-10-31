<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * CommunicationOptInDisabledException
 */
class CommunicationOptInDisabledException extends BaseException
{

	/**
	 * Get the title for the error.
	 *
	 * @return string
	 */
	public function getTitle(): string
	{
		return __('Communication Opt-In Disabled', 'beyondseo');
	}

	/**
	 * Get the description for the error.
	 *
	 * @return string
	 */
	public function getDescription(): string
	{
		return __('Communication opt-in is required to interact with the external API. Please enable it to continue.', 'beyondseo');
	}

	/**
	 * Get the reasons for the error.
	 *
	 * @return array
	 */
	public function getReasons(): array
	{
		return [
			__('The communication opt-in setting has been disabled.', 'beyondseo'),
			__('API requests cannot be sent without communication opt-in enabled.', 'beyondseo'),
			__('Enable communication opt-in in the plugin settings to restore functionality.', 'beyondseo'),
            __('Enable communication by going to BeyondSEO > Settings > Allow communication with rankingCoach servers and toggling the option to "On".', 'beyondseo'),
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
