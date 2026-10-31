<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Exception;

/**
 * BaseException
 */
abstract class BaseException extends Exception {

    /**
     * @var bool Whether to throw the exception instead of handling to render as a custom exception.
     */
    public bool $throwException = false;

    /**
	 * BaseException constructor.
	 */
	public function __construct(string $message = '', int $code = 0, ?Exception $previous = null) {
		parent::__construct($message, $code, $previous);
	}

	/**
	 * Get the title for the error.
	 */
	abstract public function getTitle(): string;

	/**
	 * Get the description for the error.
	 */
	abstract public function getDescription(): string;

	/**
	 * Get the reasons for the error.
	 */
	abstract public function getReasons(): array;

    /**
     * Determine if the exception should be thrown.
     */
    public function throwException(bool $allowToThrow = false): Exception
    {
        $this->throwException = $allowToThrow;
        return $this;
    }

	/**
	 * Determine if the footer should be shown.
	 */
	public function shouldShowFooter(): bool
	{
		return true; // Default behavior
	}

	/**
	 * Get additional styles.
	 */
	public function getStyles(): string
	{
		return ''; // Allow child exceptions to override
	}

	/**
	 * Get the footer content.
	 */
	public function getFooter(): string {
		return '';
	}
}
