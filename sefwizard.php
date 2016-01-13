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
		$_menu = null,
		$_rootlen = 0,
		$_alias = '',
		$_menuItemCandidates = array(),
		$_homePageCandidates = array();
		
	
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
			$fragments = array_values(array_filter($fragments));
			
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
					
					$languages = array($langTAG, '*');
					$this->_language = $langTAG;
					$this->_alias = $alias;
					
					$attributes = array('route', 'language');
					$attributeValues = array($path, $languages);
					
					if(!$catalias)
					{
						$attributes[] = 'home';
						$attributeValues[] = 0;
					}
					
					$menuItemsMatchingRoute = $this->_menu->getItems($attributes, $attributeValues);
					
					if(empty($menuItemsMatchingRoute))
					{
						$routes = array($fragments[0]);
						$menuFragments = array($fragments[$level-1]);
						
						for($i=1; $i<$level; $i++)
						{
							$routes[] = $routes[$i-1] . '/' . $fragments[$i];
							$menuFragments[] = $fragments[$level-$i-1] . '/' . $menuFragments[$i-1];
						}
						
						$routes = array_reverse($routes);
						
						$menuItems = $this->_menu->getItems(
							array('language', 'component'), 
							array($languages, $options)
						);
						
						$primaryLevelMenuItemCandidates = array();
						$menuItemCandidates = array();
						$homePageCandidates = array();
						$categoryFragments = array();
						$menuFragments = array();
						
						foreach($menuItems as $menuItem)
						{
							if($menuItem->home)
							{
								$homePageCandidates[] = $menuItem;
							}
							elseif(in_array($menuItem->route, $routes))
							{
								if($catalias && $menuItem->route === $routes[1])
								{
									$primaryLevelMenuItemCandidates[] = $menuItem;
								}
								$menuItemCandidates[] = $menuItem;
							}
						}
						
						if(!empty($menuItemCandidates))
						{
							usort($menuItemCandidates, function($a, $b) {
								return $a->level < $b->level;
							});
							for($i = $menuItemCandidates[0]->level; $i < $level; $i++)
							{
								$categoryFragments[] = $fragments[$i];
							}
							$menuFragments = explode('/', $menuItemCandidates[0]->route);
						}
						else
						{
							$categoryFragments = $fragments;
						}
						
						$this->_menuItemCandidates = $menuItemCandidates;
						$this->_homePageCandidates = $homePageCandidates;
						
						if(!$catalias)
						{
							if(count($homePageCandidates))
							{
								$this->_sef = $this->getPrimaryLevelSef($homePageCandidates);
							}
						}
						else
						{
							$primaryLevelSef = null;
							
							if($fragments[0] === 'component')
							{
								$primaryLevelSef = $this->getPrimaryLevelSef(null);
							}
							elseif(count($primaryLevelMenuItemCandidates))
							{	
								$primaryLevelSef = $this->getPrimaryLevelSef($primaryLevelMenuItemCandidates);
							}
							
							if($primaryLevelSef)
							{
								$this->_sef = $routes[1] . "/" . $primaryLevelSef; 
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
													$name_parentid AS $name_catid, 
													$name_path, 
													$name_extension, 
													$name_published 
												FROM $table_categories 
												WHERE $name_extension IN(" . implode(',', $extensions) . ") 
												AND (
													$name_alias = $val_alias OR $name_alias = $val_catalias
												)";
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
													$name_parentid AS $name_catid, 
													$name_path,  
													$val_com_tags AS $name_extension, 
													$name_published
												FROM $table_tags 
												WHERE $name_alias = $val_alias";
								}
								
								$primaryLevelCategoryFragments = $categoryFragments;
								$primaryLevelCategoryFragment = implode('/', $categoryFragments);
								
								if(count($categoryFragments) > 2)
								{
									array_pop($categoryFragments);
									
									$secondaryLevelCategoryFragments = $categoryFragments;
									$secondaryLevelCategoryFragment = implode('/', $categoryFragments);
								}
								else
								{
									$secondaryLevelCategoryFragments = array($catalias);
									$secondaryLevelCategoryFragment = $catalias;
								}
								
								$dbo->setQuery("
									SELECT 
										$name_id,
										$name_alias,
										$name_catid,
										$name_path,
										$name_extension,
										$name_language
									FROM (
										$subquery
									) AS $t1
									WHERE $t1.$name_language IN($val_language,$val_all)
									AND $t1.$name_published > 0
								");
								
								if($list = $dbo->loadObjectList())
								{	
									$items = array();
									
									$primaryLevelCategories = array();
									$secondaryLevelCategories = array();
									
									foreach($list as $item)
									{
										if(isset($item->path))
										{
											$item->ancestorChain = array();
											
											if($this->strend($item->path, $primaryLevelCategoryFragment))
											{
												$primaryLevelCategories[] = $item;
											}
											if($this->strend($item->path, $secondaryLevelCategoryFragment))
											{
												$secondaryLevelCategories[] = $item;
											}
										}
										else
										{
											$item->slug = $item->id . '-' . $item->alias;
											$items[] = $item;
										}
									}
									
									if($bestCategoryCandidate = $this->filterCategories($primaryLevelCategories, $primaryLevelCategoryFragments, $menuFragments))
									{
										$this->_sef = $bestCategoryCandidate->catsef;
									}
									elseif(!empty($items))
									{
										if($bestCategoryCandidate = $this->filterCategories($secondaryLevelCategories, $secondaryLevelCategoryFragments, $menuFragments))
										{
											foreach($items as $bestItemCandidate)
											{
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
				foreach($menuItems as $menuItem)
				{
					if(isset($menuItem->query['id']))
					{
						foreach ($contentItems as $contentItem)
						{
							if($contentItem->parent_id == $menuItem->query['id'])
							{
								return $contentItem->id . "-" . $this->_alias;
							}
						}
					}
				}
				
				foreach($menuItems as $menuItem)
				{
					foreach($contentItems as $contentItem)
					{
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
	
	
	PRIVATE FUNCTION matchMenuItems($menuItems, $categories, $level)
	{
		if(!empty($menuItems))
		{
			if(count($categories) === 1)
			{
				if($level <= 2 || $categories[0]->extension !== 'com_contact')
				{
					if($level === 2)
					{
						$categories[0]->ancestorChain[] = $categories[0]->catid;
					}
					
					$categories[0]->ancestorChain[] = $categories[0]->id;
					return $categories[0];
					
				}
			}
			
			$dbo = JFactory::getDbo();
			
			$name_id = $dbo->quoteName('id');
			$name_parentid = $dbo->quoteName('parent_id');
			$name_path = $dbo->quoteName('path');
			$table_categories = $dbo->quoteName('#__categories');
			$t1 = $dbo->quoteName('t1');
			
			foreach($menuItems as $menuItem)
			{
				if(isset($menuItem->query['id']))
				{
					$select = array();
					$join = array();
					
					for($i = 1; $i <= $level; $i++)
					{
						$idx = $i;
						$next = $i+1;
						
						$tblcur = $dbo->quoteName("t$idx");
						$tblnext = $dbo->quoteName("t$next");
						
						$select[] = "
							$tblcur.$name_id, $tblcur.$name_path
						";
						
						if($i < $level)
						{
							$join[] = "
								INNER JOIN $table_categories AS $tblnext ON $tblcur.$name_id = $tblnext.$name_parentid
							";
						}
					}
					
					$dbo->setQuery("
						SELECT " . implode(',', $select) . 
							" FROM $table_categories AS $t1 " . 
								implode(' ', $join) . 
									" WHERE $t1.$name_parentid = " . (int) $menuItem->query['id']
					);
					
					if($descendants = $dbo->loadRowList())
					{
						foreach($descendants as $descendant)
						{
							$descendantPath = array_pop($descendant);
							$descendantID = array_pop($descendant);
							
							foreach($categories as $category)
							{
								if($descendantID == $category->id &&
									$descendantPath == $category->path)
								{
									foreach($descendant as $key => $val)
									{
										if($key % 2 === 0)
										{
											$category->ancestorChain[] = $val;
										}
									}
									
									$category->ancestorChain[] = $descendantID;
									return $category;
									
								}
							}
						}
					}
				}
			}
			
			foreach($menuItems as $menuItem)
			{
				foreach($categories as $category)
				{
					if($menuItem->component === $category->extension)
					{
						if($category->extension === 'com_contact')
						{
							if($level >= 2)
							{
								if($level > 2)
								{
									$select = array();
									$join = array();
									
									for($i = 1; $i <= $level - 2; $i++)
									{
										$idx = $i;
										$next = $i+1;
										
										$tblcur = $dbo->quoteName("t$idx");
										$tblnext = $dbo->quoteName("t$next");
										
										$select[] = "
											$tblcur.$name_parentid
										";
										
										if($i < $level)
										{
											$join[] = "
												INNER JOIN $table_categories AS $tblnext ON $tblcur.$name_parentid = $tblnext.$name_id
											";
										}
									}
									
									$dbo->setQuery("
										SELECT " . implode(',', $select) . 
											" FROM $table_categories AS $t1 " . 
												implode(' ', $join) . 
													" WHERE $t1.$name_id = " . (int) $category->catid
									);
									
									if($descendants = $dbo->loadRow())
									{
										foreach($descendants as $key => $descendant)
										{
											array_unshift($category->ancestorChain, $descendant);
										}
									}
									
									if(!$descendants || count($category->ancestorChain) !== $level - 2)
									{
										return null;
									}
									
								}
								
								$category->ancestorChain[] = $category->catid;
								
							}
							
							$category->ancestorChain[] = $category->id;
							
						}
						
						return $category;
						
					}
				}
			}
		}
	}
	
	
	PRIVATE FUNCTION filterCategories($categories, $categoryFragments, $menuFragments)
	{
		$bestCategoryCandidate = null;
		
		if(!empty($menuFragments))
		{
			while(!empty($categories) && !empty($menuFragments))
			{
				$menuRoute = implode($menuFragments, '/');
				$level = count($categoryFragments);
				
				$menuItems = array_filter($this->_menuItemCandidates, function($menuItem) use ($menuRoute) {
					return $menuItem->route === $menuRoute;
				});
				
				if($bestCategoryCandidate = $this->matchMenuItems($menuItems, $categories, $level))
				{
					break;
				}
				else
				{
					array_unshift($categoryFragments, $menuFragments);
					$categoryFragment = implode('/', $menuFragments);
					$filtered = array();
					
					foreach($categories as $category)
					{
						if($this->strend($category->path, $categoryFragment))
						{
							$filtered[] = $category;
						}
					}
					
					$categories = $filtered;
					
				}
			}
		}
		elseif(!empty($categories))
		{
			$level = count($categoryFragments);
			$bestCategoryCandidate = $this->matchMenuItems($this->_homePageCandidates, $categories, $level);
		}
		
		if($bestCategoryCandidate)
		{
			if($bestCategoryCandidate->extension === 'com_contact')
			{
				foreach($categoryFragments as $key => &$fragment)
				{
					$fragment = $bestCategoryCandidate->ancestorChain[$key] . '-' . $fragment;
				}
			}
			else
			{
				$categoryFragments[0] = $bestCategoryCandidate->id . '-' . $categoryFragments[0];
			}
			
			$bestCategoryCandidate->catsef = implode('/', array_merge($menuFragments, $categoryFragments));
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
