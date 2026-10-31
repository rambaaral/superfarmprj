<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core;

if ( !defined('ABSPATH') ) {
    exit;
}

use RankingCoach\Inc\Exceptions\HttpApiException;
use RankingCoach\Inc\Core\Api\User\UserApiManager;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Traits\SingletonTrait;
use ReflectionException;

/**
 * Manages operations related to the current user, including retrieving user information,
 * updating user details, and handling user session data.
 */
class CurrentUserManager {


    use SingletonTrait;

	/**
	 * Constructor
	 */
	public function __construct()
	{

	}

	/**
	 * Checks if the onboarding process has been completed.
	 *
	 * @param mixed $data
	 *
	 * @return array An array with two keys: 'external' and 'internal', each containing a boolean value.
	 */
	public function setupData(mixed $data): array {
		$wp_user = wp_get_current_user();
		if (!$wp_user || $wp_user->ID === 0) {
			return [];
		}

		$onboardingOnExternalSystemDone = true;

		if($data) {
			$onboardingOnExternalSystemDone = $this->isValidExternalOnboardingData($data);
		}

		// Check if the onboarding is signed as finalized on DB
		$onboardingOnWordPressDone = $this->isValidInternalOnboardingData();

		return [
			'isExternalOnboarded' => $onboardingOnExternalSystemDone,
			'isInternalOnboarded' => $onboardingOnWordPressDone
		];
	}

	/**
	 * Checks if the onboarding process has been completed on the WordPress side.
	 *
	 * @return bool
	 */
	public function isValidInternalOnboardingData(): bool {
        return
            get_option(BaseConstants::OPTION_ACCOUNT_ONBOARDING_ON_WP, false) == true &&
            !empty(get_option(BaseConstants::OPTION_ACCOUNT_ONBOARDING_ON_WP_LAST_UPDATE, null));
	}

	/**
	 * @param mixed $data
	 *
	 * @return bool
	 */
	public function isValidExternalOnboardingData(mixed $data): bool {

		$rankingcoach_data = $data;
		if ( empty( $rankingcoach_data ) || ( ! is_array( $rankingcoach_data ) && ! is_object( $rankingcoach_data ) ) ) {
			return false;
		}

		// Convert array to object for consistency
		if ( is_array( $rankingcoach_data ) ) {
			$rankingcoach_data = (object) $rankingcoach_data;
		}


		// Ensure account exists
		return $this->isValidExternalAccountData( $rankingcoach_data );
	}

	/**
	 * @param mixed $rankingcoach_data
	 *
	 * @return bool
	 */
	public function isValidExternalAccountData( mixed $rankingcoach_data ): bool {
		$account = isset( $rankingcoach_data->account ) && is_object( $rankingcoach_data->account ) ? $rankingcoach_data->account : null;
		if ( ! $account ) {
			return false;
		}

		// Check subscriptions
		$subscriptions   = isset( $account->subscriptions ) && is_object( $account->subscriptions ) ? $account->subscriptions : null;
		$hasSubscription = isset( $subscriptions->elements ) && is_array( $subscriptions->elements ) ? $subscriptions->elements : [];

		$hasActiveSubscription = ! empty( array_filter( $hasSubscription, function ( $element ) {
			return isset( $element->status ) && $element->status === 'ACTIVE';
		} ) );

		// Check active projects
		$hasActiveProject = isset( $account->totalNumberOfActiveProjects ) && is_numeric( $account->totalNumberOfActiveProjects ) && $account->totalNumberOfActiveProjects > 0;

		// Check keywords
		$location            = isset( $rankingcoach_data->location ) && is_object( $rankingcoach_data->location ) ? $rankingcoach_data->location : null;
		$keywords            = isset( $location->keywords ) && is_object( $location->keywords ) ? $location->keywords : null;
		$hasMultipleKeywords = ! empty( $keywords->elements ) && is_array( $keywords->elements );

		return $hasActiveProject && $hasActiveSubscription && $hasMultipleKeywords;
	}
}