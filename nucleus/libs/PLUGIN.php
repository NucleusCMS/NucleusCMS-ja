<?php
/*
 * Nucleus: PHP/MySQL Weblog CMS (http://nucleuscms.org/)
 * Copyright (C) 2002-2013 The Nucleus Group
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * (see nucleus/documentation/index.html#license for more info)
 *
 * This is an (abstract) class of which all Nucleus Plugins must inherit
 *
 * for more information on plugins and how to write your own, see the
 * plugins.html file that is included with the Nucleus documenation
 */
class NucleusPlugin
{
    // these functions _have_ to be redefined in your plugin

    public function getName()
    {
        return 'Undefined';
    }
    public function getAuthor()
    {
        return 'Undefined';
    }
    public function getURL()
    {
        return 'Undefined';
    }
    public function getVersion()
    {
        return '0.0';
    }
    public function getDescription()
    {
        return 'Undefined';
    }

    // these function _may_ be redefined in your plugin

    public function getMinNucleusVersion()
    {
        return 350;
    }
    public function getMinNucleusPatchLevel()
    {
        return 0;
    }
    public function getEventList()
    {
        return array();
    }
    public function getTableList()
    {
        return array();
    }
    public function hasAdminArea()
    {
        return 0;
    }

    public function install()
    {
    }
    public function unInstall()
    {
    }

    public function init()
    {
    }

    public function doSkinVar($skinType)
    {
    }
    public function doTemplateVar(&$item)
    {
        $args = func_get_args();
        array_shift($args);
        array_unshift($args, 'template');
        call_user_func_array(array($this,'doSkinVar'), $args);
    }
    public function doTemplateCommentsVar(&$item, &$comment)
    {
        $args = func_get_args();
        array_shift($args);
        array_shift($args);
        array_unshift($args, 'template');
        call_user_func_array(array($this,'doSkinVar'), $args);
    }
    public function doAction($type)
    {
        return _ERROR_PLUGIN_NOSUCHACTION;
    }
    public function doIf($key, $value)
    {
        return false;
    }
    public function doItemVar(&$item)
    {
    }

    /**
     * Checks if a plugin supports a certain feature.
     *
     * @returns 1 if the feature is reported, 0 if not
     * @param $feature
     *                  Name of the feature. See plugin documentation for more info
     *                  'SqlTablePrefix' -> if the plugin uses the sql_table() method to get table names
     *                  'HelpPage' -> if the plugin provides a helppage
     *                  'SqlApi' -> if the plugin uses the complete sql_* api (must also require nucleuscms 3.5)
     */
    public function supportsFeature($feature)
    {
        return 0;
    }

    /**
     * Report a list of plugin that is required to function
     *
     * @returns an array of names of plugin, an empty array indicates no dependency
     */
    public function getPluginDep()
    {
        return array();
    }

    // these helper functions should not be redefined in your plugin

    /**
      * Creates a new option for this plugin
      *
      * @param name
      *		A string uniquely identifying your option. (max. length is 20 characters)
      * @param description
      *		A description that will show up in the nucleus admin area (max. length: 255 characters)
      * @param type
      *		Either 'text', 'yesno' or 'password'
      *		This info is used when showing 'edit plugin options' screens
      * @param value
      *		Initial value for the option (max. value length is 128 characters)
      */
    public function createOption($name, $desc, $type, $defValue = '', $typeExtras = '')
    {
        return $this->_createOption('global', $name, $desc, $type, $defValue, $typeExtras);
    }
    public function createBlogOption($name, $desc, $type, $defValue = '', $typeExtras = '')
    {
        return $this->_createOption('blog', $name, $desc, $type, $defValue, $typeExtras);
    }
    public function createMemberOption($name, $desc, $type, $defValue = '', $typeExtras = '')
    {
        return $this->_createOption('member', $name, $desc, $type, $defValue, $typeExtras);
    }
    public function createCategoryOption($name, $desc, $type, $defValue = '', $typeExtras = '')
    {
        return $this->_createOption('category', $name, $desc, $type, $defValue, $typeExtras);
    }
    public function createItemOption($name, $desc, $type, $defValue = '', $typeExtras = '')
    {
        return $this->_createOption('item', $name, $desc, $type, $defValue, $typeExtras);
    }

