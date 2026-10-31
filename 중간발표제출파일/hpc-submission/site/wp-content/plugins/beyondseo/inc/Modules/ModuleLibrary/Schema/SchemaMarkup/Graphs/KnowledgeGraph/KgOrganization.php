<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Graphs\KnowledgeGraph;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Exception;
use RankingCoach\Inc\Core\Helpers\CoreHelper;
use RankingCoach\Inc\Core\Settings\SettingsManager;
use RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Graphs;

/**
 * Knowledge Graph Organization graph class.
 */
class KgOrganization extends Graphs\Graph {

    use Graphs\Traits\Image;

    /**
     * Returns the graph data.
     *
     * @param object|null $graphData The graph data.
     * @return array $data The graph data.
     * @throws Exception
     */
	public function get($graphData = null): array
    {
        $options = SettingsManager::instance()->get_options();

		$homeUrl                 = trailingslashit( home_url() );
		$organizationName        = $options['organisation_or_person_name'];
		$organizationDescription = '';

		$data = [
			'@type'        => 'Organization',
			'@id'          => $homeUrl . '#organization',
			'name'         => $organizationName ?: CoreHelper::decode_html_entities(  get_bloginfo( 'name' ) ),
			'description'  => $organizationDescription,
			'url'          => $homeUrl,
			'email'        => $options['organisation_email'] ?? '',
			'telephone'    => $options['organisation_phone'] ?? '',
			'foundingDate' => $options['organisation_founding_date'] ?? ''
		];

		$numberOfEmployeesData = $options['organisation_number_of_employees'] ?? null;

		if (
			!empty($numberOfEmployeesData) &&
			$numberOfEmployeesData['isRange'] &&
			isset( $numberOfEmployeesData['from'] ) &&
			isset( $numberOfEmployeesData['to'] ) &&
			0 < $numberOfEmployeesData['to']
		) {
			$data['numberOfEmployees'] = [
				'@type'    => 'QuantitativeValue',
				'minValue' => $numberOfEmployeesData['from'],
				'maxValue' => $numberOfEmployeesData['to']
			];
		}

		if (
			!empty($numberOfEmployeesData) &&
			! $numberOfEmployeesData['isRange'] &&
			! empty( $numberOfEmployeesData['number'] )
		) {
			$data['numberOfEmployees'] = [
				'@type' => 'QuantitativeValue',
				'value' => $numberOfEmployeesData['number']
			];
		}

		$logo = $this->logo();
		if ( ! empty( $logo ) ) {
			$data['logo']  = $logo;
			$data['image'] = [ '@id' => $data['logo']['@id'] ];
		}

		return $data;
	}

    /**
     * Returns the logo data.
     *
     * @return array The logo data.
     * @throws Exception
     */
	public function logo() {
        $options = SettingsManager::instance()->get_options();
		
		// Priority 1: Organization logo from settings
		$logo = $options['organisation_logo'] ?? '';
		if ( $logo ) {
			return $this->image( $logo, 'organizationLogo' );
		}

		// Priority 2: Theme custom logo
		$imageId = get_theme_support('custom-logo') ? get_theme_mod('custom_logo') : false;
		if ( $imageId ) {
			return $this->image( $imageId, 'organizationLogo' );
		}

		return [];
	}
}