<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Graphs\Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Exception;
use RankingCoach\Inc\Core\Settings\SettingsManager;
use RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Graphs;
use RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\SchemaManager;
use RankingCoach\Inc\Modules\ModuleManager;

/**
 * Product graph class.
 */
class Product extends Graphs\Graph {

    use Graphs\Traits\Image;
    
    /**
     * Temporary graph data storage.
     *
     * @var object|null
     */
    private $graphData;

    /**
     * Returns the graph data.
     *
     * @param object|null $graphData The graph data.
     * @return array             The parsed graph data.
     * @throws Exception
     *
     */
	public function get( $graphData = null ): array {
		try {
			$this->graphData = $graphData;

			$data = $this->getCommonGraphData( $graphData );

			return $this->getDataWithOfferPriceData( $data, $graphData );
		} finally {
			// Clean up to prevent memory leaks
			$this->graphData = null;
		}
	}

    /**
     * The commong graph data for products.
     *
     * @param null $graphData The graph data.
     * @return array             The parsed graph data.
     * @throws Exception
     *
     */
	protected function getCommonGraphData( $graphData = null ) {

        /** @var ModuleManager $moduleManager */
        $moduleManager = ModuleManager::instance();
        /** @var SchemaManager $schema */
        $schema = $moduleManager->get_module('schemaMarkup')->schema;

		$data = [
			'@type'           => 'Product',
			'@id'             => ( $graphData && isset( $graphData->id ) && ! empty( $graphData->id ) ) 
				? esc_url( $schema->context['url'] ) . sanitize_key( $graphData->id ) 
				: esc_url( $schema->context['url'] ) . '#product',
			'name'            => ( $graphData && isset( $graphData->properties->name ) && ! empty( $graphData->properties->name ) ) 
				? sanitize_text_field( $graphData->properties->name ) 
				: get_the_title(),
			'description'     => ( $graphData && isset( $graphData->properties->description ) && ! empty( $graphData->properties->description ) ) 
				? wp_strip_all_tags( $graphData->properties->description ) 
				: get_the_excerpt(),
			'url'             => esc_url( $schema->context['url'] ),
			'brand'           => '',
			'sku'             => ( $graphData && isset( $graphData->properties->identifiers->sku ) && ! empty( $graphData->properties->identifiers->sku ) ) 
				? sanitize_text_field( $graphData->properties->identifiers->sku ) : '',
			'gtin'            => ( $graphData && isset( $graphData->properties->identifiers->gtin ) && ! empty( $graphData->properties->identifiers->gtin ) ) 
				? sanitize_text_field( $graphData->properties->identifiers->gtin ) : '',
			'mpn'             => ( $graphData && isset( $graphData->properties->identifiers->mpn ) && ! empty( $graphData->properties->identifiers->mpn ) ) 
				? sanitize_text_field( $graphData->properties->identifiers->mpn ) : '',
			'isbn'            => ( $graphData && isset( $graphData->properties->identifiers->isbn ) && ! empty( $graphData->properties->identifiers->isbn ) ) 
				? sanitize_text_field( $graphData->properties->identifiers->isbn ) : '',
			'material'        => ( $graphData && isset( $graphData->properties->attributes->material ) && ! empty( $graphData->properties->attributes->material ) ) 
				? sanitize_text_field( $graphData->properties->attributes->material ) : '',
			'color'           => ( $graphData && isset( $graphData->properties->attributes->color ) && ! empty( $graphData->properties->attributes->color ) ) 
				? sanitize_text_field( $graphData->properties->attributes->color ) : '',
			'pattern'         => ( $graphData && isset( $graphData->properties->attributes->pattern ) && ! empty( $graphData->properties->attributes->pattern ) ) 
				? sanitize_text_field( $graphData->properties->attributes->pattern ) : '',
			'size'            => ( $graphData && isset( $graphData->properties->attributes->size ) && ! empty( $graphData->properties->attributes->size ) ) 
				? sanitize_text_field( $graphData->properties->attributes->size ) : '',
			'energyRating'    => ( $graphData && isset( $graphData->properties->attributes->energyRating ) && ! empty( $graphData->properties->attributes->energyRating ) ) 
				? sanitize_text_field( $graphData->properties->attributes->energyRating ) : '',
			'image'           => ( $graphData && isset( $graphData->properties->image ) && ! empty( $graphData->properties->image ) ) 
				? $this->image( $graphData->properties->image ) 
				: $this->getFeaturedImage(),
			'aggregateRating' => null,
			'review'          => null,
			'audience'        => $this->getAudience()
		];

		if ( ! empty( $graphData->properties->brand ) ) {
			$data['brand'] = [
				'@type' => 'Brand',
				'name'  => $graphData->properties->brand
			];
		}

		return $data;
	}