    /**
      * Removes the option from the database
      *
      * Note: Options get erased automatically on plugin uninstall
      */
    public function deleteOption($name)
    {
        return $this->_deleteOption('global', $name);
    }
    public function deleteBlogOption($name)
    {
        return $this->_deleteOption('blog', $name);
    }
    public function deleteMemberOption($name)
    {
        return $this->_deleteOption('member', $name);
    }
    public function deleteCategoryOption($name)
    {
        return $this->_deleteOption('category', $name);
    }
    public function deleteItemOption($name)
    {
        return $this->_deleteOption('item', $name);
    }

    /**
      * Sets the value of an option to something new
      */
    public function setOption($name, $value)
    {
        return $this->_setOption('global', 0, $name, $value);
    }
    public function setBlogOption($blogid, $name, $value)
    {
        return $this->_setOption('blog', $blogid, $name, $value);
    }
    public function setMemberOption($memberid, $name, $value)
    {
        return $this->_setOption('member', $memberid, $name, $value);
    }
    public function setCategoryOption($catid, $name, $value)
    {
        return $this->_setOption('category', $catid, $name, $value);
    }
    public function setItemOption($itemid, $name, $value)
    {
        return $this->_setOption('item', $itemid, $name, $value);
    }

    /**
      * Retrieves the current value for an option
      */
    public function getOption($name)
    {
        // only request the options the very first time. On subsequent requests
        // the static collection is used to save SQL queries.
        if ($this->plugin_options == 0) {
            $this->plugin_options = array();
            // http://japan.nucleuscms.org/forum/viewtopic.php?id=6203
            $query = "SELECT d.oname as name, o.ovalue as value FROM %s d, %s o WHERE d.opid='%s' AND d.ocontext='global' AND d.oid=o.oid AND o.ocontextid=0";
            $query = sprintf($query, sql_table('plugin_option_desc'), sql_table('plugin_option'), intval($this->getID()));
            $rs    = sql_query($query);
            while ($row = sql_fetch_object($rs)) {
                $this->plugin_options[strtolower($row->name)] = $row->value;
            }
        }
        if (isset($this->plugin_options[strtolower($name)])) {
            return $this->plugin_options[strtolower($name)];
        } else {
            return $this->_getOption('global', 0, $name);
        }
    }

    public function getBlogOption($blogid, $name)
    {
        return $this->_getOption('blog', $blogid, $name);
    }
    public function getMemberOption($memberid, $name)
    {
        return $this->_getOption('member', $memberid, $name);
    }
    public function getCategoryOption($catid, $name)
    {
        return $this->_getOption('category', $catid, $name);
    }
    public function getItemOption($itemid, $name)
    {
        return $this->_getOption('item', $itemid, $name);
    }

    /**
     * Retrieves an associative array with the option value for each
     * context id
     */
    public function getAllBlogOptions($name)
    {
        return $this->_getAllOptions('blog', $name);
    }
    public function getAllMemberOptions($name)
    {
        return $this->_getAllOptions('member', $name);
    }
    public function getAllCategoryOptions($name)
    {
        return $this->_getAllOptions('category', $name);
    }
    public function getAllItemOptions($name)
    {
        return $this->_getAllOptions('item', $name);
    }

    /**
     * Retrieves an indexed array with the top (or bottom) of an option
     * (delegates to _getOptionTop())
     */
    public function getBlogOptionTop($name, $amount = 10, $sort = 'desc')
    {
        return $this->_getOptionTop('blog', $name, $amount, $sort);
    }
    public function getMemberOptionTop($name, $amount = 10, $sort = 'desc')
    {
        return $this->_getOptionTop('member', $name, $amount, $sort);
    }
    public function getCategoryOptionTop($name, $amount = 10, $sort = 'desc')
    {
        return $this->_getOptionTop('category', $name, $amount, $sort);
    }
    public function getItemOptionTop($name, $amount = 10, $sort = 'desc')
    {
        return $this->_getOptionTop('item', $name, $amount, $sort);
    }

