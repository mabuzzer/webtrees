<?php
// Controller for the Media Page
//
// webtrees: Web based Family History software
// Copyright (C) 2011 webtrees development team.
//
// Derived from PhpGedView
// Copyright (C) 2002 to 2009 PGV Development Team.  All rights reserved.
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

class WT_Controller_Media extends WT_Controller_Base {
	var $mid;
	var $mediaobject;
	var $accept_success = false;
	var $reject_success = false;

	function init() {
		global $MEDIA_DIRECTORY;

		$filename = safe_GET('filename');
		$this->mid = safe_GET_xref('mid');

		if (empty($filename) && empty($this->mid)) {
			// this section used by mediafirewall.php to determine what media file was requested

			if (isset($_SERVER['REQUEST_URI'])) {
				// NOTE: format of this server variable:
				// Apache: /phpGedView/media/a.jpg
				// IIS:    /phpGedView/mediafirewall.php?404;http://server/phpGedView/media/a.jpg
				$requestedfile = $_SERVER['REQUEST_URI'];
				// urldecode the request
				$requestedfile = rawurldecode($requestedfile);
				// make sure the requested file is in the media directory
				if (strpos($requestedfile, $MEDIA_DIRECTORY) !== false) {
					// strip off the wt directory and media directory from the requested url so just the image information is left
					$filename = substr($requestedfile, strpos($requestedfile, $MEDIA_DIRECTORY) + strlen($MEDIA_DIRECTORY) - 1);
					// strip the ged param if it was passed on the querystring
					// would be better if this could remove any querystring, but '?' are valid in unix filenames
					if (strpos($filename, '?ged=') !== false) {
						$filename = substr($filename, 0, strpos($filename, '?ged='));
					}
					// if user requested a thumbnail, lookup permissions based on the original image
					$filename = str_replace('/thumbs', '', $filename);
				} else {
					// the MEDIA_DIRECTORY of the current GEDCOM was not part of the requested file
					// either the requested file is in a different GEDCOM (with a different MEDIA_DIRECTORY)
					// or the Media Firewall is being called from outside the MEDIA_DIRECTORY
					// this condition can be detected by the media firewall by calling controller->getServerFilename()
				}
			}
		}

		//Checks to see if the filename ($filename) exists
		if (!empty($filename)) {
			//If the filename ($filename) is set, then it will call the method to get the Media ID ($this->mid) from the filename ($filename)
			$this->mid = get_media_id_from_file($filename);
			if (!$this->mid) {
				//This will set the Media ID to be false if the File given doesn't match to anything in the database
				$this->mid = false;
				// create a very basic gedcom record for this file so that the functions of the media object will work
				// this is used by the media firewall when requesting an object that exists in the media firewall directory but not in the gedcom
				$this->mediaobject = new WT_Media("0 @"."0"."@ OBJE\n1 FILE ".$filename);
			}
		}

		//checks to see if the Media ID ($this->mid) is set. If the Media ID isn't set then there isn't any information avaliable for that picture the picture doesn't exist.
		if ($this->mid) {
			//This creates a Media Object from the getInstance method of the Media Class. It takes the Media ID ($this->mid) and creates the object.
			$this->mediaobject = WT_Media::getInstance($this->mid);
			//This sets the controller ID to be the Media ID
			$this->pid = $this->mid;
		}

		if (is_null($this->mediaobject)) return false;
		$this->mediaobject->ged_id=WT_GED_ID; // This record is from a file

		$this->mid=$this->mediaobject->getXref(); // Correct upper/lower case mismatch

		//-- perform the desired action
		switch($this->action) {
		case 'addfav':
			if (WT_USER_ID && !empty($_REQUEST['gid']) && array_key_exists('user_favorites', WT_Module::getActiveModules())) {
				$favorite = array(
					'username' => WT_USER_NAME,
					'gid'      => $_REQUEST['gid'],
					'type'     => 'OBJE',
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
				accept_all_changes($this->pid, WT_GED_ID);
				$this->accept_success=true;
				//-- check if we just deleted the record and redirect to index
				$mediarec = find_media_record($this->pid, WT_GED_ID);
				if (empty($mediarec)) {
					header('Location: '.WT_SERVER_NAME.WT_SCRIPT_PATH);
					exit;
				}
				$this->mediaobject = new WT_Media($mediarec);
			}
			unset($_GET['action']);
			break;
		case 'undo':
			if (WT_USER_CAN_ACCEPT) {
				reject_all_changes($this->pid, WT_GED_ID);
				$this->reject_success=true;
				$mediarec = find_media_record($this->pid, WT_GED_ID);
				//-- check if we just deleted the record and redirect to index
				if (empty($mediarec)) {
					header('Location: '.WT_SERVER_NAME.WT_SCRIPT_PATH);
					exit;
				}
				$this->mediaobject = new WT_Media($mediarec);
			}
			unset($_GET['action']);
			break;
		}
	}

	/**
	* return the title of this page
	* @return string the title of the page to go in the <title> tags
	*/
	function getPageTitle() {
		if ($this->mediaobject) {
			return $this->mediaobject->getFullName();
		} else {
			return WT_I18N::translate('Media object');
		}
	}

	function canDisplayDetails() {
		return $this->mediaobject->canDisplayDetails();
	}

	/**
	* get edit menu
	*/
	function getEditMenu() {
		$SHOW_GEDCOM_RECORD=get_gedcom_setting(WT_GED_ID, 'SHOW_GEDCOM_RECORD');

		if (!$this->mediaobject || $this->mediaobject->isMarkedDeleted()) {
			return null;
		}

		$links = get_media_relations($this->pid);
		$linktoid = "new";
		foreach ($links as $linktoid => $type) {
			break; // we're only interested in the key of the first list entry
		}

		// edit menu
		$menu = new WT_Menu(/* I18N: A menu option */ WT_I18N::translate('Edit'), '#', 'menu-obje');
		$menu->addIcon('edit_media');
		$menu->addClass('submenuitem', 'submenuitem_hover', 'submenu', 'icon_large_edit_media');

		if (WT_USER_CAN_EDIT) {
			$submenu = new WT_Menu(/* I18N: A menu option */ WT_I18N::translate('Edit media object'), '#', 'menu-obje-edit');
			$submenu->addOnclick("window.open('addmedia.php?action=editmedia&pid={$this->pid}', '_blank', 'top=50,left=50,width=600,height=500,resizable=1,scrollbars=1')");
			$submenu->addIcon('edit_media');
			$submenu->addClass('submenuitem', 'submenuitem_hover', 'submenu', 'icon_small_edit_media');
			$menu->addSubmenu($submenu);

			// main link displayed on page
			if (WT_USER_GEDCOM_ADMIN && array_key_exists('GEDFact_assistant', WT_Module::getActiveModules())) {
				$submenu = new WT_Menu(/* I18N: A menu option */ WT_I18N::translate('Manage links'), '#', 'menu-obje-link');
			} else {
				$submenu = new WT_Menu(/* I18N: A menu option */ WT_I18N::translate('Set link'), '#', 'menu-obje-link');
			}
			$submenu->addClass('submenuitem', 'submenuitem_hover', 'submenu', 'icon_small_medialink');
			$submenu->addIcon('edit_media');

			// GEDFact assistant Add Media Links =======================
			if (WT_USER_GEDCOM_ADMIN && array_key_exists('GEDFact_assistant', WT_Module::getActiveModules())) {
				$submenu->addOnclick("return ilinkitem('".$this->pid."','manage');");
			} else {
				$ssubmenu = new WT_Menu(/* I18N: A menu option */ WT_I18N::translate('To Person'), '#', 'menu-obje-link-indi');
				$ssubmenu->addOnclick("return ilinkitem('".$this->pid."','person');");
				$ssubmenu->addClass('submenuitem', 'submenuitem_hover', 'submenu', 'icon_small_indis');
				$ssubmenu->addIcon('edit_media');
				$submenu->addSubMenu($ssubmenu);

				$ssubmenu = new WT_Menu(/* I18N: A menu option */ WT_I18N::translate('To Family'), '#', 'menu-obje-link-fam');
				$ssubmenu->addOnclick("return ilinkitem('".$this->pid."','family');");
				$ssubmenu->addClass('submenuitem', 'submenuitem_hover', 'submenu', 'icon_small_cfamily');
				$ssubmenu->addIcon('edit_media');
				$submenu->addSubMenu($ssubmenu);

				$ssubmenu = new WT_Menu(/* I18N: A menu option */ WT_I18N::translate('To Source'), '#', 'menu-obje-link-sour');
				$ssubmenu->addOnclick("return ilinkitem('".$this->pid."','source');");
				$ssubmenu->addClass('submenuitem', 'submenuitem_hover', 'submenu', 'icon_small_menu_source');
				$ssubmenu->addIcon('edit_media');
				$submenu->addSubMenu($ssubmenu);
			}
			$menu->addSubmenu($submenu);
		}

		// edit/view raw gedcom
		if (WT_USER_IS_ADMIN || $SHOW_GEDCOM_RECORD) {
			$submenu = new WT_Menu(/* I18N: A menu option */ WT_I18N::translate('Edit raw GEDCOM record'), '#', 'menu-obje-editraw');
			$submenu->addOnclick("return edit_raw('".$this->pid."');");
			$submenu->addIcon('gedcom');
			$submenu->addClass('submenuitem', 'submenuitem_hover', 'submenu', 'icon_small_edit_raw');
			$menu->addSubmenu($submenu);
		} elseif ($SHOW_GEDCOM_RECORD) {
			$submenu = new WT_Menu(/* I18N: A menu option */ WT_I18N::translate('View GEDCOM Record'), '#', 'menu-obje-viewraw');
			$submenu->addIcon('gedcom');
			if (WT_USER_CAN_EDIT || WT_USER_CAN_ACCEPT) {
				$submenu->addOnclick("return show_gedcom_record('new');");
			} else {
				$submenu->addOnclick("return show_gedcom_record();");
			}
			$submenu->addClass('submenuitem', 'submenuitem_hover', 'submenu', 'icon_small_edit_raw');
			$menu->addSubmenu($submenu);
		}

		// delete
		if (WT_USER_GEDCOM_ADMIN) {
			$submenu = new WT_Menu(
				/* I18N: A menu option */ WT_I18N::translate('Delete media object'),
				"admin_media.php?action=removeobject&amp;xref=".$this->pid,
				'menu-obje-del'
			);
			$submenu->addOnclick("return confirm('".WT_I18N::translate('Deleting a media object does not delete the media file from disk.').' '.htmlspecialchars(WT_I18N::translate('Are you sure you want to delete “%s”?', $this->mediaobject->getFullName()))."')");
			$submenu->addIcon('remove');
			$submenu->addClass('submenuitem', 'submenuitem_hover', 'submenu', 'icon_small_delete');
			$menu->addSubmenu($submenu);
		}

		// add to favorites
		if (array_key_exists('user_favorites', WT_Module::getActiveModules())) {
			$submenu = new WT_Menu(
				/* I18N: A menu option.  Add [the current page] to the list of favorites */ WT_I18N::translate('Add to favorites'),
				$this->mediaobject->getHtmlUrl()."&amp;action=addfav&amp;gid=".$this->mid,
				'menu-obje-addfav'
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

	/**
	* check if we can show the gedcom record
	* @return boolean
	*/
	function canShowGedcomRecord() {
		global $SHOW_GEDCOM_RECORD;
		if ($SHOW_GEDCOM_RECORD && $this->mediaobject->canDisplayDetails())
			return true;
	}

	/**
	* return a list of facts
	* @return array
	*/
	function getFacts($includeFileName=true) {
		$facts = $this->mediaobject->getFacts(array());
		sort_facts($facts);
		//if ($includeFileName) $facts[] = new WT_Event("1 FILE ".$this->mediaobject->getFilename(), $this->mediaobject, 0);
		$mediaType = $this->mediaobject->getMediatype();
		$facts[] = new WT_Event("1 TYPE ".WT_Gedcom_Tag::getFileFormTypeValue($mediaType), $this->mediaobject, 0);

		if (($newrec=find_updated_record($this->pid, WT_GED_ID))!==null) {
			$newmedia = new WT_Media($newrec);
			$newfacts = $newmedia->getFacts(array());
			$newimgsize = $newmedia->getImageAttributes();
			if ($includeFileName) $newfacts[] = new WT_Event("1 TYPE ".WT_Gedcom_Tag::getFileFormTypeValue($mediaType), $this->mediaobject, 0);
			$newfacts[] = new WT_Event("1 FORM ".$newimgsize['ext'], $this->mediaobject, 0);
			$mediaType = $newmedia->getMediatype();
			$newfacts[] = new WT_Event("1 TYPE ".WT_Gedcom_Tag::getFileFormTypeValue($mediaType), $this->mediaobject, 0);
			//-- loop through new facts and add them to the list if they are any changes
			//-- compare new and old facts of the Personal Fact and Details tab 1
			for ($i=0; $i<count($facts); $i++) {
				$found=false;
				foreach ($newfacts as $indexval => $newfact) {
					if (trim($newfact->gedcomRecord)==trim($facts[$i]->gedcomRecord)) {
						$found=true;
						break;
					}
				}
				if (!$found) {
					$facts[$i]->gedcomRecord.="\nWT_OLD\n";
				}
			}
			foreach ($newfacts as $indexval => $newfact) {
				$found=false;
				foreach ($facts as $indexval => $fact) {
					if (trim($fact->gedcomRecord)==trim($newfact->gedcomRecord)) {
						$found=true;
						break;
					}
				}
				if (!$found) {
					$newfact->gedcomRecord.="\nWT_NEW\n";
					$facts[]=$newfact;
				}
			}
		}

		if ($this->mediaobject->fileExists()) {
			// get height and width of image, when available
			$imgsize = $this->mediaobject->getImageAttributes();
			if (!empty($imgsize['WxH'])) {
				$facts[] = new WT_Event('1 __IMAGE_SIZE__ '.$imgsize['WxH'], $this->mediaobject, 0);
			}
			//Prints the file size
			$facts[] = new WT_Event('1 __FILE_SIZE__ '.$this->mediaobject->getFilesize(), $this->mediaobject, 0);
		}

		sort_facts($facts);
		return $facts;
	}

	/**
	* get the relative file path of the image on the server
	* @return string
	*/
	function getLocalFilename() {
		if ($this->mediaobject) {
			return $this->mediaobject->getLocalFilename();
		} else {
			return false;
		}
	}

	/**
	* get the filename on the server
	* @return string
	*/
	function getServerFilename() {
		if ($this->mediaobject) {
			return $this->mediaobject->getServerFilename();
		} else {
			return false;
		}
	}
}
