<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Graphs\KnowledgeGraph;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Exception;
use RankingCoach\Inc\Core\Settings\SettingsManager;
use RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Graphs;
use ReflectionException;

/**
 * Knowledge Graph Person graph class.
 * This is the main Person graph that can be set to represent the site.
 */
class KgPerson extends Graphs\Graph {

    use Graphs\Traits\Image;

	/**
	 * Supported social platforms with their base URLs.
	 */
	private const SOCIAL_PLATFORMS = [
		'facebook' => 'https://facebook.com/',
		'twitter' => 'https://x.com/',
		'instagram' => 'https://instagram.com/',
		'linkedin' => 'https://linkedin.com/in/',
		'youtube' => 'https://youtube.com/',
		'tiktok' => 'https://tiktok.com/@',
		'pinterest' => 'https://pinterest.com/',
		'github' => 'https://github.com/',
		'tumblr' => 'https://tumblr.com/',
		'snapchat' => 'https://snapchat.com/add/',
		'wikipedia' => 'https://en.wikipedia.org/wiki/',
		'personal_website' => '',
	];
	/**
	 * Returns the graph data.
	 *
     * @param object|null $graphData The graph data.
	 * @return array $data The graph data.
	 * @throws ReflectionException
	 * @throws Exception
	 *
	 */
	public function get($graphData = null): array
    {
        $options = SettingsManager::instance()->get_options();
		if ( 'person' !== $options['site_represents'] ) {
			return [];
		}

		$personName = $options['organisation_or_person_name'] ?? '';
		
		// Check if this is a manual configuration or user ID
		if ( 'manual' === $personName ) {
			return $this->manual();
		}

		// Try to get user ID if it's numeric
		$userId = is_numeric( $personName ) ? (int) $personName : 0;
		
		// If no valid user ID, try to get current user or site admin
		if ( ! $userId ) {
			$userId = get_current_user_id();
			if ( ! $userId ) {
				// Fallback to first admin user
				$admins = get_users( [ 'role' => 'administrator', 'number' => 1 ] );
				$userId = ! empty( $admins ) ? $admins[0]->ID : 0;
			}
		}

		if ( ! $userId ) {
			return [];
		}

		$data = [
			'@type' => 'Person',
			'@id'   => trailingslashit( home_url() ) . '#person',
			'name'  => $personName && ! is_numeric( $personName ) 
				? sanitize_text_field( $personName )
				: get_the_author_meta( 'display_name', $userId )
		];

		$avatar = $this->avatar( $userId, 'personImage' );
		if ( $avatar ) {
			$data['image'] = $avatar;
		}

		$socialUrls = $this->getUserProfiles( $userId );
		if ( ! empty( $socialUrls ) ) {
			$data['sameAs'] = array_values( array_filter( $socialUrls ) );
		}

		return $data;
	}

	/**
	 * Returns the data for the person if it is set manually.
	 *
	 * @return array $data The graph data.
	 */
	private function manual(): array {
		$options = SettingsManager::instance()->get_options();
		
		$data = [
			'@type' => 'Person',
			'@id'   => trailingslashit( home_url() ) . '#person',
			'name'  => $options['person_manual_name'] ?? get_bloginfo('name')
		];

		// Get manual person image/logo
		$personImage = $options['person_manual_image'] ?? '';
		if ( $personImage && filter_var($personImage, FILTER_VALIDATE_URL) ) {
			$data['image'] = [
				'@type' => 'ImageObject',
				'@id' => trailingslashit( home_url() ) . '#personImage',
				'url' => $personImage
			];
		}

		$socialUrls = array_values( $this->getOrganizationProfiles() );
		if ( $socialUrls ) {
			$data['sameAs'] = $socialUrls;
		}

		return $data;
	}

	/**
	 * Retrieves social profiles for a specific user.
	 *
	 * @param int $userId The user ID.
	 * @return array Array of social profile URLs.
	 */
	private function getUserProfiles(int $userId): array {
		$profiles = [];

		// Get user-specific social profiles from user meta
		foreach (self::SOCIAL_PLATFORMS as $platform => $baseUrl) {
			$metaKey = "rankingcoach_social_{$platform}";
			$profileData = get_user_meta($userId, $metaKey, true);

			if (!empty($profileData)) {
				// Handle full URL vs username scenarios
				if (filter_var($profileData, FILTER_VALIDATE_URL)) {
					$profiles[$platform] = $profileData;
				} elseif (!empty($baseUrl)) {
					$profiles[$platform] = $baseUrl . ltrim($profileData, '@/');
				} else {
					$profiles[$platform] = $profileData; // For personal websites
				}
			}
		}

		// Additional URLs from user meta
		$additionalUrls = get_user_meta($userId, 'rankingcoach_additional_social_urls', true);
		if (!empty($additionalUrls)) {
			$urls = array_filter(array_map('trim', explode("\n", $additionalUrls)));
			foreach ($urls as $index => $url) {
				if (filter_var($url, FILTER_VALIDATE_URL)) {
					$profiles["additional_{$index}"] = $url;
				}
			}
		}

		return array_filter($profiles);
	}

	/**
	 * Retrieves organization-level social profiles from plugin settings.
	 *
	 * @return array Array of social profile URLs.
	 */
	private function getOrganizationProfiles(): array {
		$options = SettingsManager::instance()->get_options();
		$profiles = [];

		// Get organization social profiles from plugin settings
		foreach (self::SOCIAL_PLATFORMS as $platform => $baseUrl) {
			$settingKey = "organization_social_{$platform}";
			
			if (isset($options[$settingKey]) && !empty($options[$settingKey])) {
				$profileData = $options[$settingKey];
				
				// Handle full URL vs username scenarios
				if (filter_var($profileData, FILTER_VALIDATE_URL)) {
					$profiles[$platform] = $profileData;
				} elseif (!empty($baseUrl)) {
					$profiles[$platform] = $baseUrl . ltrim($profileData, '@/');
				} else {
					$profiles[$platform] = $profileData; // For personal websites
				}
			}
		}

		// Additional organization URLs
		if (isset($options['organization_additional_social_urls']) && !empty($options['organization_additional_social_urls'])) {
			$urls = is_array($options['organization_additional_social_urls']) 
				? $options['organization_additional_social_urls']
				: array_filter(array_map('trim', explode("\n", $options['organization_additional_social_urls'])));
			
			foreach ($urls as $index => $url) {
				if (filter_var($url, FILTER_VALIDATE_URL)) {
					$profiles["additional_{$index}"] = $url;
				}
			}
		}

		return array_filter($profiles);
	}
}