    /**
      * Returns the plugin ID
      *
      * public
      */
    public function getID()
    {
        return $this->plugid;
    }

    /**
      * Returns the URL of the admin area for this plugin (in case there's
      * no such area, the returned information is invalid)
      *
      * public
      */
    public function getAdminURL()
    {
        global $CONF;
        return $CONF['PluginURL'] . $this->getShortName() . '/';
    }

    /**
      * Returns the directory where the admin directory is located and
      * where the plugin can maintain his extra files
      *
      * public
      */
    public function getDirectory()
    {
        global $DIR_PLUGINS;
        return $DIR_PLUGINS . $this->getShortName() . '/';
    }

    /**
      * Derives the short name for the plugin from the classname (all
      * lowercase)
      *
      * public
      */
    public function getShortName()
    {
        return str_replace('np_', '', strtolower(get_class($this)));
    }

    /**
     *	Clears the option value cache which saves the option values during
     *	the plugin execution. This function is usefull if the options has
     *	changed during the plugin execution (especially in association with
     *	the PrePluginOptionsUpdate and the PostPluginOptionsUpdate events)
     *
     *  public
     **/
    public function clearOptionValueCache()
    {
        $this->_aOptionValues = array();
        $this->plugin_options = 0;
    }

    // internal functions of the class starts here

    public $_aOptionValues;	// oid_contextid => value
    public $_aOptionToInfo;	// context_name => array('oid' => ..., 'default' => ...)
    public $plugin_options;	// see getOption()
    public $plugid;			// plugin id

    /**
     * Class constructor: Initializes some internal data
     */
    public function __construct()
    {
        $this->_aOptionValues = array();	// oid_contextid => value
        $this->_aOptionToInfo = array();	// context_name => array('oid' => ..., 'default' => ...)
        $this->plugin_options = 0;
    }

    /**
     * Retrieves an array of the top (or bottom) of an option from a plugin.
     * @author TeRanEX
     * @param  string $context the context for the option: item, blog, member,...
     * @param  string $name    the name of the option
     * @param  int    $amount  how many rows must be returned
     * @param  string $sort    desc or asc
     * @return array  array with both values and contextid's
     * @access private
     */
    public function _getOptionTop($context, $name, $amount = 10, $sort = 'desc')
    {
        if (($sort != 'desc') && ($sort != 'asc')) {
            $sort = 'desc';
        }

        $oid = $this->_getOID($context, $name);

        // retrieve the data and return
        $q     = 'SELECT otype, oextra FROM '.sql_table('plugin_option_desc').' WHERE oid = '.$oid;
        $query = sql_query($q);

        $o = sql_fetch_array($query);

        if (($this->optionCanBeNumeric($o['otype'])) && ($o['oextra'] == 'number')) {
            $orderby = 'CAST(ovalue AS SIGNED)';
        } else {
            $orderby = 'ovalue';
        }
        $q     = 'SELECT ovalue value, ocontextid id FROM '.sql_table('plugin_option').' WHERE oid = '.$oid.' ORDER BY '.$orderby.' '.$sort.' LIMIT 0,'.intval($amount);
        $query = sql_query($q);

        // create the array
        $i   = 0;
        $top = array();
        while ($row = sql_fetch_array($query)) {
            $top[$i++] = $row;
        }

        // return the array (duh!)
        return $top;
    }

    /**
     * Creates an option in the database table plugin_option_desc
     *
     * private
     */
    public function _createOption($context, $name, $desc, $type, $defValue, $typeExtras = '')
    {
        // create in plugin_option_desc
        $query = 'INSERT INTO ' . sql_table('plugin_option_desc')
               .' (opid, oname, ocontext, odesc, otype, odef, oextra)'
               .' VALUES ('.intval($this->plugid)
                         .', \''.sql_real_escape_string($name).'\''
                         .', \''.sql_real_escape_string($context).'\''
                         .', \''.sql_real_escape_string($desc).'\''
                         .', \''.sql_real_escape_string($type).'\''
                         .', \''.sql_real_escape_string($defValue).'\''
                         .', \''.sql_real_escape_string($typeExtras).'\')';
        sql_query($query);
        $oid = sql_insert_id();

        $key                        = $context . '_' . $name;
        $this->_aOptionToInfo[$key] = array('oid' => $oid, 'default' => $defValue);
        return 1;
    }

