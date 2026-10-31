<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Breadcrumbs\Dtos;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * DTO for breadcrumbs response containing generated breadcrumbs for requested types
 * * @property array[] $breadcrumbs Array of breadcrumbs grouped by type
 * * @property array[] $meta Metadata about the response, including total types and timestamp
 */
class BreadcrumbsResponseDto
{
    /**
     * @var array Array of breadcrumbs grouped by type
     */
    public array $breadcrumbs;

    /**
     * @var array Metadata about the response
     */
    public array $meta;

    /**
     * Constructor
     *
     * @param array[] $breadcrumbs Array of breadcrumbs grouped by type
     * @param array[] $meta Metadata about the response
     */
    public function __construct(array $breadcrumbs = [], array $meta = [])
    {
        $this->breadcrumbs = $breadcrumbs;
        $this->meta = $meta;
    }

    /**
     * Add breadcrumbs for a specific type
     *
     * @param string $type The breadcrumb type
     * @param array $items Array of breadcrumb items
     * @return self
     */
    public function addBreadcrumbsForType(string $type, array $items): self
    {
        $this->breadcrumbs[$type] = $items;
        return $this;
    }

    /**
     * Add metadata
     *
     * @param string $key Meta key
     * @param mixed $value Meta value
     * @return self
     */
    public function addMeta(string $key, $value): self
    {
        $this->meta[$key] = $value;
        return $this;
    }

    /**
     * Convert to array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'breadcrumbs' => $this->breadcrumbs,
            'meta' => array_merge([
                'total_types' => count($this->breadcrumbs),
                'timestamp' => wp_date('Y-m-d H:i:s'),
                'generated_at' => time()
            ], $this->meta)
        ];
    }

    /**
     * Get breadcrumbs for a specific type
     *
     * @param string $type The breadcrumb type
     * @return array|null
     */
    public function getBreadcrumbsForType(string $type): ?array
    {
        return $this->breadcrumbs[$type] ?? null;
    }

    /**
     * Check if breadcrumbs exist for a type
     *
     * @param string $type The breadcrumb type
     * @return bool
     */
    public function hasBreadcrumbsForType(string $type): bool
    {
        return isset($this->breadcrumbs[$type]) && !empty($this->breadcrumbs[$type]);
    }

    /**
     * Get all types that have breadcrumbs
     *
     * @return array
     */
    public function getAvailableTypes(): array
    {
        return array_keys($this->breadcrumbs);
    }

    /**
     * Count total breadcrumb items across all types
     *
     * @return int
     */
    public function getTotalItemsCount(): int
    {
        $total = 0;
        foreach ($this->breadcrumbs as $items) {
            $total += is_array($items) ? count($items) : 0;
        }
        return $total;
    }
}