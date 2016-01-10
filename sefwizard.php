<?php

/* SEF Wizard extension for Joomla 3.x - Version 1.0.7
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

class PlgSystemSefwizard extends JPlugin
{
	PRIVATE
		$_sef = "",
		$_router = null,
		$_sefRewrite = false,
		$_sefSuffix = false,
		$_options = array(),
		$_debug = false,
		$_execute = false,
		$_language = '',
		$_fragments = array(),
		$_menu = null,
		$_rootlen = 0;
		
	
	PUBLIC FUNCTION onAfterInitialise()
	{
		$app = JFactory::getApplication();
		$config = JFactory::getConfig();
		
		if($app->isSite() && $config->get('sef'))
		{	
			$this->_options["com_content"] = $this->params->get("com_content") ? "com_content" : "";
			$this->_options["com_contact"] = $this->params->get("com_contact") ? "com_contact" : "";
			$this->_options["com_tags"] = $this->params->get("com_tags") ? "com_tags" : "";
			
			$options = array_filter($this->_options);
			
			if(empty($options))
			{
				return false;
			}
			
			$this->_execute = true;
			$this->_router = $app->getRouter();
			$this->_sefRewrite = $config->get('sef_rewrite');
			$this->_menu = $app->getMenu();
			
			if($this->_debug = $this->params->get("debug"))
			{
				$this->script_execution_time("start");
			}
			
			$dbo = JFactory::getDbo();
			
			$uri  = JURI::getInstance();
			$path = $uri->getPath();
			$len  = strlen(JURI::root(true));
			$this->_rootlen = $len;
			
			if(stripos($path, '/index.php') === $len)
			{
				$len += 10;
			}
			
			if($len)
			{
				$path = substr($path . '/', $len);
			}
			
			if($this->_sefSuffix = $config->get('sef_suffix'))
			{
				if($pos = strrpos($path, '.'))
				{
					$path = substr_replace($path, '', $pos);
				}
			}
			
			$fragments = explode("/", $path);
			$fragments = array_filter($fragments);
			$fragments = array_values($fragments);
			
			if(isset($fragments[0]))
			{
				$base = $fragments[0];
				$sefs = JLanguageHelper::getLanguages('sef');
				
				if(isset($sefs[$base]))
				{
					array_shift($fragments);
					$langSEF = $sefs[$base]->sef;
					$langTAG = $sefs[$base]->lang_code;
				}
				else
				{
					$codes = JLanguageHelper::getLanguages('lang_code');
					$langTAG = JComponentHelper::getParams('com_languages')->get('site', 'en-GB');
					$langSEF = $codes[$langTAG]->sef;
				}
				
				if($level = count($fragments))
				{
					$alias = $fragments[$level-1];
					$catalias = isset($fragments[$level-2]) ? $fragments[$level-2] : '';
					$path = implode('/', $fragments);
					
					$this->_language = $langTAG;
					$this->_languages = array($langTAG, '*');
					$this->_fragments = $fragments;
					$this->_alias = $alias;
					$this->_catalias = $catalias;
					
					$excludeParameters = array('route', 'language');
					$excludeValues = array($path, $this->_languages);
					
					if(!$catalias)
					{
						$excludeParameters[] = 'home';
						$excludeValues[] = 0;
					}
					
					$currentRouteItems = $this->_menu->getItems($excludeParameters, $excludeValues);
					
					if(!count($currentRouteItems))
					{	
						if(!$catalias)
						{
							$menuItems = $this->_menu->getItems(
								array(
									'home', 
									'language', 
									'component'
								), 
								array(
									'1', 
									array($langTAG, '*'),
									$options
								)
							);
							
							if(count($menuItems))
							{
								$this->_sef = $this->getPrimaryLevelSef($menuItems);
							}
						}
						else
						{
							$primaryLevelSef = null;
							$primaryLevelFragments = $fragments;
							
							array_pop($primaryLevelFragments);
							$primaryLevelPath = implode("/", $primaryLevelFragments);
							
							if($fragments[0] === 'component')
							{
								$primaryLevelSef = $this->getPrimaryLevelSef(null);
							}
							else
							{
								$menuItems = $this->_menu->getItems(
									array( 
										'language', 
										'component',
										'route'
									), 
									array(
										array($langTAG, '*'),
										$options,
										$primaryLevelPath
									)
								);
								
								if(count($menuItems))
								{
									$primaryLevelSef = $this->getPrimaryLevelSef($menuItems);
								}
							}
							
							if($primaryLevelSef)
							{
								$this->_sef = $primaryLevelPath . "/" . $primaryLevelSef;
							}
							else
							{
								$table_content = $dbo->quoteName('#__content');
								$table_categories = $dbo->quoteName('#__categories');
								$table_contact_details = $dbo->quoteName('#__contact_details');
								$table_tags = $dbo->quoteName('#__tags');
								$table_menu = $dbo->quoteName('#__menu');
								
								$name_id = $dbo->quoteName('id');
								$name_catid = $dbo->quoteName('catid');
								$name_parentid = $dbo->quoteName('parent_id');
								$name_alias = $dbo->quoteName('alias');
								$name_language = $dbo->quoteName('language');
								$name_path = $dbo->quoteName('path');
								$name_level = $dbo->quoteName('level');
								$name_link = $dbo->quoteName('link');
								$name_home = $dbo->quoteName('home');
								$name_extension = $dbo->quoteName('extension');
								$name_published = $dbo->quoteName('published');
								$name_state = $dbo->quoteName('state');
								
								$val_alias = $dbo->quote($alias);
								$val_com_content = $dbo->quote('com_content');
								$val_com_contact = $dbo->quote('com_contact');
								$val_com_tags = $dbo->quote('com_tags');
								$val_path = $dbo->quote($path);
								$val_catalias = $dbo->quote($catalias);
								$val_language = $dbo->quote($langTAG);
								$val_all = $dbo->quote('*');
								
								$t1 = $dbo->quoteName('t1');
								$t2 = $dbo->quoteName('t2');
								
								$subquery = "";
								$extensions = array();
								
								if($this->_options['com_content'])
								{
									$subquery = "SELECT 
													$name_id, 
													$name_language, 
													$name_alias, 
													$name_catid, 
													null AS $name_path, 
													null AS $name_extension, 
													$name_state AS $name_published 
											FROM $table_content 
											WHERE $name_alias = $val_alias";
											
									$extensions[] = $val_com_content;
								}
								
								if($this->_options['com_contact'])
								{
									if ($subquery) {
										$subquery .= " UNION";
									}
									
									$subquery .= " SELECT 
													$name_id, 
													$name_language, 
													$name_alias, 
													$name_catid, 
													null AS $name_path, 
													null AS $name_extension, 
													$name_published 
												FROM $table_contact_details 
												WHERE $name_alias = $val_alias";
												
									$extensions[] = $val_com_contact;
								}
								
								if($subquery)
								{
									$subquery .= " UNION SELECT 
													$name_id, 
													$name_language, 
													$name_alias, 
													null AS $name_catid, 
													$name_path, 
													$name_extension, 
													$name_published 
												FROM $table_categories 
												WHERE $name_extension IN(" . implode(',', $extensions) . ") 
												AND ($name_alias = $val_alias
												OR $name_alias = $val_catalias)";
								}
								
								if($this->_options['com_tags'])
								{
									if ($subquery) {
										$subquery .= " UNION";
									}
									
									$subquery .= " SELECT 
													$name_id, 
													$name_language, 
													$name_alias, 
													null AS $name_catid, 
													$name_path,  
													$val_com_tags AS $name_extension, 
													$name_published
												FROM $table_tags 
												WHERE $name_alias = $val_alias";
								}
								
								$dbo->setQuery("
									SELECT 
										$t1.$name_id,
										$t1.$name_alias,
										$t1.$name_catid,
										$t1.$name_path,
										$t1.$name_extension 
									FROM (
										$subquery
									) AS $t1
									WHERE $t1.$name_language IN($val_language,$val_all)
									AND $t1.$name_published > 0
								");
								
								if($list = $dbo->loadObjectList())
								{	
									$items = array();
									$categories = array();
									
									foreach($list as $item)
									{
										if(isset($item->path))
										{
											$categories[] = $item;
										}
										else
										{
											$item->slug = $item->id . '-' . $item->alias;
											$items[] = $item;
										}
									}
									
									if(!empty($categories))
									{
										$firstLevelCandidates = array_filter($categories, function($category) use ($alias) {
											return $category->alias === $alias;
										});
										
										if($bestCategoryCandidate = $this->filterCategories($firstLevelCandidates))
										{
											$this->_sef = $bestCategoryCandidate->catsef;
										}
										elseif(!empty($items))
										{
											$secondLevelCandidates = array_filter($categories, function($category) use ($catalias) {
												return $category->alias === $catalias;
											});
											
											if($bestCategoryCandidate = $this->filterCategories($secondLevelCandidates, true))
											{
												foreach ($items as $bestItemCandidate) {
													if($bestCategoryCandidate->id == $bestItemCandidate->catid)
													{
														$this->_sef = $bestCategoryCandidate->catsef . '/' . $bestItemCandidate->slug;
														break;
													}
												}
											}
										}
										
									}
								}
							}
						}
						
						if($this->_sef)
						{
							$this->_router->attachParseRule(array($this, "parse"));
						}
					}
					
				}
			}
			
			$this->_router->attachBuildRule(array($this, "build"));
			
			if($this->_debug)
			{
				$this->script_execution_time("end", "onAfterInitialise");
			}
			
		}
		
	}
	
	
	PRIVATE FUNCTION getPrimaryLevelSef($menuItems)
	{
		$dbo = JFactory::getDbo();
		
		$table_content = $dbo->quoteName('#__content');
		$table_categories = $dbo->quoteName('#__categories');
		$table_contact_details = $dbo->quoteName('#__contact_details');
		$table_tags = $dbo->quoteName('#__tags');
		$t1 = $dbo->quoteName('t1');
		
		$name_id = $dbo->quoteName('id');
		$name_catid = $dbo->quoteName('catid');
		$name_parentid = $dbo->quoteName('parent_id');
		$name_alias = $dbo->quoteName('alias');
		$name_language = $dbo->quoteName('language');
		$name_extension = $dbo->quoteName('extension');
		$name_published = $dbo->quoteName('published');
		$name_state = $dbo->quoteName('state');
		
		$name_element = $dbo->quoteName('element');
		$val_element_article = $dbo->quote('com_content');
		$val_element_contact = $dbo->quote('com_contact');
		$val_element_tag = $dbo->quote('com_tags');
		
		$val_alias = $dbo->quote($this->_alias);
		$val_language = $dbo->quote($this->_language);
		$val_all = $dbo->quote('*');
		$extensions = array();
		$subquery = "";
		
		if($this->_options['com_content'])
		{
			$subquery .= "
				SELECT 
					$name_id,
					$name_alias,
					$name_catid AS $name_parentid, 
					$name_language, 
					$val_element_article AS $name_element, 
					$name_state AS $name_published
				FROM $table_content
			";
			$extensions[] = $dbo->quote('com_content');
		}
		
		if($this->_options['com_contact'])
		{
			if($subquery)
			{
				$subquery .= " UNION ";
			}
			
			$subquery .= "
				SELECT 
					$name_id,
					$name_alias,
					$name_catid AS $name_parentid, 
					$name_language, 
					$val_element_contact AS $name_element, 
					$name_published
				FROM $table_contact_details
			";
			$extensions[] = $dbo->quote('com_contact');
		}
		
		if($subquery)
		{
			$subquery .= " UNION ";
			$subquery .= "
				SELECT 
					$name_id,
					$name_alias,
					$name_parentid, 
					$name_language, 
					$name_extension AS $name_element, 
					$name_published
				FROM $table_categories
				WHERE $name_extension IN(". implode(",", $extensions) .")
			";
		}
		
		if($this->_options['com_tags'])
		{
			if($subquery)
			{
				$subquery .= " UNION ";
			}
			
			$subquery .= "
				SELECT 
					$name_id,
					$name_alias,					
					$name_parentid, 
					$name_language, 
					$val_element_tag AS $name_element, 
					$name_published
				FROM $table_tags
			";
			
		}
		
		$dbo->setQuery("
			SELECT
				$name_id,
				$name_parentid,
				$name_language,
				$name_element
			FROM (
				$subquery
			) AS $t1
			WHERE $t1.$name_alias = $val_alias
			AND $t1.$name_language IN($val_language,$val_all)
			AND $t1.$name_published > 0
		");
		
		if($contentItems = $dbo->loadObjectList())
		{
			if($menuItems)
			{
				foreach ($menuItems as $menuItem) {
					if (isset($menuItem->query['id']))
					{
						foreach ($contentItems as $contentItem)
						{
							if($contentItem->parent_id == $menuItem->query['id'] && 
								$contentItem->language == $menuItem->language)
							{
								return $contentItem->id . "-" . $this->_alias;
							}
						}
					}
				}
				
				foreach ($menuItems as $menuItem) {
					foreach ($contentItems as $contentItem) {
						if($contentItem->element == $menuItem->component)
						{
							return $contentItem->id . "-" . $this->_alias;
						}
					}
				}
			}
			else
			{
				return array_shift($contentItems)->id . "-" . $this->_alias;
			}
		}
	}
	
	
	PRIVATE FUNCTION matchMenuItems($menuItems, $categories)
	{
		foreach($menuItems as $menuItem)
		{
			if(isset($menuItem->query['id']))
			{
				foreach($categories as $category)
				{
					if($category->id == $menuItem->query['id'] && 
						$category->language == $menuItem->language)
					{
						return $category;
					}
				}
			}
		}
		
		foreach($menuItems as $menuItem)
		{
			foreach($categories as $category)
			{
				if($category->extension == $menuItem->component)
				{
					return $category;
				}
			}
		}
	}
	
	
	PRIVATE FUNCTION filterCategories($categories, $skip_first = false)
	{
		$menu_fragments = $this->_fragments;
		$bestCategoryCandidate = null;
		
		if($skip_first)
		{
			array_pop($menu_fragments);
		}
		
		$path_fragment = "";
		$path_fragments = array();
		$menu_fragments_num = count($menu_fragments);
		
		for($i = $menu_fragments_num - 1; $i >= 0; $i--)
		{
			$menuPath = implode('/', $menu_fragments);
			
			$menuItems = $this->_menu->getItems(
				array(
					'route', 
					'language',
					'home'
				), 
				array(
					$menuPath, 
					$this->_languages,
					0
				)
			);
			
			if(count($menuItems))
			{
				if($bestCategoryCandidate = $this->matchMenuItems($menuItems, $categories))
				{
					break;
				}
			}
			
			$filtered = array();
			
			foreach($categories as $category)
			{
				if($this->strend($category->path, $menu_fragments[$i] . $path_fragment))
				{
					$filtered[] = $category;
				}
			}
			
			if(empty($filtered))
			{
				break;
			}
			
			$categories = $filtered;
			$path_fragment = '/' . $menu_fragments[$i] . $path_fragment;
			array_unshift($path_fragments, array_pop($menu_fragments));
			
		}
		
		if(!$bestCategoryCandidate)
		{
			$menuPath = '';
			
			$menuItems = $this->_menu->getItems(
				array(
					'language',
					'home'
				), 
				array( 
					$this->_languages,
					1
				)
			);
			
			$bestCategoryCandidate = $this->matchMenuItems($menuItems, $categories);
		}
		else
		{
			$menuPath .= '/';
		}
		
		if($bestCategoryCandidate)
		{
			if($bestCategoryCandidate->extension === 'com_contact')
			{
				$level = count($path_fragments);
				
				if($level == 1)
				{
					$path_fragments[0] = $bestCategoryCandidate->id . '-' . $path_fragments[0];
				}
				else
				{
					$path_fragments = array_reverse($path_fragments);
					$category = JCategories::getInstance('contact')->get($bestCategoryCandidate->id);
					
					foreach($path_fragments as $key => $fragment)
					{
						if($category)
						{
							$path_fragments[$key] = $category->id . '-' . $fragment;
							$category = $category->getParent();
						}
					}
					
					$path_fragments = array_reverse($path_fragments);
					
				}
				
				$bestCategoryCandidate->catsef = $menuPath . implode('/', $path_fragments);
				
			}
			elseif($bestCategoryCandidate->extension === 'com_content')
			{
				$bestCategoryCandidate->catsef = $menuPath . $bestCategoryCandidate->id . '-' . implode('/', $path_fragments);
			}
		}
		
		return $bestCategoryCandidate;
		
	}
	
	
	PUBLIC FUNCTION parse(&$router, &$uri)
	{
		$uri->setPath($this->_sef);
	}
	
	
	PUBLIC FUNCTION build(&$siteRouter, &$uri)
	{
		$vars = array();
		
		if(spl_object_hash($siteRouter) === spl_object_hash($this->_router))
		{
			$query = $uri->getQuery(true);
			
			if(isset($query["option"], $query["id"]) && 
				in_array($query["option"], $this->_options) && !is_array($query["id"]))
			{	
				$router = clone $siteRouter;
				$url = $router->build($uri);
				$path = $url->getPath();
				
				if($path !== JURI::root(true) . '/index.php')
				{
					$queryString = $url->getQuery();
					$fragment = $url->getFragment();
					$entry_point = '';
					
					$len = strlen( str_replace( 'index.php', '', $uri->getPath() ) );
					
					if($len && !$this->_sefRewrite)
					{
						$entry_point = '/index.php/';
						$len += 10;
					}
					
					if($len += $this->_rootlen)
					{
						$path = $entry_point . substr($path, $len);
					}
					
					if($this->_sefSuffix)
					{
						if($pos = strrpos($path, '.'))
						{
							$path = substr_replace($path, '', $pos);
						}
					}
					
					$offset = 0;
		
					if(isset($query["Itemid"]))
					{
						$menuitem = $this->_menu->getItem($query["Itemid"]);
						
						if(!$menuitem->home)
						{
							$offset = strlen($menuitem->route);
						}
						
					}
					
					if($offset < strlen($path))
					{
						if(in_array($query["option"], array('com_contact', 'com_tags')))
						{
							$path = preg_replace("#(?<=.{" . $offset . "})/\d+-#", "/", $path);
						}
						else
						{
							$id = $this->getID_fragment($query["id"]);
							
							if($query["view"] === 'article')
							{
								$ending = "";
								$pos = strrpos($path, $id, $offset);
								
								if($pos !== false)
								{
									$ending = substr($path, $pos + strlen($id));
									$path = substr_replace($path, "/", $pos);
								}
								
								if(isset($query["catid"]) && $offset < strlen($path) - 1)
								{
									$catid = $this->getID_fragment($query["catid"]);
									$path = $this->remove_catID($path, $catid, $query, $offset);
								}
								
								$path .= $ending;
								
							}
							else
							{
								$path = $this->remove_catID($path, $id, $query, $offset);
							}
							
						}
						
						$uri->setPath($path);
						$uri->setQuery($queryString);
						$uri->setFragment($fragment);
						
					}
				}
			}
		}
		
		return $vars;
	}
	
	
	PRIVATE FUNCTION getID_fragment($stringID)
	{
		return preg_replace('#[^\d]*([\d]+).*#', '/$1-', (string) $stringID);
	}
	
	
	PRIVATE FUNCTION remove_catID($path, $catid, $query, $offset)
	{
		$pos = strpos($path, $catid, $offset);
		
		if($pos !== false)
		{
			$path = substr_replace($path, "/", $pos, strlen($catid));
		}
		
		return $path;
		
	}
	
	
	PUBLIC FUNCTION onAfterRoute()
	{
		$duplicate_handling = $this->params->get('duplicate_handling');
		
		if($this->_execute && $duplicate_handling)
		{	
			$app = JFactory::getApplication();
			$option = $app->input->get("option");
			
			if(in_array($option, $this->_options) && 
				($option !== "com_content" || $app->input->get("view") !== "archive"))
			{
				$vars = $this->_router->getVars();				
				$url_parts = explode("?", JRoute::_('index.php?' . http_build_query($vars), false), 2);
				$path = $url_parts[0];
				
				if($this->_debug)
				{
					$this->script_execution_time("start");
				}
				
				$uri = JURI::getInstance();
				$canonical = $uri->toString(array('scheme', 'host', 'port')) . $path;
				$root = JURI::root(true);
				
				if($canonical !== JURI::current() && $path !== "$root/index.php" &&
					stripos($path, "$root/component") !== 0)
				{
					if($duplicate_handling == 1 && 
						(empty($url_parts[1]) || !preg_match("#\b(?:cat|Item)?id=#i", $url_parts[1])))
					{
						if($query_string = $uri->getQuery())
						{
							$canonical .= '?' . $query_string;
						}
						$app->redirect($canonical, 301);
					}
					else
					{
						throw new Exception("Not found", 404);
					}
				}
				
				if($this->_debug)
				{
					$this->script_execution_time("end", "onAfterRoute");
				}
				
			}
		}
	}
	
	
	PUBLIC FUNCTION onAfterRender()
	{
		if($this->_execute)
		{
			if($this->_debug)
			{
				$this->script_execution_time("start");
			}
			
			$app = JFactory::getApplication();
			$html = $app->getBody();
			
			if($this->_debug)
			{
				$html = $this->script_execution_time("end", "onAfterRender", $html);
			}
			
			$app->setBody($html);
		}
		
	}
	
	
	PRIVATE FUNCTION strend($haystack, $needle)
	{
		$offset = strlen($haystack) - strlen($needle);	
		return $offset >= 0 && strpos($haystack, $needle, $offset) !== false;
	}
	
	
	PRIVATE FUNCTION script_execution_time($timestamp, $caller = "", $html = null)
	{	
		static $start, $result = array(), $format = 5;
		
		if($timestamp === "start")
		{
			$start = microtime(true);
		}
		elseif($timestamp === "end")
		{
			$result[$caller] = number_format((microtime(true) - $start), $format);
		}
		
		if($html)
		{
			$total = number_format((array_sum($result)), $format);
			
			if($this->_debug == 1)
			{
				$notice = "\\n SEF WIZARD PLUGIN (PHP SCRIPT EXECUTION TIME):";
				foreach($result as $name => $time)
				{
					$notice .= "\\n $name: $time sec.";
				}	
				$notice .= "\\n Total execution time: {$total} sec.";
				return preg_replace("#(</head>)#", "<script>if('console' in window && console.log) console.log('$notice')</script> $1", $html);
			}
			else
			{
				$notice = "<div><p style='margin: 15px'>SEF WIZARD PLUGIN (PHP SCRIPT EXECUTION TIME):";
				foreach($result as $name => $time)
				{
					$notice .= "<br>$name: $time sec.";
				}	
				$notice .= "<br>total execution time: <b>{$total} sec.</b></div>";
				return preg_replace("#<body[^>]*>#", "$0{$notice}", $html);
			}
		}
		
	}

}