    /**
     * Deletes an option from the database tables
     * plugin_option and plugin_option_desc
     *
     * private
     */
    public function _deleteOption($context, $name)
    {
        $oid = $this->_getOID($context, $name);
        if (!$oid) {
            return 0;
        } // no such option

        // delete all things from plugin_option
        sql_query('DELETE FROM ' . sql_table('plugin_option') . ' WHERE oid=' . $oid);

        // delete entry from plugin_option_desc
        sql_query('DELETE FROM ' . sql_table('plugin_option_desc') . ' WHERE oid=' . $oid);

        // clear from cache
        unset($this->_aOptionToInfo[$context . '_' . $name]);
        $this->_aOptionValues = array();
        return 1;
    }

    /**
     * Update an option in the database table plugin_option
     *
     * returns: 1 on success, 0 on failure
     * private
     */
    public function _setOption($context, $contextid, $name, $value)
    {
        global $manager;

        $oid = $this->_getOID($context, $name);
        if (!$oid) {
            return 0;
        }

        // check if context id exists
        switch ($context) {
            case 'member':
                if (!MEMBER::existsID($contextid)) {
                    return 0;
                }
                break;
            case 'blog':
                if (!$manager->existsBlogID($contextid)) {
                    return 0;
                }
                break;
            case 'category':
                if (!$manager->existsCategory($contextid)) {
                    return 0;
                }
                break;
            case 'item':
                if (!$manager->existsItem($contextid, true, true)) {
                    return 0;
                }
                break;
            case 'global':
                if ($contextid != 0) {
                    return 0;
                }
                break;
        }

        // update plugin_option
        sql_query('DELETE FROM ' . sql_table('plugin_option') . ' WHERE oid='.intval($oid) . ' and ocontextid='. intval($contextid));
        sql_query('INSERT INTO ' . sql_table('plugin_option') . ' (ovalue, oid, ocontextid) VALUES (\''.sql_real_escape_string($value).'\', '. intval($oid) . ', ' . intval($contextid) . ')');

        // update cache
        $this->_aOptionValues[$oid . '_' . $contextid] = $value;

        return 1;
    }

    /**
     * Get an option from Cache or database
     * 	 - if not in the option Cache read it from the database
     *   - if not in the database write default values into the database
     *
     * private
     */
    public function _getOption($context, $contextid, $name)
    {
        $oid = $this->_getOID($context, $name);
        if (!$oid) {
            return '';
        }

        $key = $oid . '_' . $contextid;

        if (isset($this->_aOptionValues[$key])) {
            return $this->_aOptionValues[$key];
        }

        // get from DB
        $res = sql_query('SELECT ovalue FROM ' . sql_table('plugin_option') . ' WHERE oid='.intval($oid).' and ocontextid=' . intval($contextid));

        if (!$res || (sql_num_rows($res) == 0)) {
            $defVal                     = $this->_getDefVal($context, $name);
            $this->_aOptionValues[$key] = $defVal;

            // fill DB with default value
            $query = 'INSERT INTO ' . sql_table('plugin_option') . ' (oid,ocontextid,ovalue)'
                   .' VALUES ('.intval($oid).', '.intval($contextid).', \''.sql_real_escape_string($defVal).'\')';
            sql_query($query);
        } else {
            $o                          = sql_fetch_object($res);
            $this->_aOptionValues[$key] = $o->ovalue;
        }

        return $this->_aOptionValues[$key];
    }

