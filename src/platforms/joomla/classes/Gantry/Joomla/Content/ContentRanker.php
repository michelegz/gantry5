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
 * Dependency-free helpers to merge and sort external ranking scores.
 *
 * Kept free of framework and CMS dependencies on purpose so the logic
 * stays unit testable in isolation.
 */
class ContentRanker
{
    /**
     * Merge score maps from any number of responders into id => score.
     *
     * Keys are normalized to positive integers, non numeric and non finite
     * scores (e.g. NaN, which would poison the sort) are ignored and on
     * duplicates the highest score wins.
     *
     * @param array $maps List of id => score maps.
     * @return array<int, int|float>
     */
    public static function mergeScoreMaps(array $maps)
    {
        $scores = [];

        foreach ($maps as $map) {
            if (!is_array($map)) {
                continue;
            }

            foreach ($map as $id => $score) {
                $id = (int) $id;

                if ($id <= 0 || !is_numeric($score)) {
                    continue;
                }

                $score = $score + 0;

                if (is_nan($score)) {
                    continue;
                }

                if (!isset($scores[$id]) || $score > $scores[$id]) {
                    $scores[$id] = $score;
                }
            }
        }

        return $scores;
    }

    /**
     * Order candidate IDs by score, highest first.
     *
     * The sort is stable: ties keep the original relative order and IDs
     * without a score are appended at the end in their original order.
     * Duplicate IDs are collapsed to their first occurrence.
     *
     * @param array $ids Candidate IDs in their original order.
     * @param array $scores id => score map (single map, already merged).
     * @return int[]
     */
    public static function sortRanked(array $ids, array $scores)
    {
        $clean = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0 && !isset($clean[$id])) {
                $clean[$id] = true;
            }
        }

        $ids = array_keys($clean);
        $scores = static::mergeScoreMaps([$scores]);

        $decorated = [];
        foreach ($ids as $index => $id) {
            $decorated[] = [$id, $scores[$id] ?? null, $index];
        }

        usort($decorated, static function ($a, $b) {
            if ($a[1] === null && $b[1] === null) {
                return $a[2] <=> $b[2];
            }
            if ($a[1] === null) {
                return 1;
            }
            if ($b[1] === null) {
                return -1;
            }

            // Always numeric: string comparison would order 1-11-2-12.
            $compared = (float) $b[1] <=> (float) $a[1];

            return $compared !== 0 ? $compared : ($a[2] <=> $b[2]);
        });

        return array_map(static function ($row) {
            return $row[0];
        }, $decorated);
    }
}
