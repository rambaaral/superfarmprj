<?php

declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Dtos;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Example usage of the GraphDataDto factory method
 */
class GraphDataExample
{
    /**
     * Demonstrates how to create a fully initialized GraphDataDto object
     * and populate it with data for different schema types.
     *
     * @return GraphDataDto
     */
    public static function createExampleGraphData(): GraphDataDto
    {
        // Create fully initialized object with all properties set to null/empty
        $graphData = GraphDataDto::createFullyInitialized();
        
        // Now you can populate any properties you need
        // Example for FAQ schema:
        $graphData->id = 'faq-example';
        $graphData->question = 'What is schema markup?';
        $graphData->answer = 'Schema markup is structured data that helps search engines understand your content.';
        
        // Example for Article schema:
        $graphData->properties->name = 'Example Article Title';
        $graphData->properties->headline = 'This is an example headline';
        $graphData->properties->description = 'This is an example description for the article.';
        $graphData->properties->keywords = '["SEO", "Schema", "Markup"]'; // JSON string
        
        // Author information
        $graphData->properties->author->name = 'John Doe';
        $graphData->properties->author->url = 'https://example.com/author/john-doe';
        
        // Date information
        $graphData->properties->dates->include = true;
        $graphData->properties->dates->datePublished = '2024-01-15';
        $graphData->properties->dates->dateModified = '2024-01-20';
        
        // Example for JobPosting schema:
        $graphData->properties->employmentType = 'FULL_TIME';
        $graphData->properties->remote = false;
        
        // Hiring organization
        $graphData->properties->hiringOrganization->name = 'Example Company';
        $graphData->properties->hiringOrganization->url = 'https://example.com';
        
        // Location for on-site positions
        $graphData->properties->location->streetAddress = '123 Main Street';
        $graphData->properties->location->locality = 'New York';
        $graphData->properties->location->postalCode = '10001';
        $graphData->properties->location->region = 'NY';
        $graphData->properties->location->country = 'US';
        
        // Salary information
        $graphData->properties->salary->minimum = '50000';
        $graphData->properties->salary->maximum = '80000';
        $graphData->properties->salary->interval = 'YEAR';
        $graphData->properties->salary->currency = 'USD';
        
        // Requirements
        $graphData->properties->requirements->experienceInsteadOfEducation = false;
        $graphData->properties->requirements->experience = '3+ years';
        $graphData->properties->requirements->degree = 'Bachelor';
        
        // Example for Product schema:
        $graphData->properties->brand = 'Example Brand';
        
        // Product identifiers
        $graphData->properties->identifiers->sku = 'EX-12345';
        $graphData->properties->identifiers->gtin = '1234567890123';
        $graphData->properties->identifiers->mpn = 'MPN-12345';
        
        // Product attributes
        $graphData->properties->attributes->material = 'Cotton';
        $graphData->properties->attributes->color = 'Blue';
        $graphData->properties->attributes->size = 'Large';
        
        // Offer information
        $graphData->properties->offer->price = '29.99';
        $graphData->properties->offer->currency = 'USD';
        $graphData->properties->offer->availability = 'InStock';
        $graphData->properties->offer->validUntil = '2024-12-31';
        
        // Example for Recipe schema:
        $graphData->properties->dishType = 'Main Course';
        $graphData->properties->cuisineType = 'Italian';
        $graphData->properties->ingredients = '["Pasta", "Tomato Sauce", "Cheese"]'; // JSON string
        
        // Time required
        $graphData->properties->timeRequired->preparation = 'PT15M'; // 15 minutes
        $graphData->properties->timeRequired->cooking = 'PT30M'; // 30 minutes
        
        // Nutrition information
        $graphData->properties->nutrition->servings = '4';
        $graphData->properties->nutrition->calories = '350';
        
        // Instructions (first item in array)
        $graphData->properties->instructions[0]->name = 'Step 1';
        $graphData->properties->instructions[0]->text = 'Boil water in a large pot.';
        
        // Example for Video schema:
        $graphData->properties->contentUrl = 'https://example.com/video.mp4';
        $graphData->properties->embedUrl = 'https://example.com/embed/video';
        $graphData->properties->thumbnailUrl = 'https://example.com/thumbnail.jpg';
        $graphData->properties->uploadDate = '2024-01-15';
        $graphData->properties->familyFriendly = true;
        
        // Example for Person schema:
        $graphData->properties->email = 'john@example.com';
        $graphData->properties->jobTitle = 'Software Developer';
        
        // Person location (reusing location structure)
        $graphData->properties->personLocation->streetAddress = '456 Oak Avenue';
        $graphData->properties->personLocation->locality = 'San Francisco';
        $graphData->properties->personLocation->postalCode = '94102';
        $graphData->properties->personLocation->region = 'CA';
        $graphData->properties->personLocation->country = 'US';
        
        return $graphData;
    }
    
    /**
     * Example of how to use the GraphDataDto in a Graph class get() method
     *
     * @param GraphDataDto|null $graphData
     * @return array
     */
    public static function exampleGraphUsage(?GraphDataDto $graphData = null): array
    {
        // If no graph data provided, create a default one
        if ($graphData === null) {
            $graphData = GraphDataDto::createFullyInitialized();
        }
        
        // Use the graph data to build schema
        $schema = [
            '@type' => 'Article',
            '@id' => $graphData->id ?: '#article',
            'headline' => $graphData->properties->name,
            'description' => $graphData->properties->description,
        ];
        
        // Add author if available
        if ($graphData->properties->author && $graphData->properties->author->name) {
            $schema['author'] = [
                '@type' => 'Person',
                'name' => $graphData->properties->author->name,
                'url' => $graphData->properties->author->url
            ];
        }
        
        // Add dates if available
        if ($graphData->properties->dates && $graphData->properties->dates->include) {
            if ($graphData->properties->dates->datePublished) {
                $schema['datePublished'] = $graphData->properties->dates->datePublished;
            }
            if ($graphData->properties->dates->dateModified) {
                $schema['dateModified'] = $graphData->properties->dates->dateModified;
            }
        }
        
        return $schema;
    }
}
