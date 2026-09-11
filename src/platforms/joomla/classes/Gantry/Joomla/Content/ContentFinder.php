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

use Gantry\Joomla\Category\Category;
use Gantry\Joomla\Category\CategoryFinder;
use Gantry\Joomla\Object\Collection;
use Gantry\Joomla\Object\Finder;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;

/**
 * Class ContentFinder
 * @package Gantry\Joomla\Content
 */
class ContentFinder extends Finder
{
    /** @var string */
    protected $table = '#__content';
    /** @var bool */
    protected $readonly = true;
    /** @var array */
    protected $state = [];

    /**
     * Makes all created objects as readonly.
     *
     * @param bool $readonly
     * @return $this
     */
    public function readonly($readonly = true)
    {
        $this->readonly = (bool)$readonly;

        return $this;
    }

    /**
     * @param bool $object
     * @return Collection|string[]
     */
    public function find($object = true)
    {
        $ids = parent::find();

        if (!$object) {
            return $ids;
        }

        return Content::getInstances($ids, $this->readonly);
    }

    /**
     * Maximum candidates evaluated in a single ranking pass. Bounds the
     * dispatch payload, the plugin IN() list and the in-memory sort so a
     * misconfigured listing can never hang the page.
     */
    const RANK_CANDIDATE_CAP = 5000;

    /**
     * Find articles ordered by external ranking scores.
     *
     * Collects the candidate IDs after the configured filters, dispatches the
     * generic onContentRankIds event and orders the candidates by the merged
     * scores (highest first, stable, unscored appended at the end). Falls back
     * to publish_up ordering when no scores are provided or anything fails,
     * so ranking listeners are always optional and nothing here is fatal:
     * this method never throws, it degrades to the default ordering instead.
     *
     * @param int $limit
     * @param int $start
     * @param string $direction Ordering direction used for the fallback.
     * @param string $context Fixed content scope passed to the event.
     * @return Collection
     */
    public function findRanked($limit, $start, $direction = 'DESC', $context = 'com_content.articles')
    {
        $limit = max(0, (int) $limit);
        $start = max(0, (int) $start);

        if ($limit <= 0) {
            return Content::getInstances([], $this->readonly);
        }

        try {
            $ids = array_values(array_map('intval', (array) $this->limit(0)->start(0)->find(false)));

            if (count($ids) > static::RANK_CANDIDATE_CAP) {
                $ids = array_slice($ids, 0, static::RANK_CANDIDATE_CAP);
            }

            $scores = [];
            $deltas = [];
            if ($ids) {
                try {
                    // Ranking requires the Joomla 4+ event system. On older
                    // platforms (e.g. Joomla 3) fall back silently to the
                    // default ordering instead of fataling.
                    if (!class_exists('Joomla\CMS\Event\AbstractEvent') || !class_exists('Gantry\Joomla\Content\ContentRankEvent')) {
                        $scores = [];
                    } else {
                        $app = Factory::getApplication();
                        $dispatcher = method_exists($app, 'getDispatcher') ? $app->getDispatcher() : null;
                        if ($dispatcher && is_callable([$dispatcher, 'dispatch'])) {
                            $event = new ContentRankEvent($ids, $context);
                            $dispatcher->dispatch('onContentRankIds', $event);
                            $scores = $event->getScores();
                            $deltas = $event->getDeltas();
                        } else {
                            $scores = [];
                        }
                    }
                } catch (\Throwable $e) {
                    $scores = [];
                    $deltas = [];
                }
            }

            if (!$scores) {
                $fallback = strtoupper((string) $direction) === 'ASC' ? 'ASC' : 'DESC';

                return $this->order('publish_up', $fallback)->limit($limit)->start($start)->find();
            }

            $slice = array_slice(ContentRanker::sortRanked($ids, $scores, $deltas), $start, $limit);

            return Content::getInstances($slice, $this->readonly);
        } catch (\Throwable $e) {
            try {
                $fallback = strtoupper((string) $direction) === 'ASC' ? 'ASC' : 'DESC';

                return $this->order('publish_up', $fallback)->limit($limit)->start($start)->find();
            } catch (\Throwable $e2) {
                return Content::getInstances([], $this->readonly);
            }
        }
    }

    /**
     * @param int|int[] $ids
     * @param bool $include
     * @return $this
     */
    public function id($ids, $include = true)
    {
        return $this->addToGroup('a.id', $ids, $include);
    }

    /**
     * @param int|int[] $ids
     * @param bool $include
     * @return $this
     */
    public function author($ids, $include = true)
    {
        return $this->addToGroup('a.created_by', $ids, $include);
    }

