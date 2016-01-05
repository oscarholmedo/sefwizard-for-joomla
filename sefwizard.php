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

class PlgSystemSefwizard extends JPlugin
{
	PRIVATE
		$_sef = "",
		$_router = null,
		$_sefRewrite = null,
		$_options = array(),
		$_debug = null,
		$_execute = false,
		$_language = '',
		$_fragments = array();
		
	
	PUBLIC FUNCTION onAfterInitialise()
	{
		$app = JFactory::getApplication();
		
		if($app->isSite() && JFactory::getConfig()->get('sef'))
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
			$this->_sefRewrite = JFactory::getConfig()->get('sef_rewrite');
			
			if($this->_debug = $this->params->get("debug"))
			{
				$this->script_execution_time("start");
			}
			
			$dbo = JFactory::getDbo();
			
			$uri  = JURI::getInstance();
			$path = $uri->getPath();
			$len  = strlen(JURI::root(true));
			
			if(stripos($path, '/index.php/') === $len)
			{
				$len += 10;
			}
			
			if($len)
			{
				$path = substr($path, $len);
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
					$name_catpath = $dbo->quoteName('path');
					$name_language = $dbo->quoteName('language');
					$name_link = $dbo->quoteName('link');
					$name_home = $dbo->quoteName('home');
					$name_extension = $dbo->quoteName('extension');
					
					$table_menu = $dbo->quoteName('#__menu');
					$table_content = $dbo->quoteName('#__content');
					$table_categories = $dbo->quoteName('#__categories');
					$table_contact_details = $dbo->quoteName('#__contact_details');
					$table_tags = $dbo->quoteName('#__tags');
					
					$t1 = $dbo->quoteName('t1');
					$t2 = $dbo->quoteName('t2');
					
					$subquery = "";
					$subcond = "";
					
					if($this->_options['com_content'])
					{
						$subquery = "SELECT 
										$name_id, 
										$name_language, 
										$name_alias, 
										$name_catid, 
										null AS $name_catpath, 
										null AS $name_extension
								FROM $table_content
								WHERE $name_alias = $val_alias";
						
						$subcond = "$name_link LIKE " . $dbo->quote('index.php?option=com_content&view=category%');
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
										null AS $name_catpath, 
										null AS $name_extension
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
										$name_extension
									FROM $table_categories
									WHERE $name_extension IN($val_com_content,$val_com_contact)
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
										$val_com_tags AS $name_extension
									FROM $table_tags
									WHERE $name_alias = $val_alias";
					}
					
					$dbo->setQuery("
						SELECT 
							$t1.$name_id, 
							$t1.$name_alias, 
							$t1.$name_catid, 
							$t1.$name_catpath, 
							$t1.$name_extension 
						FROM (
							$subquery
						) AS $t1
						LEFT JOIN $table_menu AS $t2 ON $t2.$name_path = $val_path
							WHERE $t2.$name_path IS null
								AND $t1.$name_language IN($val_language,$val_all)
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
						
						if($category = $this->getItem($categories))
						{
							$skip_categories = false;
							
							if($catalias)
							{
								$skip_categories = array_filter($items, function($item) use ($catalias, $category, $path)
								{
									return $item->alias === $catalias && 
										$this->strend($path, $category->path . '/' . $item->alias);
								});
							}
							
							if(empty($skip_categories))
							{
								$this->_sef = preg_replace('#(./)?(' . preg_quote($category->first_fragment, '#') . ')$#', '${1}' . $category->id . '-$2', $path);
							}
						}
						
						if(!$this->_sef)
						{
							if($catalias)
							{
								$categories = array_filter($categories, function($category) use ($catalias) {
									return $category->alias === $catalias;
								});
								
								$filtered = array();
								
								if($categories)
								{
									foreach ($items as $item) {
										foreach ($categories as $category) {
											if (!$item->path && $item->catid === $category->id)
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
								
								if($item = $this->getItem($filtered, true))
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
			
			if(isset($query["option"], $query["id"]) && in_array($query["option"], $this->_options))
			{	
				$router = clone $siteRouter;
				$url = $router->build($uri);
				
				$path = $url->toString(array("path", "query", "fragment"));
				$fragment = '';
				
				$len = strlen( str_replace( 'index.php', '', $uri->getPath() ) );
				
				if($len)
				{
					if(!$this->_sefRewrite)
					{
						$fragment = '/index.php/';
						$len += 10;
					}
				}
				
				$len += strlen( JURI::root(true) );
				
				if($len)
				{
					$path = $fragment . substr($path, $len);
				}
				
				$subpattern = preg_replace('#[^\d]*([\d]+).*#', '$1', $query["id"]);
				
				if(isset($query["catid"]))
				{
					$subpattern = '(?:' . $subpattern . '|' . preg_replace('#[^\d]*([\d]+).*#', '$1', $query["catid"]) . ')';
				}
				
				$path = preg_replace("#/{$subpattern}-#", "/", $path);
				
				$uri->setPath($path);
				$uri->setQuery($vars);
				
			}
		}
		
		return $vars;
	}
	
	
	PUBLIC FUNCTION onAfterRoute()
	{
		$param = $this->params->get('duplicate_handling');
		
		if($this->_execute && $param)
		{	
			$app = JFactory::getApplication();
			$option = $app->input->get("option");
			
			if(in_array($option, $this->_options))
			{
				$vars = $this->_router->getVars();				
				$url = preg_replace('#\?.*$#', '', JRoute::_('index.php?' . http_build_query($vars), false));
				
				if($this->_debug)
				{
					$this->script_execution_time("start");
				}
				
				$uri = JURI::getInstance();
				$canonical = $uri->toString(array('scheme', 'host', 'port')) . $url;
				
				if($canonical !== JURI::current())
				{
					if($url !== JURI::root(true) . "/index.php")
					{
						if($param == 1 && !$uri->getQuery())
						{
							$app->redirect($canonical, 301);
						}
						else if( !( $option === "com_content" && $app->input->get("view") === "archive" ) )
						{
							throw new Exception("Not found", 404);
						}
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
	
	
	PRIVATE FUNCTION getItem($list, $skip_first = false)
	{
		if(!empty($list))
		{
			$path_fragment = "";
			$path_fragments = array();
			$reversed_fragments = array_reverse($this->_fragments);
			
			if($skip_first)
			{
				array_shift($reversed_fragments);
			}
			
			foreach($reversed_fragments as $key => $fragment)
			{	
				$sorted = array_filter($list, function($item) use ($fragment, $path_fragment)
				{
					$needle = $fragment . $path_fragment;
					$offset = strlen($item->path) - strlen($needle);
					
					return $offset >= 0 && strpos($item->path, $needle, $offset) !== false;
				});
				
				if(!count($sorted))
				{
					break;
				}
				
				$reversed_fragments[$key] = null;
				array_unshift($path_fragments, $fragment);
				
				$path_fragment = '/' . $fragment . $path_fragment;
				$list = $sorted;
				
			}
			
			if($path_fragment)
			{	
				$menu_fragments = array_values(array_filter($reversed_fragments));
				
				if(count($list) === 1 || !count($menu_fragments))
				{
					$menu_fragments[] = $path_fragments[0];
				}
				
				$computed_menu_path = implode('/', $menu_fragments);
				
				$dbo = JFactory::getDbo();
				$val_all = $dbo->quote('*');
				$val_language = $dbo->quote($this->_language);
				
				$dbo->setQuery($dbo->getQuery(true)
					->select($dbo->quoteName('link'))
					->from($dbo->quoteName('#__menu'))
					->where($dbo->quoteName('path') . "=" . $dbo->quote($computed_menu_path))
					->where($dbo->quoteName('language') . " IN($val_language,$val_all)")
				);
				
				if($menuitemlink = $dbo->loadResult())
				{
					if($menu_fragments[0] === $path_fragments[0])
					{
						if(count($path_fragments) > 1)
						{
							array_shift($path_fragments);
						}
						else
						{
							foreach($list as $item)
							{
								if(!$item->extension && strpos($menuitemlink, "&id=" . $item->catid))
								{
									$item->first_fragment = null;
									return $item;
								}
							}
						}
					}
					
					foreach($list as $item)
					{
						if($item->extension && strpos($menuitemlink, "option=" . $item->extension))
						{
							$item->first_fragment = $item->extension === 'com_tags' ? 
								array_pop($path_fragments) : implode('/', $path_fragments);
							return $item;
						}
					}
				}
				
				$item = array_shift($list);
				
				$item->first_fragment = $item->extension === 'com_tags' ? 
					array_pop($path_fragments) : implode('/', $path_fragments);
				
				return $item;
			}
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
