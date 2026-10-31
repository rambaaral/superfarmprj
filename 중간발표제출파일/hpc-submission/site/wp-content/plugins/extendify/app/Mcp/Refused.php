<?php

/**
 * Thrown when a tool call tries something a connection may not do.
 */

namespace Extendify\Mcp;

defined('ABSPATH') || die('No direct access.');

/**
 * A pre_update_option filter can only return a value, so refusing means throwing.
 */
class Refused extends \RuntimeException
{
    /**
     * @var string
     */
    private $reason;

    /**
     * @param string $message - What the model reads.
     * @param string $reason  - The code the usage ping may send in place of the message.
     */
    public function __construct($message, $reason)
    {
        parent::__construct($message);
        $this->reason = $reason;
    }

    /**
     * @return string
     */
    public function reason()
    {
        return $this->reason;
    }
}
