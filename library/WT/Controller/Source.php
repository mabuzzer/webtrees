<?php
// Controller for the Source Page
//
// webtrees: Web based Family History software
// Copyright (C) 2011 webtrees development team.
//
// Derived from PhpGedView
// Copyright (C) 2002 to 2010 PGV Development Team.  All rights reserved.
//
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
//
// $Id$

if (!defined('WT_WEBTREES')) {
	header('HTTP/1.0 403 Forbidden');
	exit;
}

require_once WT_ROOT.'includes/functions/functions_print_facts.php';
require_once WT_ROOT.'includes/functions/functions_import.php';

class WT_Controller_Source extends WT_Controller_Base {
	var $sid;
	var $source = null;
	var $diffsource = null;
	var $accept_success = false;
	var $reject_success = false;

	function init() {
		$this->sid = safe_GET_xref('sid');

		$gedrec = find_source_record($this->sid, WT_GED_ID);

		if (find_source_record($this->sid, WT_GED_ID) || find_updated_record($this->sid, WT_GED_ID)!==null) {
			$this->source = new WT_Source($gedrec);
			$this->source->ged_id=WT_GED_ID; // This record is from a file
		} else if (!$gedrec) {
			return false;
		}

		$this->rid=$this->source->getXref(); // Correct upper/lower case mismatch

		//-- perform the desired action
		switch($this->action) {
		case 'addfav':
			if (WT_USER_ID && !empty($_REQUEST['gid']) && array_key_exists('user_favorites', WT_Module::getActiveModules())) {
				$favorite = array(
					'username' => WT_USER_NAME,
					'gid'      => $_REQUEST['gid'],
					'type'     => 'SOUR',
					'file'     => WT_GEDCOM,
					'url'      => '',
					'note'     => '',
					'title'    => ''
				);
				user_favorites_WT_Module::addFavorite($favorite);
			}
			unset($_GET['action']);
			break;
		case 'accept':
			if (WT_USER_CAN_ACCEPT) {
				accept_all_changes($this->sid, WT_GED_ID);
				$this->accept_success=true;
				//-- check if we just deleted the record and redirect to index
				$gedrec = find_source_record($this->sid, WT_GED_ID);
				if (empty($gedrec)) {
					header('Location: '.WT_SERVER_NAME.WT_SCRIPT_PATH);
					exit;
				}
				$this->source = new WT_Source($gedrec);
			}
			unset($_GET['action']);
			break;
		case 'undo':
			if (WT_USER_CAN_ACCEPT) {
				reject_all_changes($this->sid, WT_GED_ID);
				$this->reject_success=true;
				$gedrec = find_source_record($this->sid, WT_GED_ID);
				//-- check if we just deleted the record and redirect to index
				if (empty($gedrec)) {
					header('Location: '.WT_SERVER_NAME.WT_SCRIPT_PATH);
					exit;
				}
				$this->source = new WT_Source($gedrec);
			}
			unset($_GET['action']);
			break;
		}

		//-- if the user can edit and there are changes then get the new changes
		if (WT_USER_CAN_EDIT) {
			$newrec = find_updated_record($this->sid, WT_GED_ID);
			if (!empty($newrec)) {
				$this->diffsource = new WT_Source($newrec);
				$this->diffsource->setChanged(true);
			}
		}

		$this->source->diffMerge($this->diffsource);
	}

	/**
	* get the title for this page
	* @return string
	*/
	function getPageTitle() {
		if ($this->source) {
			return $this->source->getFullName();
		} else {
			return WT_I18N::translate('Source');
		}
	}

	/**
	* get edit menu
	*/
	function getEditMenu() {
		$SHOW_GEDCOM_RECORD=get_gedcom_setting(WT_GED_ID, 'SHOW_GEDCOM_RECORD');

		if (!$this->source || $this->source->isMarkedDeleted()) {
			return null;
		}

		// edit menu
		$menu = new WT_Menu(/* I18N: A menu option */ WT_I18N::translate('Edit'), '#', 'menu-sour');
		$menu->addIcon('edit_sour');
		$menu->addClass('submenuitem', 'submenuitem_hover', 'submenu', 'icon_large_edit_source');

		if (WT_USER_CAN_EDIT) {
			$submenu = new WT_Menu(/* I18N: A menu option */ WT_I18N::translate('Edit source'), '#', 'menu-sour-edit');
			$submenu->addOnclick('return edit_source(\''.$this->sid.'\');');
			$submenu->addIcon('edit_sour');
			$submenu->addClass('submenuitem', 'submenuitem_hover', 'submenu', 'icon_small_edit_source');
			$menu->addSubmenu($submenu);
		}

		// edit/view raw gedcom
		if (WT_USER_IS_ADMIN || $SHOW_GEDCOM_RECORD) {
			$submenu = new WT_Menu(/* I18N: A menu option */ WT_I18N::translate('Edit raw GEDCOM record'), '#', 'menu-sour-editraw');
			$submenu->addOnclick("return edit_raw('".$this->sid."');");
			$submenu->addIcon('gedcom');
			$submenu->addClass('submenuitem', 'submenuitem_hover', 'submenu', 'icon_small_edit_raw');
			$menu->addSubmenu($submenu);
		} elseif ($SHOW_GEDCOM_RECORD) {
			$submenu = new WT_Menu(/* I18N: A menu option */ WT_I18N::translate('View GEDCOM Record'), '#', 'menu-sour-viewraw');
			$submenu->addIcon('gedcom');
			if (WT_USER_CAN_EDIT) {
				$submenu->addOnclick("return show_gedcom_record('new');");
			} else {
				$submenu->addOnclick("return show_gedcom_record();");
			}
			$submenu->addClass('submenuitem', 'submenuitem_hover', 'submenu', 'icon_small_edit_raw');
			$menu->addSubmenu($submenu);
		}

		// delete
		if (WT_USER_CAN_EDIT) {
			$submenu = new WT_Menu(/* I18N: A menu option */ WT_I18N::translate('Delete source'), '#', 'menu-sour-del');
			$submenu->addOnclick("if (confirm('".htmlspecialchars(WT_I18N::translate('Are you sure you want to delete “%s”?', $this->source->getFullName()))."')) return delete_source('".$this->sid."'); else return false;");
			$submenu->addIcon('remove');
			$submenu->addClass('submenuitem', 'submenuitem_hover', 'submenu', 'icon_small_delete');
			$menu->addSubmenu($submenu);
		}

		// add to favorites
		if (array_key_exists('user_favorites', WT_Module::getActiveModules())) {
			$submenu = new WT_Menu(
				/* I18N: A menu option.  Add [the current page] to the list of favorites */ WT_I18N::translate('Add to favorites'),
				$this->source->getHtmlUrl()."&amp;action=addfav&amp;gid=".$this->sid,
				'menu-sour-addfav'
			);
			$submenu->addIcon('favorites');
			$submenu->addClass('submenuitem', 'submenuitem_hover', 'submenu', 'icon_small_fav');
			$menu->addSubmenu($submenu);
		}

		//-- get the link for the first submenu and set it as the link for the main menu
		if (isset($menu->submenus[0])) {
			$link = $menu->submenus[0]->onclick;
			$menu->addOnclick($link);
		}
		return $menu;
	}
}
