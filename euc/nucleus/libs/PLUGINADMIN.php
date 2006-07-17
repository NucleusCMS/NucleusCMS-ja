<?php

/**
  * Nucleus: PHP/MySQL Weblog CMS (http://nucleuscms.org/) 
  * Copyright (C) 2002-2005 The Nucleus Group
  *
  * This program is free software; you can redistribute it and/or
  * modify it under the terms of the GNU General Public License
  * as published by the Free Software Foundation; either version 2
  * of the License, or (at your option) any later version.
  * (see nucleus/documentation/index.html#license for more info)
  *
  * code to make it easier to create plugin admin areas
  *
  * $Id: PLUGINADMIN.php,v 1.3 2005-03-16 08:10:35 kimitake Exp $
  * $NucleusJP: PLUGINADMIN.php,v 1.3 2005/03/12 06:19:05 kimitake Exp $
  */

global $HTTP_GET_VARS, $HTTP_POST_VARS, $HTTP_COOKIE_VARS, $HTTP_ENV_VARS, $HTTP_POST_FILES, $HTTP_SESSION_VARS;
$aVarsToCheck = array('DIR_LIBS');
foreach ($aVarsToCheck as $varName)
{
	if (phpversion() >= '4.1.0')
	{
		if (   isset($_GET[$varName]) 
			|| isset($_POST[$varName]) 
			|| isset($_COOKIE[$varName])
			|| isset($_ENV[$varName])
			|| isset($_SESSION[$varName])
			|| isset($_FILES[$varName])
		){
			die('Sorry. An error occurred.');
		}
	} else {
		if (   isset($HTTP_GET_VARS[$varName]) 
			|| isset($HTTP_POST_VARS[$varName]) 
			|| isset($HTTP_COOKIE_VARS[$varName])
			|| isset($HTTP_ENV_VARS[$varName])
			|| isset($HTTP_SESSION_VARS[$varName])
			|| isset($HTTP_POST_FILES[$varName])
		){
			die('Sorry. An error occurred.');
		}		
	}
}


include($DIR_LIBS . 'ADMIN.php');

class PluginAdmin {
	
	var $strFullName;		// NP_SomeThing
	var $plugin;			// ref. to plugin object
	var $bValid;			// evaluates to true when object is considered valid
	var $admin;				// ref to an admin object
	
	function PluginAdmin($pluginName)
	{
		global $manager;
		
		$this->strFullName = 'NP_' . $pluginName;
		
		// check if plugin exists and is installed
		if (!$manager->pluginInstalled($this->strFullName))
			doError('Invalid plugin');
		
		$this->plugin =& $manager->getPlugin($this->strFullName);
		$this->bValid = $this->plugin;
	
		if (!$this->bValid)
			doError('Invalid plugin');
		
		$this->admin = new ADMIN();
		$this->admin->action = 'plugin_' . $pluginName;
	}
	
	function start($extraHead = '')
	{
		global $CONF;
		$strBaseHref  = '<base href="' . htmlspecialchars($CONF['AdminURL']) . '" />';	
		$extraHead .= $strBaseHref;
		
		$this->admin->pagehead($extraHead);
	}
	
	function end()
	{
		$this->admin->pagefoot();
	}	
}



?>