<?php

/* SEF Wizard extension for Joomla 3.x/2.5.x - Version 1.1.2
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
		$doc = JFactory::getDocument();
		$language = JFactory::getLanguage()->getName();
		
		$donatePPID = $language === "Russian" ? "D6CR8WY5NAWHS" : "5CNAKEVMSUT3Q";
		$donatePPURL = "https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id={$donatePPID}";
		$ppImg = JURI::root(true) . "/plugins/system/sefwizard/assets/ppbtn_donate.jpg";
		
		$doc->addStyleDeclaration("
			#general .add-spacer, #general .add-spacer label {
				text-transform: uppercase !important;
				font-weight: bold !important;
				cursor: default !important;
			}
			#attrib-donate .control-group .controls,
			#general .control-group:last-child .controls {
				margin: 0 !important;
			}
			#general .control-group:last-child .control-label {
				display: none !important;
			}
			li a[href='#attrib-donate'], #donate-options a {
				color: red !important;
			}
		");
		
		if(version_compare(JVERSION, '3.0', '<'))
		{
			$doc->addStyleDeclaration("
				#jform_params_com_tags-lbl, #jform_params_com_tags {
					display: none !important;
				}
			");
		}
		
		$html = "<a href='{$donatePPURL}' target='_blank'><img src='{$ppImg}' alt='' width='135' height='31' /></a>";
		
		if($language === "Russian")
		{
			$donateYMURL = "https://addondev.com/ru/?page=donate";
			$ymImg = JURI::root(true) . "/plugins/system/sefwizard/assets/ymbtn_donate.png";
			$vmcImg = JURI::root(true) . "/plugins/system/sefwizard/assets/vmcbtn_donate.jpg";
			
			$html .= "<a href='{$donateYMURL}' target='_blank'><img style='margin-left: 10px' src='{$ymImg}' alt='' width='127' height='40' /></a>";
			$html .= "<a href='{$donateYMURL}' target='_blank'><img src='{$vmcImg}' alt='' width='120' height='40' /></a>";
		}
		
		
		return $html;
	}
	
}
