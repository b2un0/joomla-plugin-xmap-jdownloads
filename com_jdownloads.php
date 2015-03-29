<?php

/**
 * @author     Branko Wilhelm <branko.wilhelm@gmail.com>
 * @link       http://www.z-index.net
 * @copyright  (c) 2013 - 2015 Branko Wilhelm
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

defined('_JEXEC') or die;

class xmap_com_jdownloads
{
    private static $views = array('categories', 'category', 'downloads');

    private static $enabled = false;

    public function __construct()
    {
        self::$enabled = JComponentHelper::isEnabled('com_jdownloads');
    }

    public static function getTree(XmapDisplayer $xmap, stdClass $parent, array &$params)
    {
        $uri = new JUri($parent->link);

        if (!self::$enabled || !in_array($uri->getVar('view'), self::$views))
        {
            return;
        }

        $params['groups'] = implode(',', JFactory::getUser()->getAuthorisedViewLevels());

        $params['include_downloads'] = JArrayHelper::getValue($params, 'include_downloads', 1);
        $params['include_downloads'] = ($params['include_downloads'] == 1 || ($params['include_downloads'] == 2 && $xmap->view == 'xml') || ($params['include_downloads'] == 3 && $xmap->view == 'html'));

        $params['show_unauth'] = JArrayHelper::getValue($params, 'show_unauth', 0);
        $params['show_unauth'] = ($params['show_unauth'] == 1 || ($params['show_unauth'] == 2 && $xmap->view == 'xml') || ($params['show_unauth'] == 3 && $xmap->view == 'html'));

        $params['category_priority'] = JArrayHelper::getValue($params, 'category_priority', $parent->priority);
        $params['category_changefreq'] = JArrayHelper::getValue($params, 'category_changefreq', $parent->changefreq);

        if ($params['category_priority'] == -1)
        {
            $params['category_priority'] = $parent->priority;
        }

        if ($params['category_changefreq'] == -1)
        {
            $params['category_changefreq'] = $parent->changefreq;
        }

        $params['download_priority'] = JArrayHelper::getValue($params, 'download_priority', $parent->priority);
        $params['download_changefreq'] = JArrayHelper::getValue($params, 'download_changefreq', $parent->changefreq);

        if ($params['download_priority'] == -1)
        {
            $params['download_priority'] = $parent->priority;
        }

        if ($params['download_changefreq'] == -1)
        {
            $params['download_changefreq'] = $parent->changefreq;
        }

        switch ($uri->getVar('view'))
        {
            case 'categories':
                self::getCategoryTree($xmap, $parent, $params, 1);
                break;

            case 'category':
                self::getDownloads($xmap, $parent, $params, $uri->getVar('catid'));
                break;

            case 'downloads':
                self::getDownloads($xmap, $parent, $params);
                break;
        }
    }

    private static function getCategoryTree(XmapDisplayer $xmap, stdClass $parent, array &$params, $parent_id)
    {
        $db = JFactory::getDbo();

        $query = $db->getQuery(true)
            ->select(array('c.id', 'c.title', 'c.parent_id'))
            ->from('#__jdownloads_categories AS c')
            ->where('c.parent_id = ' . $db->quote($parent_id))
            ->where('c.published = 1')
            ->order('c.ordering');

        if (!$params['show_unauth'])
        {
            $query->where('c.access IN(' . $params['groups'] . ')');
        }

        $db->setQuery($query);
        $rows = $db->loadObjectList();

        if (empty($rows))
        {
            return;
        }

        $xmap->changeLevel(1);

        foreach ($rows as $row)
        {
            $node = new stdclass;
            $node->id = $parent->id;
            $node->name = $row->title;
            $node->uid = $parent->uid . '_cid_' . $row->id;
            $node->browserNav = $parent->browserNav;
            $node->priority = $params['category_priority'];
            $node->changefreq = $params['category_changefreq'];
            $node->pid = $row->parent_id;
            $node->link = 'index.php?option=com_jdownloads&view=category&catid=' . $row->id . '&Itemid=' . $parent->id;

            if ($xmap->printNode($node) !== false)
            {
                self::getDownloads($xmap, $parent, $params, $row->id);
            }
        }

        $xmap->changeLevel(-1);
    }

    private static function getDownloads(XmapDisplayer $xmap, stdClass $parent, array &$params, $catid = null)
    {
        if (!is_null($catid))
        {
            self::getCategoryTree($xmap, $parent, $params, $catid);
        }

        if (!$params['include_downloads'])
        {
            return;
        }

        $db = JFactory::getDbo();

        $query = $db->getQuery(true)
            ->select(array('d.file_id', 'd.file_title', 'd.file_alias'))
            ->from('#__jdownloads_files AS d')
            ->where('d.published = 1')
            ->order('d.ordering');

        if (!is_null($catid))
        {
            $query->where('d.cat_id = ' . $db->Quote($catid));
        }

        if (!$params['show_unauth'])
        {
            $query->where('d.access IN(' . $params['groups'] . ')');
        }

        $db->setQuery($query);

        $rows = $db->loadObjectList();

        if (empty($rows))
        {
            return;
        }

        $xmap->changeLevel(1);

        foreach ($rows as $row)
        {
            $row->slug = !empty($row->file_alias) ? ($row->file_id . ':' . $row->file_alias) : $row->file_id;

            $node = new stdclass;
            $node->id = $parent->id;
            $node->name = $row->file_title;
            $node->uid = $parent->uid . '_' . $row->file_id;
            $node->browserNav = $parent->browserNav;
            $node->priority = $params['download_priority'];
            $node->changefreq = $params['download_changefreq'];
            $node->link = 'index.php?option=com_jdownloads&view=download&id=' . $row->slug . '&catid=' . $catid . '&Itemid=' . $parent->id;

            $xmap->printNode($node);
        }

        $xmap->changeLevel(-1);
    }
}