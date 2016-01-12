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
		$donateID = JFactory::getLanguage()->getName() === "Russian" ? "D6CR8WY5NAWHS" : "5CNAKEVMSUT3Q";
		$donateURL = "https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id={$donateID}";
		$img = JURI::root(true) . "/plugins/system/sefwizard/assets/btn_donate.jpg";
		
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
		
		return "<a href='{$donateURL}' target='_blank'><img src='{$img}' alt='' width='135' height='31' /></a>";
	}
	
}
