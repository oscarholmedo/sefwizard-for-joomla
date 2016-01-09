<?php

/* SEF Wizard extension for Joomla 3.x - Version 1.0.4
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
			
			$continue = array_filter($this->_options);
			
			if(empty($continue))
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
			
			if(stripos($path, '/index.php/') === $len)
			{
				$len += 10;
			}
			
			if($len)
			{
				$path = substr($path, $len);
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
				
				$this->_language = $langTAG;
				
				if($level = count($fragments))
				{
					$path = implode('/', $fragments);
					$this->_fragments = $fragments;
					
					$alias = $fragments[$level-1];
					$catalias = isset($fragments[$level-2]) ? $fragments[$level-2] : '';
					
					$val_alias = $dbo->quote($alias);
					$val_path = $dbo->quote($path);
					$val_language = $dbo->quote($langTAG);
					$val_catalias = $dbo->quote($catalias);
					$val_com_content = $dbo->quote('com_content');
					$val_com_contact = $dbo->quote('com_contact');
					$val_com_tags = $dbo->quote('com_tags');
					$val_all = $dbo->quote('*');
					
					$name_id = $dbo->quoteName('id');
					$name_catid = $dbo->quoteName('catid');
					$name_alias = $dbo->quoteName('alias');
					$name_path = $dbo->quoteName('path');
					$name_level = $dbo->quoteName('level');
					$name_language = $dbo->quoteName('language');
					$name_link = $dbo->quoteName('link');
					$name_home = $dbo->quoteName('home');
					$name_extension = $dbo->quoteName('extension');
					$name_published = $dbo->quoteName('published');
					$name_state = $dbo->quoteName('state');
					
					$table_menu = $dbo->quoteName('#__menu');
					$table_content = $dbo->quoteName('#__content');
					$table_categories = $dbo->quoteName('#__categories');
					$table_contact_details = $dbo->quoteName('#__contact_details');
					$table_tags = $dbo->quoteName('#__tags');
					
					$t1 = $dbo->quoteName('t1');
					$t2 = $dbo->quoteName('t2');
					
					$subquery = "";
					$subcond = "";
					$extensions = array();
					
					if($this->_options['com_content'])
					{
						$extensions[] = $val_com_content;
						
						$subquery = "SELECT 
										$name_id, 
										$name_language, 
										$name_alias, 
										$name_catid, 
										null AS $name_path, 
										null AS $name_level, 
										null AS $name_extension, 
										$name_state AS $name_published 
								FROM $table_content 
								WHERE $name_alias = $val_alias";
						
						$subcond = "$name_link LIKE " . $dbo->quote('index.php?option=com_content&view=category%');
					}
					
					if($this->_options['com_contact'])
					{
						$extensions[] = $val_com_contact;
						
						if ($subquery) {
							$subquery .= " UNION";
						}
						
						$subquery .= " SELECT 
										$name_id, 
										$name_language, 
										$name_alias, 
										$name_catid, 
										null AS $name_path, 
										null AS $name_level, 
										null AS $name_extension, 
										$name_published 
									FROM $table_contact_details 
									WHERE $name_alias = $val_alias";
						
						$subcond .= $subcond ? ' OR ' : '';
						$subcond .= "$name_link LIKE " . $dbo->quote('index.php?option=com_contact&view=category%');
					}
					
					if($subquery)
					{
						$subquery .= " UNION SELECT 
										$name_id, 
										$name_language, 
										$name_alias, 
										null AS $name_catid, 
										$name_path, 
										$name_level, 
										$name_extension, 
										$name_published 
									FROM $table_categories 
									WHERE $name_extension IN(" . implode(',', $extensions) . ") 
									AND $name_alias = $val_alias";
									
						if ($catalias) {
							$subquery .= " OR $name_alias = $val_catalias";
						}
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
										null as $name_level, 
										$val_com_tags AS $name_extension, 
										$name_published
									FROM $table_tags 
									WHERE $name_alias = $val_alias";
									
						$subcond .= $subcond ? ' OR ' : '';
						$subcond .= "$name_link LIKE " . $dbo->quote('index.php?option=com_tags%');
					}
					
					$dbo->setQuery("
						SELECT 
							$t1.$name_id,
							$t1.$name_alias,
							$t1.$name_catid,
							$t1.$name_path,
							$t1.$name_level,
							$t1.$name_extension, 
							$t1.$name_published
						FROM (
							$subquery
						) AS $t1
						LEFT JOIN $table_menu AS $t2 ON $t2.$name_path = $val_path
							WHERE ($t2.$name_path IS null OR $t2.$name_published <> 1)
								AND $t1.$name_language IN($val_language,$val_all)
									AND $t1.$name_published > 0
						ORDER BY $name_level DESC
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
								$items[] = $item;
							}
						}
						
						if($category = $this->getCategory($items, $categories, $catalias, $subcond))
						{
							$this->_sef = preg_replace('#(./)?(' . preg_quote($category->first_fragment, '#') . ')$#', '${1}' . $category->id . '-$2', $path);
						}
						elseif($catalias)
						{
							$categories = array_filter($categories, function($category) use ($catalias) {
								return $category->alias === $catalias;
							});
							
							$filtered = array();
							
							if(count($categories))
							{
								foreach ($items as $item) {
									foreach ($categories as $category) {
										if (!$item->path && $item->catid == $category->id)
										{
											$item->path = $category->path;
											$filtered[] = $item;
										}
									}
								}
							}
							else
							{
								foreach ($items as $item) {
									$item->path = $catalias;
									$filtered[] = $item;
								}
							}
							
							if($item = $this->getCategory($items, $filtered, $catalias, $subcond, true))
							{
								if($item->first_fragment)
								{
									$pattern = '#(./)?(' . preg_quote($item->first_fragment, '#') . ')/([^/]+)$#';
									$replacement = '${1}' . $item->catid . "-$2/{$item->id}-$3";
								}
								else
								{
									$pattern = '#([^/]+)$#';
									$replacement = $item->id . '-$1';
								}
								
								$this->_sef = preg_replace($pattern, $replacement, $path);
							}
						}
						else
						{
							$dbo->setQuery("
								SELECT $name_link
									FROM $table_menu
										WHERE $name_language IN($val_language,$val_all)
											AND $name_home = 1
												AND ($subcond)
							");			
							
							if($menu_items = $dbo->loadObjectList())
							{
								foreach ($menu_items as $menu_item) {
									foreach ($items as $item) {
										if(strpos($menu_item->link, "&id=" . $item->catid))
										{
											$this->_sef = preg_replace("#([^/]+$)#", $item->id . "-$1", $path);
											break 2;
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
					
					$queryString = $queryString ? "?{$queryString}" : '';
					$fragment = $fragment ? "#{$fragment}" : '';
					
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
						$route = '/' . $this->_menu->getItem($query["Itemid"])->route;
						$offset = strlen($route);
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
						
						$uri->setPath($path . $queryString . $fragment);
						$uri->setQuery($vars);
						
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
	
	
	PRIVATE FUNCTION getCategory($items, $categories, $catalias, $subcond, $skip_first = false)
	{
		if(!empty($categories))
		{
			$category = null;
			
			$path_fragment = "";
			$path_fragments = array();
			
			$menu_fragments = $this->_fragments;
			
			if($skip_first)
			{
				array_pop($menu_fragments);
			}
			
			$menu_fragments_num = count($menu_fragments);
			
			for($i = $menu_fragments_num - 1; $i >= 0; $i--)
			{
				$filtered = array();
				$fragment = $menu_fragments[$i];
				
				foreach($categories as $ctg)
				{
					if($this->strend($ctg->path, $fragment . $path_fragment))
					{
						$filtered[] = $ctg;
					}
				}
				
				array_unshift($path_fragments, array_pop($menu_fragments));
				
				if(!count($filtered))
				{
					break;
				}
				
				$categories = $filtered;
				$path_fragment = '/' . $fragment . $path_fragment;
				
			}
			
			if($path_fragment)
			{
				if(!count($menu_fragments))
				{
					$menu_fragments[] = $path_fragments[0];
				}
				
				if($menu_fragments[0] !== 'component')
				{
					$computed_menu_path = implode('/', $menu_fragments);
					
					$dbo = JFactory::getDbo();
					$name_link = $dbo->quoteName('link');
					$name_level = $dbo->quoteName('level');
					$name_lang = $dbo->quoteName('language');
					$name_published = $dbo->quoteName('published');
					$name_path = $dbo->quoteName('path');
					$name_home = $dbo->quoteName('home');
					
					$val_all = $dbo->quote('*');
					$val_lang = $dbo->quote($this->_language);
					$val_computed_menu_path = $dbo->quote($computed_menu_path);
					
					$table_menu = $dbo->quoteName('#__menu');
					
					$dbo->setQuery("
						SELECT $name_link, $name_level, $name_path, $name_home
						FROM $table_menu
						WHERE $name_lang IN($val_lang,$val_all)
						AND $name_published = 1
						AND (
							$name_path = $val_computed_menu_path
							OR ($name_home = 1 AND ($subcond))
						)
						ORDER BY $name_home
					");
					
					if($menuitem = $dbo->loadObject())
					{
						if(!$menuitem->home)
						{
							array_shift($path_fragments);
						}
						else
						{
							$menuitem->level -= 1;
						}
						
						$categories = array_filter($categories, function($category) use ($menuitem) {
							return strpos($menuitem->link, "option=" . $category->extension);
						});
						
						if(count($categories) > 1)
						{
							if(preg_match("#.+?([\d]+)$#", $menuitem->link, $matches))
							{
								$name_alias = $dbo->quoteName('alias');
								$table_categories = $dbo->quoteName('#__categories');
								$name_id = $dbo->quoteName('id');
								$val_id = (int) $matches[1];
								
								$dbo->setQuery("
									SELECT $name_alias
									FROM $table_categories
									WHERE $name_id = $val_id
									AND $name_published = 1
									AND $name_lang IN($val_lang,$val_all)
								");
								
								if($parent_catalias = $dbo->loadResult())
								{
									$categories = array_filter($categories, function($category) use ($parent_catalias, $val_id)
									{
										return strpos('/' . $category->path, '/' . $parent_catalias . '/') !== false || 
											$category->catid == $val_id;
									});
								}
							}
						}
						
						if(count($categories) > 1)
						{
							$categories = array_filter($categories, function($category) use ($menuitem, $menu_fragments_num)
							{
								return $category->level + $menuitem->level - 1 === $menu_fragments_num;
							});
						}
						
						if(!count($categories))
						{
							return null;
						}
					}
				}
				elseif(count($path_fragments) > 1)
				{
					array_shift($path_fragments);
				}
				
				$category = array_shift($categories);
				
				if(empty($path_fragments) && $category->extension)
				{
					$path_fragments[] = array_shift($menu_fragments);
				}
				
				$category->first_fragment = $category->extension === 'com_tags' ? 
					array_pop($path_fragments) : implode('/', $path_fragments);
				
				
				if($catalias)
				{
					foreach($items as $item)
					{
						if($item->alias === $catalias && $item->catid == $category->id && 
							$this->strend($category->first_fragment, $category->path))
						{
							return null;
						}
					}
				}
				
			}
			
			return $category;
			
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
