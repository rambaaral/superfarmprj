<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\AutoSetup\Onboarding\Collectors;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

abstract class AbstractCollector
{
    public int $id = 0;
    public string $collector = '';
    public array $settings = [];
    public string $className = '';
    public int $priority = 0;
    public bool $active = true;
    public bool $saveCollectedData = true;

    public function __construct(?int $id = null, array $settings = [])
    {
        $this->id = $id ?? 0;
        $this->settings = $settings;
    }

    public function getSetting(string $path, mixed $default = null): mixed
    {
        $keys = explode('.', $path);
        $value = $this->settings;

        foreach ($keys as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return $default;
            }
            $value = $value[$key];
        }

        return $value;
    }

    public function businessEmailAddress(): ?string
    {
        return null;
    }

    public function businessWebsiteUrl(): ?string
    {
        return null;
    }

    public function businessName(): ?string
    {
        return null;
    }

    public function businessDescription(): ?string
    {
        return null;
    }

    public function businessAddress(): ?string
    {
        return null;
    }

    public function businessGeoAddress(): ?string
    {
        return null;
    }

    public function businessServiceArea(): ?string
    {
        return null;
    }

    public function businessKeywords(): ?string
    {
        return null;
    }

    public function businessCategories(): ?string
    {
        return null;
    }

    public function businessSpecificDescription(): ?string
    {
        return null;
    }
}
