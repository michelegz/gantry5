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

use Joomla\CMS\Event\AbstractEvent;

/**
 * Generic extension point to rank content IDs from the outside.
 *
 * Dispatched as 'onContentRankIds' with the candidate IDs already filtered.
 * Any listener may contribute an id => score map via addScores(). A single
 * map passed as the 'scores' argument is accepted as well. Maps from all
 * listeners are merged with the highest score winning per ID, candidates
 * are ordered by score descending in a stable way and candidates without a
 * score are appended at the end. When no scores are provided the caller falls
 * back to its default ordering, so listeners are always optional.
 *
 * Generic subscriber skeleton (Joomla SubscriberInterface):
 *
 *   public static function getSubscribedEvents(): array
 *   {
 *       return ['onContentRankIds' => 'onContentRankIds'];
 *   }
 *
 *   public function onContentRankIds(ContentRankEvent $event): void
 *   {
 *       $event->addScores($this->score($event->getIds()));
 *   }
 *
 * The event only carries a fixed content scope ('context') and the candidate
 * IDs. Extra arguments may be added later without breaking listeners.
 */
class ContentRankEvent extends AbstractEvent
{
    /**
     * @param array $ids Candidate content IDs.
     * @param string $context Fixed content scope of the call.
     */
    public function __construct(array $ids, $context = 'com_content.articles')
    {
        parent::__construct('onContentRankIds', [
            'context' => (string) $context,
            'ids' => array_values(array_map('intval', $ids)),
            'results' => []
        ]);
    }

    /**
     * @return string
     */
    public function getContext()
    {
        return (string) $this->getArgument('context', '');
    }

    /**
     * @return int[]
     */
    public function getIds()
    {
        return array_values(array_map('intval', (array) $this->getArgument('ids', [])));
    }

    /**
     * Contribute an id => score map. Merged with the highest score per ID.
     *
     * @param array $map
     * @return void
     */
    public function addScores(array $map)
    {
        $results = (array) $this->getArgument('results', []);
        $results[] = $map;

        $this->setArgument('results', $results);
    }

    /**
     * Merged id => score map across all contributors.
     *
     * Collects every map added via addScores() plus a single map passed
     * as the 'scores' argument, so both shapes are accepted.
     *
     * @return array<int, int|float>
     */
    public function getScores()
    {
        $maps = (array) $this->getArgument('results', []);
        $single = $this->getArgument('scores', null);

        if (is_array($single)) {
            $maps[] = $single;
        }

        return ContentRanker::mergeScoreMaps($maps);
    }
}
