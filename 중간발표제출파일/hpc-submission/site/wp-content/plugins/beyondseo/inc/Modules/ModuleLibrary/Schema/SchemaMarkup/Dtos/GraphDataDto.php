<?php

declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Dtos;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Data container representing optional graph configuration.
 */
class GraphDataDto
{
    public ?string $id = null;
    public ?string $question = null;
    public ?string $answer = null;

    public GraphDataPropertiesDto $properties;

    public function __construct()
    {
        $this->properties = new GraphDataPropertiesDto();
    }

    /**
     * Creates a fully initialized GraphDataDto object with all properties set to null/empty.
     * This factory method instantiates all nested DTOs and arrays to provide a complete
     * object structure that matches the expected schema format.
     *
     * @return GraphDataDto Fully initialized GraphDataDto object
     */
    public static function createFullyInitialized(): GraphDataDto
    {
        $graphData = new self();

        // Initialize all nested DTOs in properties
        $graphData->properties->author = new GraphDataAuthorDto();
        $graphData->properties->dates = new GraphDataDatesDto();
        $graphData->properties->hiringOrganization = new GraphDataOrganizationDto();
        $graphData->properties->location = new GraphDataLocationDto();
        $graphData->properties->salary = new GraphDataSalaryDto();
        $graphData->properties->requirements = new GraphDataRequirementsDto();
        $graphData->properties->identifiers = new GraphDataIdentifiersDto();
        $graphData->properties->attributes = new GraphDataAttributesDto();
        $graphData->properties->offer = new GraphDataOfferDto();
        $graphData->properties->audience = new GraphDataAudienceDto();
        $graphData->properties->rating = new GraphDataRatingDto();
        $graphData->properties->timeRequired = new GraphDataTimeRequiredDto();
        $graphData->properties->nutrition = new GraphDataNutritionDto();
        $graphData->properties->personLocation = new GraphDataLocationDto();

        // Initialize array properties with sample structures
        $graphData->properties->locations = [
            new GraphDataLocationDto()
        ];

        $graphData->properties->shippingDestinations = [
            self::createShippingDestination()
        ];

        $graphData->properties->reviews = [
            new GraphDataReviewDto()
        ];

        $graphData->properties->instructions = [
            new GraphDataInstructionDto()
        ];

        $graphData->properties->editions = [
            new GraphDataEditionDto()
        ];

        return $graphData;
    }

    /**
     * Creates a fully initialized shipping destination with delivery time structure.
     *
     * @return GraphDataShippingDestinationDto
     */
    private static function createShippingDestination(): GraphDataShippingDestinationDto
    {
        $destination = new GraphDataShippingDestinationDto();
        $destination->deliveryTime = new GraphDataDeliveryTimeDto();
        $destination->deliveryTime->handlingTime = new GraphDataQuantitativeValueDto();
        $destination->deliveryTime->transitTime = new GraphDataQuantitativeValueDto();

        return $destination;
    }
}