    /**
     * Returns assoc array with all values for a given option
     * (one option per possible context id)
     *
     * private
     */
    public function _getAllOptions($context, $name)
    {
        $aOptions = array();

        global $resultCache;
        if (isset($resultCache["aOptions{$context}"])) {
            $aOptions = $resultCache["aOptions{$context}"];
        } else {
            if ($context === 'blog') {
                $query = 'SELECT bnumber as contextid FROM ' . sql_table('blog');
            } elseif ($context === 'category') {
                $query = 'SELECT catid as contextid FROM ' . sql_table('category');
            } elseif ($context === 'member') {
                $query = 'SELECT mnumber as contextid FROM ' . sql_table('member');
            } elseif ($context === 'item') {
                $query = 'SELECT inumber as contextid FROM ' . sql_table('item');
            } else {
                $query = '';
            }

            if ($query === '') {
                return false;
            }

            $oid = $this->_getOID($context, $name);
            if (!$oid) {
                return array();
            }
            $defVal = $this->_getDefVal($context, $name);

            $r = sql_query($query);
            if ($r) {
                while ($o = sql_fetch_object($r)) {
                    $aOptions[$o->contextid] = $defVal;
                }
            }

            $resultCache["aOptions{$context}"] = $aOptions;
            $query                             = 'SELECT ocontextid, ovalue FROM ' . sql_table('plugin_option') . ' WHERE oid=' . $oid;
            $res                               = sql_query('SELECT ocontextid, ovalue FROM ' . sql_table('plugin_option') . ' WHERE oid=' . $oid);
            while ($o = sql_fetch_object($res)) {
                $aOptions[$o->ocontextid] = $o->ovalue;
            }
            $resultCache["aOptions{$context}"] = $aOptions;
        }

        return $aOptions;
    }

    /**
     * Gets the 'option identifier' that corresponds to a given option name.
     * When this method is called for the first time, all the OIDs for the plugin
     * are loaded into memory, to avoid re-doing the same query all over.
     */
    public function _getOID($context, $name)
    {
        $key = $context . '_' . $name;
        if (array_key_exists($key, $this->_aOptionToInfo)) {
            $info = $this->_aOptionToInfo[$key];
            if (is_array($info)) {
                return $info['oid'];
            }
        }

        // load all OIDs for this plugin from the database
        $this->_aOptionToInfo = array();
        $query                = 'SELECT oid, oname, ocontext, odef FROM ' . sql_table('plugin_option_desc') . ' WHERE opid=' . intval($this->plugid);
        $res                  = sql_query($query);
        while ($o = sql_fetch_object($res)) {
            $k                        = $o->ocontext . '_' . $o->oname;
            $this->_aOptionToInfo[$k] = array('oid' => $o->oid, 'default' => $o->odef);
        }
        sql_free_result($res);

        if (array_key_exists($key, $this->_aOptionToInfo)) {
            return $this->_aOptionToInfo[$key]['oid'];
        } else {
            return null;
        }
    }

    public function _getDefVal($context, $name)
    {
        $key  = $context . '_' . $name;
        $info = $this->_aOptionToInfo[$key];
        if (is_array($info)) {
            return $info['default'];
        }
    }

    /**
     * Deletes all option values for a given context and contextid
     * (used when e.g. a blog, member or category is deleted)
     *
     * (static method)
     */
    public static function _deleteOptionValues($context, $contextid)
    {
        // delete all associated plugin options
        $aOIDs = array();
        // find ids
        $query = 'SELECT oid FROM '.sql_table('plugin_option_desc') . ' WHERE ocontext=\''.sql_real_escape_string($context).'\'';
        $res   = sql_query($query);
        while ($o = sql_fetch_object($res)) {
            array_push($aOIDs, $o->oid);
        }
        sql_free_result($res);
        // delete those options. go go go
        if (count($aOIDs) > 0) {
            $query = 'DELETE FROM ' . sql_table('plugin_option') . ' WHERE oid in ('.implode(',', $aOIDs).') and ocontextid=' . intval($contextid);
            sql_query($query);
        }
    }

    /**
     * splits the option's typeextra field (at ;'s) to split the meta collection
     * @param  string $typeExtra the value of the typeExtra field of an option
     * @return array  array of the meta-key/value-pairs
     * @author TeRanEX
     * @static
     */
    public static function getOptionMeta($typeExtra)
    {
        $tmpMeta = explode(';', $typeExtra);
        $meta    = array();
        for ($i = 0; $i < count($tmpMeta); $i++) {
            if (($i == 0) && (!strstr($tmpMeta[0], '='))) {
                // we have the select-list
                $meta['select'] = $tmpMeta[0];
            } else {
                $tmp           = explode('=', $tmpMeta[$i]);
                $meta[$tmp[0]] = $tmp[1];
            }
        }
        return $meta;
    }

