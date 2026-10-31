# GraphDataDto Factory Method Usage

## Overview

The `GraphDataDto::createFullyInitialized()` factory method creates a complete GraphDataDto object with all properties and nested DTOs instantiated and set to null/empty values. This provides a fully structured object that matches the expected schema format.

## Usage

### Basic Usage

```php
use RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Dtos\GraphDataDto;

// Create a fully initialized GraphDataDto object
$graphData = GraphDataDto::createFullyInitialized();

// Now all properties are available and can be populated as needed
$graphData->id = 'custom-id';
$graphData->question = 'What is this?';
$graphData->answer = 'This is an example.';

// All nested properties are also available
$graphData->properties->name = 'Example Name';
$graphData->properties->description = 'Example Description';
$graphData->properties->author->name = 'John Doe';
$graphData->properties->author->url = 'https://example.com/author';
```

### Using in Graph Classes

```php
// In your Graph class get() method
public function get($graphData = null): array {
    // If no graphData provided, create a fully initialized one
    if ($graphData === null) {
        $graphData = GraphDataDto::createFullyInitialized();
    }
    
    // Use the graphData to build your schema
    $schema = [
        '@type' => 'Article',
        '@id' => $graphData->id ?: '#article',
        'headline' => $graphData->properties->name,
        'description' => $graphData->properties->description,
    ];
    
    // Add conditional properties
    if ($graphData->properties->author && $graphData->properties->author->name) {
        $schema['author'] = [
            '@type' => 'Person',
            'name' => $graphData->properties->author->name,
            'url' => $graphData->properties->author->url
        ];
    }
    
    return $schema;
}
```

## Available Properties

The factory method initializes all the following structures:

### Main Properties
- `id` - Custom @id fragment
- `question` - Used by FAQ block
- `answer` - Used by FAQ block

### Generic Properties
- `properties->name` - Name/title
- `properties->headline` - Headline
- `properties->description` - Description
- `properties->image` - Image URL/ID
- `properties->keywords` - JSON string of keywords

### Author Information
- `properties->author->name` - Author name
- `properties->author->url` - Author URL

### Date Information
- `properties->dates->include` - Whether to include dates (default: true)
- `properties->dates->datePublished` - Publication date
- `properties->dates->dateModified` - Modification date
- `properties->dates->datePosted` - Posted date (for jobs)
- `properties->dates->dateExpires` - Expiration date (for jobs)

### JobPosting Fields
- `properties->employmentType` - Employment type
- `properties->remote` - Remote work flag
- `properties->hiringOrganization` - Organization details
- `properties->locations[]` - Array of remote locations
- `properties->location` - On-site location
- `properties->salary` - Salary information
- `properties->requirements` - Job requirements

### Product Fields
- `properties->brand` - Product brand
- `properties->identifiers` - SKU, GTIN, MPN, ISBN
- `properties->attributes` - Material, color, size, etc.
- `properties->offer` - Price and availability
- `properties->shippingDestinations[]` - Shipping information
- `properties->audience` - Target audience

### Review and Rating
- `properties->reviews[]` - Array of reviews
- `properties->rating` - Rating information

### Recipe Fields
- `properties->dishType` - Type of dish
- `properties->cuisineType` - Cuisine type
- `properties->timeRequired` - Preparation and cooking time
- `properties->nutrition` - Nutritional information
- `properties->ingredients` - JSON string of ingredients
- `properties->instructions[]` - Array of cooking instructions

### Video Fields
- `properties->contentUrl` - Video content URL
- `properties->embedUrl` - Embed URL
- `properties->thumbnailUrl` - Thumbnail URL
- `properties->uploadDate` - Upload date
- `properties->familyFriendly` - Family-friendly flag

### Person Fields
- `properties->email` - Email address
- `properties->jobTitle` - Job title
- `properties->personLocation` - Person's address

### Book Fields
- `properties->editions[]` - Array of book editions

## Example

See `GraphDataExample.php` for a complete example of how to use the factory method and populate the various properties for different schema types.
