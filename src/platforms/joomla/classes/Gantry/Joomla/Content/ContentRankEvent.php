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
 * Both the flat shape [id => float] and the rich shape
 * [id => ['growth' => float, 'delta' => int]] are accepted; rich pairs are
 * merged on growth with delta kept as tiebreaker (see ContentRanker).
 *
 * For interoperability with listeners written against the ResultAware
 * convention, addResult() is accepted as an alias of addScores() and
 * getResult() exposes the contributed maps.
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
     * Plain argument read, bypassing Joomla's onGet<Name>/get<Name>
     * preprocessing.
     *
     * Joomla\CMS\Event\AbstractEvent::getArgument() dispatches to a
     * get<ArgumentName>() method when one exists on the event. Our own
     * accessors below (getIds, getContext, getScores) would therefore call
     * themselves forever: getArgument('ids') -> getIds() -> getArgument...
     * (white page: the request recurses until memory is exhausted). Reading
     * the arguments array directly breaks the cycle.
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function getArgument($name, $default = null)
    {
        return $this->arguments[$name] ?? $default;
    }

    /**
     * Plain argument write, bypassing the onSet<Name>/set<Name> preprocessing
     * for the same reason (no such hooks are defined here; behavior is
     * otherwise identical).
     *
     * @param string $name
     * @param mixed $value
     * @return $this
     */
    public function setArgument($name, $value)
    {
        $this->arguments[$name] = $value;

        return $this;
    }

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
     * Accepts both the flat [id => float] and the rich
     * [id => ['growth' => float, 'delta' => int]] shape.
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
     * Alias of addScores() for listeners written against the ResultAware
     * convention ($event->addResult($map)).
     *
     * @param array $map
     * @return void
     */
    public function addResult(array $map)
    {
        $this->addScores($map);
    }

    /**
     * The contributed maps, one per contributor (ResultAware-compatible read).
     *
     * @return array
     */
    public function getResult()
    {
        return (array) $this->getArgument('results', []);
    }

    /**
     * Merged id => score map across all contributors.
     *
     * Collects every map added via addScores()/addResult() plus a single map
     * passed as the 'scores' argument, so all shapes are accepted. Rich
     * [id => ['growth', 'delta']] pairs are merged on growth.
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

    /**
     * Per-ID delta tiebreakers across all contributors (rich maps only,
     * flat entries contribute 0). Mirrors getScores().
     *
     * @return array<int, int>
     */
    public function getDeltas()
    {
        $maps = (array) $this->getArgument('results', []);
        $single = $this->getArgument('scores', null);

        if (is_array($single)) {
            $maps[] = $single;
        }

        return ContentRanker::extractDeltas($maps);
    }
}
