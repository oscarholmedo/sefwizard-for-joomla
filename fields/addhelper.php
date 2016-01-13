<?php

/* SEF Wizard extension for Joomla 3.x - Version 1.1
--------------------------------------------------------------
 Copyright (C) 2015 AddonDev. All rights reserved.
 Website: www.addondev.com
 GitHub: github.com/addondev
 Developer: Philip Sorokin
 Location: Russia, Moscow
 E-mail: philip.sorokin@gmail.com
 Created: January 2016
 License: GNU GPLv2 http://www.gnu.org/licenses/gpl-2.0.html
--------------------------------------------------------------- */

defined('JPATH_PLATFORM') or die;

class JFormFieldAddhelper extends JFormField
{	
	protected $type = 'addhelper';
	
	protected function getInput()
	{
		$language = JFactory::getLanguage()->getName() === "Russian";
		
		$donatePPID = $language === "Russian" ? "D6CR8WY5NAWHS" : "5CNAKEVMSUT3Q";
		$donatePPURL = "https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id={$donatePPID}";
		$ppImg = JURI::root(true) . "/plugins/system/sefwizard/assets/ppbtn_donate.jpg";
		
		JFactory::getDocument()->addStyleDeclaration("
			#general .add-spacer {
				text-transform: uppercase !important;
			}
			#attrib-donate .control-group .controls {
				margin: 0 !important;
			}
			li a[href='#attrib-donate'] {
				color: red !important;
			}
		");
		
		$html = "<a href='{$donatePPURL}' target='_blank'><img src='{$ppImg}' alt='' width='135' height='31' /></a>";
		
		if($language === "Russian")
		{
			$donateYMURL = "https://addondev.com/ru/?page=donate";
			$ymImg = JURI::root(true) . "/plugins/system/sefwizard/assets/ymbtn_donate.png";
			
			$html .= "<a href='{$donateYMURL}' target='_blank'><img style='margin:-10px 0 0 10px' src='{$ymImg}' alt='' width='127' height='40' /></a>";
		}
		
		
		return $html;
	}
	
}
