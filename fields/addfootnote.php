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

class JFormFieldAddfootnote extends JFormField
{	
	protected $type = 'addfootnote';
	
	protected function getInput()
	{
		return '<hr/>' . JText::_('PLG_SEFWIZARD_REVIEW');
	}
	
}