    /**
     * filters the selectlists out of the meta collection
     * @param  string $typeExtra the value of the typeExtra field of an option
     * @return string the selectlist
     * @author TeRanEX
     */
    public static function getOptionSelectValues($typeExtra)
    {
        $meta = NucleusPlugin::getOptionMeta($typeExtra);
        //the select list must always be the first part
        return $meta['select'];
    }

    /**
     * checks if the eventlist in the database is up-to-date
     * @return bool if it is up-to-date it return true, else false
     * @author TeRanEX
     */
    public function subscribtionListIsUptodate()
    {
        $res = sql_query('SELECT event FROM '.sql_table('plugin_event').' WHERE pid = '.$this->getID());
        $ev  = array();
        while ($a = sql_fetch_array($res)) {
            array_push($ev, $a['event']);
        }
        if (count($ev) != count($this->getEventList())) {
            return false;
        }
        $d = array_diff($ev, $this->getEventList());
        if (count($d) > 0) {
            // there are differences so the db is not up-to-date
            return false;
        }
        return true;
    }

    /**
     * @param $aOptions:     array ( 'oid' => array( 'contextid' => 'value'))
     *                        (taken from request using requestVar())
     * @param $newContextid: integer (accepts a contextid when it is for a new
     *                        contextid there was no id available at the moment of writing the
     *                        formcontrols into the page (by ex: itemOptions for new item)
     * @static
     */
    public static function _applyPluginOptions(&$aOptions, $newContextid = 0)
    {
        global $manager;
        if (!is_array($aOptions)) {
            return;
        }

        foreach ($aOptions as $oid => $values) {
            // get option type info
            $query = 'SELECT opid, oname, ocontext, otype, oextra, odef FROM ' . sql_table('plugin_option_desc') . ' WHERE oid=' . intval($oid);
            $res   = sql_query($query);
            if ($o = sql_fetch_object($res)) {
                foreach ($values as $key => $value) {
                    // avoid overriding the key used by foreach statement
                    $contextid = $key;

                    // retreive any metadata
                    $meta = NucleusPlugin::getOptionMeta($o->oextra);

                    // if the option is readonly or hidden it may not be saved
                    if (!array_key_exists('access', $meta) || (($meta['access'] != 'readonly') && ($meta['access'] != 'hidden'))) {
                        $value = undoMagic($value);	// value comes from request

                        switch($o->otype) {
                            case 'yesno':
                                if (($value != 'yes') && ($value != 'no')) {
                                    $value = 'no';
                                }
                                break;
                            default:
                                break;
                        }

                        // check the validity of numerical options
                        if (array_key_exists('datatype', $meta) && ($meta['datatype'] == 'numerical') && (!is_numeric($value))) {
                            //the option must be numeric, but the it isn't
                            //use the default for this option
                            $value = $o->odef;
                        }

                        // decide wether we are using the contextid of newContextid
                        if ($newContextid != 0) {
                            $contextid = $newContextid;
                        }

                        //trigger event PrePluginOptionsUpdate to give the plugin the
                        //possibility to change/validate the new value for the option
                        $param = array(
                            'context'    => $o->ocontext,
                            'plugid'     => $o->opid,
                            'optionname' => $o->oname,
                            'contextid'  => $contextid,
                            'value'      => &$value
                        );
                        $manager->notify('PrePluginOptionsUpdate', $param);

                        // delete the old value for the option
                        sql_query('DELETE FROM '.sql_table('plugin_option').' WHERE oid='.intval($oid).' AND ocontextid='.intval($contextid));
                        sql_query('INSERT INTO '.sql_table('plugin_option')." (oid, ocontextid, ovalue) VALUES (".intval($oid).",".intval($contextid).",'" . sql_real_escape_string($value) . "')");
                    }
                }
            }
            // clear option value cache if the plugin object is already loaded
            if (is_object($o)) {
                $plugin = & $manager->pidLoaded($o->opid);
                if ($plugin) {
                    $plugin->clearOptionValueCache();
                }
            }
        }
    }
}
