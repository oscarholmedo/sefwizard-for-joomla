<?php

/* SEF Wizard extension for Joomla 3.x - Version 1.0.2
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

defined('_JEXEC') or die('Restricted access');

class PlgSystemSefwizardInstallerScript
{
	public function preflight($type, $parent)
	{
		$app = JFactory::getApplication();
		
		if(version_compare(JVERSION, '3.0', '<'))
		{
			$app->redirect(JURI::getInstance()->toString(), JText::_('PLG_SEFWIZARD_JOOMLA_VERSION_CHECK_FAILURE'), 'error');
			return false;
		}
		if(version_compare(PHP_VERSION, '5.3', '<'))
		{
			$app->redirect(JURI::getInstance()->toString(), JText::_('PLG_SEFWIZARD_PHP_VERSION_CHECK_FAILURE'), 'error');
			return false;
		}
		
	}

}
