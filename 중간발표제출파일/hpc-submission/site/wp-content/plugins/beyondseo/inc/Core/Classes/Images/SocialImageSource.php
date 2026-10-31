<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Classes\Images;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class SocialImageSource
 * Represents a social image source with its metadata
 */
final class SocialImageSource
{
    /**
     * The descriptive label of the image source
     * @var string
     */
    private string $label;

    /**
     * The URL value of the image
     * @var string|null
     */
    private ?string $value;

    /**
     * The source identifier (e.g., 'default', 'featured', etc.)
     * @var string
     */
    private string $source;

    /**
     * Indicates if this is the default source
     * @var bool
     */
    private bool $default;

    /**
     * @param string $label The descriptive label of the image source
     * @param string|null $value The URL value of the image
     * @param string $source The source identifier (e.g., 'default', 'featured', etc.)
     * @param bool $default Indicates if this is the default source
     */
    public function __construct(
        string $label,
        ?string $value,
        string $source,
        bool $default = false
    ) {
        $this->label = $label;
        $this->value = $value;
        $this->source = $source;
        $this->default = $default;
    }

    /**
     * Gets the descriptive label of the image source
     * @return string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * Gets the URL value of the image
     * @return string|null
     */
    public function getValue(): ?string
    {
        return $this->value;
    }

    /**
     * Gets the source identifier
     * @return string
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * Checks if this is the default source
     * @return bool
     */
    public function isDefault(): bool
    {
        return $this->default;
    }

    /**
     * Converts the object to an array representation
     * 
     * @return array The array representation of the social image source
     */
    public function toArray(): array
    {
        return [
            'label' => $this->getLabel(),
            'value' => $this->getValue(),
            'source' => $this->getSource(),
            'default' => $this->isDefault(),
        ];
    }

    /**
     * Creates a SocialImageSource from an array
     * 
     * @param array $data The array containing source data
     * @return self The created SocialImageSource object
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['label'] ?? '',
            $data['value'] ?? null,
            $data['source'] ?? '',
            $data['default'] ?? false
        );
    }
}