    /**
     * @param int|int[] $ids
     * @param bool $include
     * @return $this
     */
    public function category($ids, $include = true)
    {
        if ($ids instanceof Collection) {
            $ids = $ids->toArray();
        } else {
            $ids = (array)$ids;
        }

        array_walk($ids, static function (&$item) { $item = $item instanceof Category ? $item->id : (int) $item; });

        return $this->addToGroup('a.catid', $ids, $include);
    }

    /**
     * @param int|bool $featured
     * @return $this
     */
    public function featured($featured = true)
    {
        $featured = (int)((bool)$featured);
        $this->where('a.featured', '=', $featured);

        return $this;
    }

    /**
     * @param string|int|bool $language
     * @return $this
     */
    public function language($language = true)
    {
        if (!$language) {
            return $this;
        }
        if ($language === true || is_numeric($language)) {
            /** @var CMSApplication $application */
            $application = Factory::getApplication();
            $language = $application->getLanguage()->getTag();
        }
        return $this->where('a.language', 'IN', [$language, '*']);
    }

    /**
     * @param int|int[] $published
     * @return $this
     */
    public function published($published = 1)
    {
        if (!\is_array($published)) {
            $published = [(int)$published];
        }
        return $this->where('a.state', 'IN', $published);
    }

    /**
     * @param bool $authorised
     * @return $this
     */
    public function authorised($authorised = true)
    {
        if (!$authorised) {
            return $this;
        }

        $unpublished = CategoryFinder::getUnpublished('content');
        if ($unpublished) {
            $this->where('a.catid', 'NOT IN', $unpublished);
        }

        /** @var CMSApplication $application */
        $application = Factory::getApplication();
        $user = $application->getIdentity();
        if (!$user) {
            $this->skip = true;

            return $this;
        }

        // Filter by start and end dates.
            if (!$user->authorise('core.edit.state', 'com_content') && !$user->authorise('core.edit', 'com_content')) {
            // Define now date and use modern datetime null checks (Joomla 5)
            $nowDate = $this->db->quote(Factory::getDate()->toSql());
            $nullDateUp = $this->query->isNullDatetime('a.publish_up');
            $nullDateDown = $this->query->isNullDatetime('a.publish_down');

            $this->query
                ->where('(' . $nullDateUp . ' OR a.publish_up <= ' . $nowDate . ')')
                ->where('(' . $nullDateDown . ' OR a.publish_down >= ' . $nowDate . ')')
                ->where('a.state >= 1')
            ;
        }

        $groups = $user->getAuthorisedViewLevels();

        $this->query->join('INNER', '#__categories AS c ON c.id = a.catid');

        return $this->where('a.access', 'IN', $groups)->where('c.access', 'IN', $groups);
    }

    /**
     * @param string $key
     * @param int|int[] $ids
     * @param bool $include
     * @return $this
     */
    protected function addToGroup($key, $ids, $include = true)
    {
        $op = $include ? 'IN' : 'NOT IN';

        if (isset($this->state[$key][$op])) {
            $this->state[$key][$op] = array_merge($this->state[$key][$op], $ids);
        } else {
            $this->state[$key][$op] = $ids;
        }

        return $this;
    }

    protected function prepare()
    {
        foreach ($this->state as $key => $list) {
            foreach ($list as $op => $group) {
                $this->where($key, $op, array_unique($group));
            }
        }
    }

     public function tags($tagIds = [])
    {
        if (empty($tagIds['id'][0])) {
            return $this;
        }

        $input = $tagIds['id'][0];
        $tagIdsArray = [];
        $tagNamesArray = [];

        // Separate IDs and names
        $items = array_map('trim', explode(',', $input));

        foreach ($items as $item) {
            if (is_numeric($item)) {
                $tagIdsArray[] = (int)$item;
            } else {
                $tagNamesArray[] = $item;
            }
        }

        // If we have tag names, get their IDs
        if (!empty($tagNamesArray)) {
            // Use the same database object as other methods in this class
            $query = $this->db->getQuery(true)
                ->select($this->db->quoteName('id'))
                ->from($this->db->quoteName('#__tags'))
                ->where($this->db->quoteName('title') . ' IN (' . implode(',', array_map([$this->db, 'quote'], $tagNamesArray)) . ')');

            $this->db->setQuery($query);
            $tagIdsFromNames = $this->db->loadColumn();

            if (!empty($tagIdsFromNames)) {
                $tagIdsArray = array_merge($tagIdsArray, $tagIdsFromNames);
            }
        }

        if (empty($tagIdsArray)) {
            return $this;
        }

        $this->query->join('INNER', '#__contentitem_tag_map AS t ON t.content_item_id = a.id');

        return $this->where('t.tag_id', 'IN', $tagIdsArray)->where('t.type_alias', '=', 'com_content.article');
    }
}
