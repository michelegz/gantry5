<?php

/**
 * @package   Gantry5
 * @author    Tiger12 http://tiger12.com
 * @originalCreator  RocketTheme (Gantry Framework)
 * @currentDeveloper  Tiger12, LLC
 * @copyright Copyright (C) 2007 - 2022 Tiger12, LLC
 * @license   GNU/GPLv2 and later
 *
 * http://www.gnu.org/licenses/gpl-2.0.html
 */

namespace Gantry\Joomla\Content;

/**
 * Per-request registry of article IDs already rendered by contentarray particles.
 *
 * First rendered particle wins: later instances with the "exclude already shown"
 * option skip these IDs. The registry lives only for the current PHP request,
 * so AJAX reloads (single particle renders) start empty on purpose.
 */
class ContentArraySeen
{
    /** @var int[] */
    protected static $ids = [];

    /**
     * @return int[]
     */
    public static function get()
    {
        return array_values(array_unique(array_map('intval', static::$ids)));
    }

    /**
     * @param int|int[] $ids
     * @return int[]
     */
    public static function add($ids)
    {
        foreach ((array) $ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                static::$ids[] = $id;
            }
        }

        static::$ids = array_values(array_unique(static::$ids));

        return static::get();
    }

    /**
     * @return void
     */
    public static function clear()
    {
        static::$ids = [];
    }
}
