<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Breadcrumbs\Dtos;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * DTO for breadcrumbs request containing types and context information
 * @property string[] $types Array of breadcrumb types to generate
 * @property array[] $context Context information for breadcrumb generation
 * @property string $post_id Post ID for context (if applicable)
 */
class BreadcrumbsRequestDto
{
    /**
     * @var string[] Array of breadcrumb types to generate
     */
    public array $types;

    /**
     * @var array[] Context information for breadcrumb generation
     */
    public array $context;

    /**
     * @var string Post ID for context (if applicable)
     */
    public string $post_id;


    /**
     * Convert to array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'types' => $this->types,
            'context' => $this->context
        ];
    }

    /**
     * Create DTO from array
     *
     * @param array $data Data to populate the DTO
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->types = $data['types'] ?? [];
        $dto->context = $data['context'] ?? [];
        if (isset($data['post_id'])) {
            $dto->context['post_id'] = (string) $data['post_id'];
        }
        return $dto;
    }

    /**
     * Validate the request data
     *
     * @return array Array of validation errors, empty if valid
     */
    public function validate(): array
    {
        $errors = [];

        if (empty($this->types)) {
            $errors[] = 'Types array cannot be empty';
        }

        if (!is_array($this->types)) {
            $errors[] = 'Types must be an array';
        }

        if (!is_array($this->context)) {
            $errors[] = 'Context must be an array';
        }

        $validTypes = ['post', 'page', 'archive', 'search', '404', 'category', 'tag', 'term', 'home', 'date'];
        $invalidTypes = array_diff($this->types, $validTypes);
        if (!empty($invalidTypes)) {
            $errors[] = 'Invalid breadcrumb types: ' . implode(', ', $invalidTypes) . '. Valid types: ' . implode(', ', $validTypes);
        }

        return $errors;
    }
}