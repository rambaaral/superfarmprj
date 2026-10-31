<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Graphs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Exception;
use RankingCoach\Inc\Core\Settings\SettingsManager;
use RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Graphs;
use RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\SchemaManager;
use RankingCoach\Inc\Modules\ModuleManager;

/**
 * JobPosting graph class.
 */
class JobPosting extends Graphs\Graph {

    use Graphs\Traits\Image;
    /**
     * Returns the graph data.
     *
     * @param object|null $graphData The graph data.
     * @return array             The parsed graph data.
     * @throws Exception
     *
     */
	public function get( $graphData = null ): array {

        $options = SettingsManager::instance()->get_options();

        /** @var ModuleManager $moduleManager */
        $moduleManager = ModuleManager::instance();
        /** @var SchemaManager $schema */
        $schema = $moduleManager->get_module('schemaMarkup')->schema;

		$data = [
			'@type'                         => 'JobPosting',
			'@id'                           => ! empty( $graphData->id ) ? $schema->context['url'] . $graphData->id : $schema->context['url'] . '#jobPosting',
			'title'                         => ! empty( $graphData->properties->name ) ? $graphData->properties->name : get_the_title(),
			'description'                   => ! empty( $graphData->properties->description ) ? $graphData->properties->description : $schema->context['description'],
			'employmentType'                => ! empty( $graphData->properties->employmentType ) ? $graphData->properties->employmentType : '',
			'jobLocationType'               => ! empty( $graphData->properties->remote ) ? 'TELECOMMUTE' : '',
			'datePosted'                    => '',
			'validThrough'                  => '',
			'hiringOrganization'            => [],
			'jobLocation'                   => [],
			'applicantLocationRequirements' => [],
			'baseSalary'                    => [],
			'educationRequirements'         => [],
			'experienceRequirements'        => [],
			'experienceInPlaceOfEducation'  => ! empty( $graphData->properties->requirements->experienceInsteadOfEducation )
				? $graphData->properties->requirements->experienceInsteadOfEducation
				: false
		];

		if ( ! empty( $graphData->properties->dates ) ) {
            $post = is_singular() ? get_post( get_the_ID() ) : null;

			$data['datePosted'] = ! empty( $graphData->properties->dates->datePosted )
				? mysql2date( DATE_W3C, $graphData->properties->dates->datePosted, false )
				: mysql2date( DATE_W3C, $post->post_date, false );

			$data['validThrough'] = ! empty( $graphData->properties->dates->dateExpires )
				? mysql2date( DATE_W3C, $graphData->properties->dates->dateExpires, false )
				: '';
		}

		if ( ! empty( $graphData->properties->hiringOrganization ) ) {
			$data['hiringOrganization'] = [
				'@type'  => 'Organization',
				'name'   => ! empty( $graphData->properties->hiringOrganization->name ) ? $graphData->properties->hiringOrganization->name : '',
				'sameAs' => ! empty( $graphData->properties->hiringOrganization->url ) ? $graphData->properties->hiringOrganization->url : '',
				'logo'   => ! empty( $graphData->properties->hiringOrganization->image ) ? $this->image( $graphData->properties->hiringOrganization->image ) : ''
			];

			// If name is empty, fall back to the global one.
			if (
				empty( $graphData->properties->hiringOrganization->name ) &&
				'organization' === $options['site_represents']
			) {
				$homeUrl                    = trailingslashit( home_url() );
				$data['hiringOrganization'] = [
					'@type' => 'Organization',
					'@id'   => $homeUrl . '#organization',
				];
			}
		}

		if ( ! empty( $graphData->properties->remote ) && ! empty( $graphData->properties->locations ) ) {
			foreach ( $graphData->properties->locations as $location ) {
				if ( empty( $location->type ) || empty( $location->name ) ) {
					continue;
				}

				$data['applicantLocationRequirements'][] = [
					'@type' => $location->type,
					'name'  => $location->name
				];
			}
		}

		if ( empty( $graphData->properties->remote ) && ! empty( $graphData->properties->location ) ) {
			$data['jobLocation'] = [
				'@type'   => 'Place',
				'address' => [
					'streetAddress'   => ! empty( $graphData->properties->location->streetAddress ) ? $graphData->properties->location->streetAddress : '',
					'addressLocality' => ! empty( $graphData->properties->location->locality ) ? $graphData->properties->location->locality : '',
					'postalCode'      => ! empty( $graphData->properties->location->postalCode ) ? $graphData->properties->location->postalCode : '',
					'addressRegion'   => ! empty( $graphData->properties->location->region ) ? $graphData->properties->location->region : '',
					'addressCountry'  => ! empty( $graphData->properties->location->country ) ? $graphData->properties->location->country : ''
				]
			];
		}

		if (
			! empty( $graphData->properties->salary->minimum ) &&
			! empty( $graphData->properties->salary->maximum ) &&
			! empty( $graphData->properties->salary->interval )
		) {
			$data['baseSalary'] = [
				'@type'    => 'MonetaryAmount',
				'currency' => ! empty( $graphData->properties->salary->currency ) ? $graphData->properties->salary->currency : '',
				'value'    => [
					'@type'    => 'QuantitativeValue',
					'minValue' => ! empty( $graphData->properties->salary->minimum ) ? $graphData->properties->salary->minimum : 0,
					'maxValue' => ! empty( $graphData->properties->salary->maximum ) ? $graphData->properties->salary->maximum : 0,
					'unitText' => ! empty( $graphData->properties->salary->interval ) ? $graphData->properties->salary->interval : ''
				]
			];
		}

		if ( ! empty( $graphData->properties->requirements->experience ) ) {
			$data['experienceRequirements'] = [
				'@type'              => 'OccupationalExperienceRequirements',
				'monthsOfExperience' => $graphData->properties->requirements->experience
			];
		}

		if ( ! empty( $graphData->properties->requirements->degree ) ) {
			$data['educationRequirements'] = [
				'@type'              => 'EducationalOccupationalCredential',
				'credentialCategory' => $graphData->properties->requirements->degree
			];
		}

		return $data;
	}
}