	/**
	 * Returns data with offer price data.
	 *
	 * @param  array $data      The data array.
	 * @param  object $graphData The graph data.
	 * @return array            The data array.
	 */
	protected function getDataWithOfferPriceData( $data, $graphData = null )
    {
        $options = SettingsManager::instance()->get_options();

		if ( isset( $graphData->properties->offer->price ) && isset( $graphData->properties->offer->currency ) ) {
			$data['offers'] = [
				'@type'           => 'Offer',
				'price'           => ! empty( $graphData->properties->offer->price ) ? (float) $graphData->properties->offer->price : 0,
				'priceCurrency'   => ! empty( $graphData->properties->offer->currency ) ? $graphData->properties->offer->currency : '',
				'priceValidUntil' => ! empty( $graphData->properties->offer->validUntil )
					? gmdate( 'Y-m-d', strtotime( $graphData->properties->offer->validUntil ) )
					: '',
				'availability'    => ! empty( $graphData->properties->offer->availability ) ? $graphData->properties->offer->availability : 'https://schema.org/InStock',
				'shippingDetails' => $this->getShippingDetails()
			];

			if ( 'organization' === $options['site_represents'] ) {
				$homeUrl                  = trailingslashit( home_url() );
				$data['offers']['seller'] = [
					'@type' => 'Organization',
					'@id'   => $homeUrl . '#organization',
				];
			}
		}

		return $data;
	}

	/**
	 * Returns the intended audience.
	 *
	 * @return array The audience data.
	 */
	protected function getAudience() {
		if ( empty( $this->graphData->properties->audience->gender ) ) {
			return [];
		}

		return [
			'@type'           => 'PeopleAudience',
			'suggestedGender' => $this->graphData->properties->audience->gender,
			'suggestedMinAge' => (float) $this->graphData->properties->audience->minimumAge,
			'suggestedMaxAge' => (float) $this->graphData->properties->audience->maximumAge
		];
	}

	/**
	 * Returns the shipping details.
	 *
	 * @return array The shipping details.
	 */
	public function getShippingDetails() {
		if ( empty( $this->graphData->properties->shippingDestinations ) ) {
			return [];
		}

		$shippingDetails = [];
		foreach ( $this->graphData->properties->shippingDestinations as $shippingDestination ) {
			if ( empty( $shippingDestination->country ) ) {
				continue;
			}

			$shippingDetail = [
				'@type'               => 'OfferShippingDetails',
				'shippingRate'        => [
					'@type'    => 'MonetaryAmount',
					'value'    => ! empty( $shippingDestination->rate ) ? (float) $shippingDestination->rate : 0,
					'currency' => ! empty( $this->graphData->properties->offer->currency ) ? $this->graphData->properties->offer->currency : ''
				],
				'shippingDestination' => [
					'@type'          => 'DefinedRegion',
					'addressCountry' => $shippingDestination->country
				],
				'deliveryTime'        => $this->getDeliveryTime( $shippingDestination->deliveryTime ),
			];

			// States can't be combined with postal codes so it's either one or the other.
			if ( ! empty( $shippingDestination->states ) ) {
				$states = json_decode( $shippingDestination->states );
				$states = array_map( function ( $state ) {
					return $state->value;
				}, $states );
				$shippingDetail['shippingDestination']['addressRegion'] = $states;
			} elseif ( $shippingDestination->postalCodes ) {
				$postalCodes = json_decode( $shippingDestination->postalCodes );
				$postalCodes = array_map( function ( $postalCode ) {
					return $postalCode->value;
				}, $postalCodes );
				$shippingDetail['shippingDestination']['postalCode'] = $postalCodes;
			}

			$shippingDetails[] = $shippingDetail;
		}

		return $shippingDetails;
	}

	/**
	 * Returns the delivery time.
	 *
	 * @param  object|null $deliveryTime The unparsed delivery time data.
	 * @return array                     The delivery time schema data.
	 */
	protected function getDeliveryTime( $deliveryTime ) {
		if ( empty( $deliveryTime->handlingTime ) ) {
			return [];
		}

		if (
			! is_numeric( $deliveryTime->handlingTime->minValue )
			&& ! is_numeric( $deliveryTime->handlingTime->maxValue )
			&& ! is_numeric( $deliveryTime->transitTime->minValue )
			&& ! is_numeric( $deliveryTime->transitTime->maxValue )
		) {
			return [];
		}

		$schema = [
			'@type' => 'ShippingDeliveryTime'
		];

		if (
			is_numeric( $deliveryTime->handlingTime->minValue )
			&& is_numeric( $deliveryTime->handlingTime->maxValue )
		) {
			$schema['handlingTime'] = [
				'@type'    => 'QuantitativeValue',
				'minValue' => (int) $deliveryTime->handlingTime->minValue,
				'maxValue' => (int) $deliveryTime->handlingTime->maxValue,
				'unitCode' => ! empty( $deliveryTime->handlingTime->unitCode ) ? $deliveryTime->handlingTime->unitCode : 'DAY'
			];
		}

		if (
			is_numeric( $deliveryTime->transitTime->minValue )
			&& is_numeric( $deliveryTime->transitTime->maxValue )
		) {
			$schema['transitTime'] = [
				'@type'    => 'QuantitativeValue',
				'minValue' => (int) $deliveryTime->transitTime->minValue,
				'maxValue' => (int) $deliveryTime->transitTime->maxValue,
				'unitCode' => ! empty( $deliveryTime->transitTime->unitCode ) ? $deliveryTime->transitTime->unitCode : 'DAY'
			];
		}

		return $schema;
	}
}
