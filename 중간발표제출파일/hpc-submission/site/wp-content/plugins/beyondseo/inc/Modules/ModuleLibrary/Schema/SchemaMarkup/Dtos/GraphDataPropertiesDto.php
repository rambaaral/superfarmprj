<?php

declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Dtos;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Holds all optional properties used by schema graphs.
 */
class GraphDataPropertiesDto
{
    // Generic fields
    public ?string $name = null;
    public ?string $headline = null;
    public ?string $description = null;
    public ?string $image = null;
    public ?string $keywords = null;

    // Author
    public ?GraphDataAuthorDto $author = null;

    // Dates
    public ?GraphDataDatesDto $dates = null;

    // Job posting
    public ?string $employmentType = null;
    public ?bool $remote = null;
    public ?GraphDataOrganizationDto $hiringOrganization = null;
    /** @var GraphDataLocationDto[] */
    public array $locations = [];
    public ?GraphDataLocationDto $location = null;
    public ?GraphDataSalaryDto $salary = null;
    public ?GraphDataRequirementsDto $requirements = null;

    // Product
    public ?string $brand = null;
    public ?GraphDataIdentifiersDto $identifiers = null;
    public ?GraphDataAttributesDto $attributes = null;
    public ?GraphDataOfferDto $offer = null;
    /** @var GraphDataShippingDestinationDto[] */
    public array $shippingDestinations = [];
    public ?GraphDataAudienceDto $audience = null;
    /** @var GraphDataReviewDto[] */
    public array $reviews = [];
    public ?GraphDataRatingDto $rating = null;

    // Movie
    public ?string $director = null;
    public ?string $releaseDate = null;

    // Recipe
    public ?string $dishType = null;
    public ?string $cuisineType = null;
    public ?GraphDataTimeRequiredDto $timeRequired = null;
    public ?GraphDataNutritionDto $nutrition = null;
    public ?string $ingredients = null; // JSON string
    /** @var GraphDataInstructionDto[] */
    public array $instructions = [];

    // Video
    public ?string $contentUrl = null;
    public ?string $embedUrl = null;
    public ?string $thumbnailUrl = null;
    public ?string $uploadDate = null;
    public ?bool $familyFriendly = null;

    // Person
    public ?string $email = null;
    public ?string $jobTitle = null;
    public ?GraphDataLocationDto $personLocation = null;

    // Book
    /** @var GraphDataEditionDto[] */
    public array $editions = [];
}
