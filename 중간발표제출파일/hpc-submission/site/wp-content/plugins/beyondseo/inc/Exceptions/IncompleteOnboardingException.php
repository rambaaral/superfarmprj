<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class IncompleteOnboardingException
 */
class IncompleteOnboardingException extends BaseException
{
	/**
	 * IncompleteOnboardingException constructor.
	 *
	 * @return string
	 */
	public function getTitle(): string
	{
		return $this->getMessage() ?? __('Onboarding Incomplete', 'beyondseo');
	}

	/**
	 * @return string
	 */
	public function getDescription(): string
	{
		return __(
			'The onboarding process for your rankingCoach account has not been completed yet. Please finalize the setup in the rankingCoach platform to connect all services.',
			'beyondseo'
		);
	}

	/**
	 * @return array
	 */
	public function getReasons(): array
	{
		return [
			__('The onboarding process has not been completed in your rankingCoach account.', 'beyondseo'),
			__('Some external service features require completed onboarding to function.', 'beyondseo'),
		];
	}

	/**
	 * @return bool
	 * @noinspection PhpMissingParentCallCommonInspection
	 */
	public function shouldShowFooter(): bool
	{
		return true;
	}

	/**
	 * @return string
	 * @noinspection PhpMissingParentCallCommonInspection
	 */
	public function getFooter(): string
	{
		return __('Please complete the onboarding process in your rankingCoach platform account to enable all connected services.', 'beyondseo');
	}
}
