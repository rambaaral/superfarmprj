<?php

/**
 * Whether connections work on this site at all.
 */

namespace Extendify\Mcp;

defined('ABSPATH') || die('No direct access.');

use Extendify\Mcp\OAuth\Metadata;
use Extendify\PartnerData;

/**
 * An administrator can undo their own switch, never the partner's.
 */
class Availability
{
    // phpcs:ignore PSR12.Properties.ConstantVisibility.NotFound
    const OPTION = 'extendify_mcp_turned_off';

    /**
     * @return boolean
     */
    public static function offered()
    {
        // A blocked partner's last mcpConfig would otherwise keep every token working.
        return PartnerData::setting('license') === 'active' && Allowed::reads() !== Allowed::NONE;
    }

    /**
     * @return boolean
     */
    public static function live()
    {
        return self::offered() && !self::turnedOff() && !self::blocker();
    }

    /**
     * MCP clients build discovery URLs from the issuer's path and drop its query.
     * Over http, sign-in codes travel unencrypted; local hosts stay usable for development.
     *
     * @return string|null permalinks or http; null when nothing in the site's setup stops a connection.
     */
    public static function blocker()
    {
        if (\wp_parse_url(Metadata::issuer(), PHP_URL_QUERY)) {
            return 'permalinks';
        }

        $url = Metadata::resource();
        if (\wp_parse_url($url, PHP_URL_SCHEME) === 'https' || Reachability::isLocal($url)) {
            return null;
        }

        return 'http';
    }

    /**
     * @return array|null
     */
    public static function turnedOff()
    {
        $off = \get_option(self::OPTION);

        return is_array($off) ? $off : null;
    }

    /**
     * @param integer $userId - The administrator turning connections off.
     * @return void
     */
    public static function turnOff($userId)
    {
        \update_option(self::OPTION, ['by' => (int) $userId, 'at' => time()], false);
    }

    /**
     * @return void
     */
    public static function turnOn()
    {
        \delete_option(self::OPTION);
    }
}
