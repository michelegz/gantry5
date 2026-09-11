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
     * Both the flat shape [id => float] and the rich shape
     * [id => ['growth' => float, 'delta' => int]] are accepted; rich pairs
     * contribute their growth here (use extractDeltas() for the tiebreak).
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

                if (is_array($score)) {
                    $score = isset($score['growth']) ? $score['growth'] : null;
                }

                if ($id <= 0 || !is_numeric($score)) {
                    continue;
                }

                $score = $score + 0;

                // Ignore non-finite scores (NaN, INF) which would poison the sort.
                $floatScore = (float) $score;
                if (is_nan($floatScore) || !is_finite($floatScore)) {
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
     * Extract per-article delta tiebreakers from any number of score maps.
     *
     * Flat [id => float] entries contribute 0, rich [id => ['growth', 'delta']]
     * entries contribute their (int) delta. Highest delta wins per ID, mirroring
     * mergeScoreMaps(). Entries with invalid/non-finite growth are skipped so the
     * tiebreakers stay aligned with the merged scores.
     *
     * @param array $maps List of id => score maps (flat and/or rich).
     * @return array<int, int>
     */
    public static function extractDeltas(array $maps)
    {
        $deltas = [];

        foreach ($maps as $map) {
            if (!is_array($map)) {
                continue;
            }

            foreach ($map as $id => $score) {
                $id = (int) $id;

                if ($id <= 0) {
                    continue;
                }

                if (is_array($score)) {
                    $growth = isset($score['growth']) ? $score['growth'] : null;

                    if (!is_numeric($growth)) {
                        continue;
                    }

                    $growth = $growth + 0;
                    $floatGrowth = (float) $growth;

                    if (is_nan($floatGrowth) || !is_finite($floatGrowth)) {
                        continue;
                    }

                    $delta = (int) (isset($score['delta']) ? $score['delta'] : 0);
                } else {
                    if (!is_numeric($score)) {
                        continue;
                    }

                    $flat = $score + 0;

                    if (is_nan((float) $flat) || !is_finite((float) $flat)) {
                        continue;
                    }

                    $delta = 0;
                }

                if (!isset($deltas[$id]) || $delta > $deltas[$id]) {
                    $deltas[$id] = $delta;
                }
            }
        }

        return $deltas;
    }

    /**
     * Order candidate IDs by score, highest first.
     *
     * The sort is stable: ties keep the original relative order and IDs
     * without a score are appended at the end in their original order.
     * Duplicate IDs are collapsed to their first occurrence. An optional
     * per-ID tiebreaker map (e.g. from extractDeltas()) orders equal scores
     * by tiebreaker descending before falling back to candidate order.
     *
     * @param array $ids Candidate IDs in their original order.
     * @param array $scores id => score map (single map, already merged).
     * @param array $tiebreakers Optional id => int map for equal scores.
     * @return int[]
     */
    public static function sortRanked(array $ids, array $scores, array $tiebreakers = [])
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
            $decorated[] = [$id, $scores[$id] ?? null, $index, isset($tiebreakers[$id]) ? (int) $tiebreakers[$id] : 0];
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

            if ($compared !== 0) {
                return $compared;
            }

            $tied = (int) $b[3] <=> (int) $a[3];

            return $tied !== 0 ? $tied : ($a[2] <=> $b[2]);
        });

        return array_map(static function ($row) {
            return $row[0];
        }, $decorated);
    }
}
