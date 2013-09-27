<?php

/**
 * @author     Branko Wilhelm <branko.wilhelm@gmail.com>
 * @link       http://www.z-index.net
 * @copyright  (c) 2013 Branko Wilhelm
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
 
defined('_JEXEC') or die;

final class xmap_com_jdownloads {
	
	private static $views = array('viewcategories', 'viewcategory');
	
	private static $enabled = false;
	
	public function __construct() {
		self::$enabled = JComponentHelper::isEnabled('com_jdownloads');
	}
	
	public static function getTree(XmapDisplayer &$xmap, stdClass &$parent, array &$params) {
		$uri = new JUri($parent->link);
		
		if(!self::$enabled || !in_array($uri->getVar('view'), self::$views)) {
			return;
		}
		
		$params['include_downloads'] = JArrayHelper::getValue($params, 'include_downloads', 1);
		$params['include_downloads'] = ($params['include_downloads'] == 1 || ($params['include_downloads'] == 2 && $xmap->view == 'xml') || ($params['include_downloads'] == 3 && $xmap->view == 'html'));
		
		$params['show_unauth'] = JArrayHelper::getValue($params, 'show_unauth', 0);
		$show_unauth = ($params['show_unauth'] == 1 || ( $params['show_unauth'] == 2 && $xmap->view == 'xml') || ( $params['show_unauth'] == 3 && $xmap->view == 'html'));
		
		$params['category_priority'] = JArrayHelper::getValue($params, 'category_priority', $parent->priority);
		$params['category_changefreq'] = JArrayHelper::getValue($params, 'category_changefreq', $parent->changefreq);
		
		if($params['category_priority'] == -1) {
			$params['category_priority'] = $parent->priority;
		}
		
		if($params['category_changefreq'] == -1) {
			$params['category_changefreq'] = $parent->changefreq;
		}
			
		$params['download_priority'] = JArrayHelper::getValue($params, 'download_priority', $parent->priority);
		$params['download_changefreq'] = JArrayHelper::getValue($params, 'download_changefreq', $parent->changefreq);
		
		if($params['download_priority'] == -1) {
			$params['download_priority'] = $parent->priority;
		}
		
		if($params['download_changefreq'] == -1) {
			$params['download_changefreq'] = $parent->changefreq;
		}
		
		switch($uri->getVar('view')) {
			case 'viewcategories':
				self::getCategoryTree($xmap, $parent, $params, 0);
			break;
					
			case 'viewcategory':
				self::getDownloads($xmap, $parent, $params, $uri->getVar('catid'));
			break;					
		}
	}
	
	private static function getCategoryTree(XmapDisplayer &$xmap, stdClass &$parent, array &$params, $parent_id) {
		$db = JFactory::getDbo();
		
		$query = $db->getQuery(true)
				->select(array('c.cat_id', 'c.cat_title', 'c.parent_id'))
				->from('#__jdownloads_cats AS c')
				->where('c.parent_id = ' . $db->quote($parent_id))
				->where('c.published = 1')
				->order('c.ordering');
		
		if(!$params['show_unauth']) {
			$user = JFactory::getUser();
			
			$access = '';
			if($user->guest) {
				$access = 01;
			}
			elseif(!$user->guest) {
				$access = 11;
			}
			elseif($user->get('isRoot')) {
				$access = 22;
			}
			
			$query->where('c.cat_access <= ' . $db->Quote($access));
		}
		
		$db->setQuery($query);
		$rows = $db->loadObjectList();
		
		if(empty($rows)) {
			return;
		}
		
		$xmap->changeLevel(1);
		
		foreach($rows as $row) {
			$node = new stdclass;
			$node->id = $parent->id;
			$node->name = $row->cat_title;
			$node->uid = $parent->uid . '_cid_' . $row->cat_id;
			$node->browserNav = $parent->browserNav;
			$node->priority = $params['category_priority'];
			$node->changefreq = $params['category_changefreq'];
			$node->pid = $row->parent_id;
			$node->link = 'index.php?option=com_jdownloads&view=viewcategory&catid=' . $row->cat_id . '&Itemid=' . $parent->id;
			
			if ($xmap->printNode($node) !== false) {
				self::getCategoryTree($xmap, $parent, $params, $row->cat_id);
				if ($params['include_downloads']) {
					self::getDownloads($xmap, $parent, $params, $row->cat_id);
				}
			}
		}
		
		$xmap->changeLevel(-1);
	}

	private static function getDownloads(XmapDisplayer &$xmap, stdClass &$parent, array &$params, $catid) {
		$db = JFactory::getDbo();
		
		$query = $db->getQuery(true)
				->select(array('d.file_id', 'd.file_title'))
				->from('#__jdownloads_files AS d')
				->where('d.cat_id = ' . $db->Quote($catid))
				->where('d.published = 1')
				->order('d.ordering');
		
		$db->setQuery($query);
		$rows = $db->loadObjectList();

		if(empty($rows)) {
			return;
		}
		
		$xmap->changeLevel(1);
		
		foreach($rows as $row) {
			$node = new stdclass;
			$node->id = $parent->id;
			$node->name = $row->file_title;
			$node->uid = $parent->uid . '_' . $row->file_id;
			$node->browserNav = $parent->browserNav;
			$node->priority = $params['download_priority'];
			$node->changefreq = $params['download_changefreq'];
			$node->link = 'index.php?option=com_jdownloads&view=viewdownload&catid=' . $catid . '&cid=' . $row->file_id . '&Itemid=' . $parent->id;
			
			$xmap->printNode($node);
		}
		
		$xmap->changeLevel(-1);
	}
}