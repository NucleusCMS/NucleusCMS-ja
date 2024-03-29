<?php
/*
 * Nucleus: PHP/MySQL Weblog CMS (http://nucleuscms.org/)
 * Copyright (C) The Nucleus Group
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * (see nucleus/documentation/index.html#license for more info)
 *
 * The allowed tags for a type of skinpart are defined by the
 * SKIN::getAllowedActionsForType($type) method
 *
 * @license http://nucleuscms.org/license.txt GNU General Public License
 * @copyright Copyright (C) The Nucleus Group
 */

require_once __DIR__ . '/BaseActions.php'; // PHP Fatal error:  Class 'BaseActions' not found

class ACTIONS extends BaseActions
{
    // part of the skin currently being parsed ('index', 'item', 'archive',
    // 'archivelist', 'member', 'search', 'error', 'imagepopup')
    public $skintype;

    // contains an assoc array with parameters that need to be included when
    // generating links to items/archives/... (e.g. catid)
    public $linkparams;

    // reference to the skin object for which a part is being parsed
    public $skin;

    // used when including templated forms from the include/ dir. The $formdata var
    // contains the values to fill out in there (assoc array name -> value)
    public $formdata;

    // filled out with the number of displayed items after calling one of the
    // (other)blog/(other)searchresults skinvars.
    public $amountfound;

    /**
     * Constructor for a new ACTIONS object
     */
    public function __construct($type)
    {
        // call constructor of superclass first
        parent::__construct();

        $this->skintype = $type;

        global $catid;
        if ($catid) {
            $this->linkparams = array('catid' => $catid);
        }
    }

    /**
     *  Set the skin
     */
    public function setSkin(&$skin)
    {
        unset($this->skin);
        $this->skin = & $skin;
    }

    /**
     *  Set the parser
     */
    public function setParser(&$parser)
    {
        unset($this->parser);
        $this->parser = & $parser;
    }

    /**
     *    Forms get parsedincluded now, using an extra <formdata> skinvar
     */
    public function doForm($filename)
    {
        global $DIR_NUCLEUS;
        array_push($this->parser->actions, 'formdata', 'text', 'callback', 'errordiv', 'ticket');
        $oldIncludeMode   = PARSER::getProperty('IncludeMode');
        $oldIncludePrefix = PARSER::getProperty('IncludePrefix');
        PARSER::setProperty('IncludeMode', 'normal');
        PARSER::setProperty('IncludePrefix', '');
        $this->parse_parsedinclude($DIR_NUCLEUS . 'forms/' . $filename . '.template');
        PARSER::setProperty('IncludeMode', $oldIncludeMode);
        PARSER::setProperty('IncludePrefix', $oldIncludePrefix);
        array_pop($this->parser->actions);        // errordiv
        array_pop($this->parser->actions);        // callback
        array_pop($this->parser->actions);        // text
        array_pop($this->parser->actions);        // formdata
        array_pop($this->parser->actions);        // ticket
    }

    /**
     * Checks conditions for if statements
     *
     * @param string $field type of <%if%>
     * @param string $name  property of field
     * @param string $value value of property
     */
    public function checkCondition($field, $name = '', $value = '')
    {
        global $catid, $blog, $member, $itemidnext, $itemidprev, $manager, $archiveprevexists, $archivenextexists;

        $condition = 0;
        switch ($field) {
            case 'category':
                $condition = ($blog && $this->_ifCategory($name, $value));
                break;
            case 'blogsetting':
                $condition = ($blog && ($blog->getSetting($name) == $value));
                break;
            case 'loggedin':
                $condition = $member->isLoggedIn();
                break;
            case 'onteam':
                $condition = $member->isLoggedIn() && $this->_ifOnTeam($name);
                break;
            case 'admin':
                $condition = $member->isLoggedIn() && $this->_ifAdmin($name);
                break;
            case 'nextitem':
                $condition = ($itemidnext != '');
                break;
            case 'previtem':
                $condition = ($itemidprev != '');
                break;
            case 'archiveprevexists':
                $condition = ($archiveprevexists == true);
                break;
            case 'archivenextexists':
                $condition = ($archivenextexists == true);
                break;
            case 'skintype':
                $condition = ($name == $this->skintype);
                break;
            case 'hasplugin':
                $condition = $this->_ifHasPlugin($name, $value);
                break;
            case 'commentclosed':
                $condition = $this->parse_commentclosed();
                break;
            case 'hascomment':
                $condition = $this->parse_hascomment();
                break;
            case 'authorvisible':
                $condition = ($blog && $blog->getAuthorVisible());
                break;
            default:
                $condition = $manager->pluginInstalled('NP_' . $field) && $this->_ifPlugin($field, $name, $value);
                break;
        }
        return $condition;
    }

    /**
     *    hasplugin,PlugName
     *       -> checks if plugin exists
     *    hasplugin,PlugName,OptionName
     *       -> checks if the option OptionName from plugin PlugName is not set to 'no'
     *    hasplugin,PlugName,OptionName=value
     *       -> checks if the option OptionName from plugin PlugName is set to value
     */
    public function _ifHasPlugin($name, $value)
    {
        global $manager;
        $condition = false;
        // (pluginInstalled method won't write a message in the actionlog on failure)
        if ($manager->pluginInstalled('NP_' . $name)) {
            $plugin = & $manager->getPlugin('NP_' . $name);
            if ($plugin != null) {
                if ($value == "") {
                    $condition = true;
                } else {
                    list($name2, $value2) = explode('=', $value, 2);
                    if ($value2 == "" && $plugin->getOption($name2) != 'no') {
                        $condition = true;
                    } else {
                        if ($plugin->getOption($name2) == $value2) {
                            $condition = true;
                        }
                    }
                }
            }
        }
        return $condition;
    }

    /**
     * Checks if a plugin exists and call its doIf function
     */
    public function _ifPlugin($name, $key = '', $value = '')
    {
        global $manager;

        $plugin = & $manager->getPlugin('NP_' . $name);
        if (!$plugin) {
            return;
        }

        $params = func_get_args();
        array_shift($params);

        return call_user_func_array(array($plugin, 'doIf'), $params);
    }

    /**
     *  Different checks for a category
     */
    public function _ifCategory($name = '', $value = '')
    {
        global $blog, $catid;

        // when no parameter is defined, just check if a category is selected
        if (($name != 'catname' && $name != 'catid') || ($value == '')) {
            return $blog->isValidCategory($catid);
        }

        // check category name
        if ($name == 'catname') {
            $value = $blog->getCategoryIdFromName($value);
            if ($value == $catid) {
                return $blog->isValidCategory($catid);
            }
        }

        // check category id
        if (($name == 'catid') && ($value == $catid)) {
            return $blog->isValidCategory($catid);
        }

        return false;
    }

    /**
     *  Checks if a member is on the team of a blog and return his rights
     */
    public function _ifOnTeam($blogName = '')
    {
        global $blog, $member, $manager;

        // when no blog found
        if (($blogName == '') && (!is_object($blog))) {
            return 0;
        }

        // explicit blog selection
        if ($blogName != '') {
            $blogid = getBlogIDFromName($blogName);
        }

        if (($blogName == '') || !$manager->existsBlogID($blogid)) { // use current blog
            $blogid = $blog->getID();
        }

        return $member->teamRights($blogid);
    }

    /**
     *  Checks if a member is admin of a blog
     */
    public function _ifAdmin($blogName = '')
    {
        global $blog, $member, $manager;

        // when no blog found
        if (($blogName == '') && (!is_object($blog))) {
            return 0;
        }

        // explicit blog selection
        if ($blogName != '') {
            $blogid = getBlogIDFromName($blogName);
        }

        if (($blogName == '') || !$manager->existsBlogID($blogid)) { // use current blog
            $blogid = $blog->getID();
        }

        return $member->blogAdminRights($blogid);
    }

    /**
     * returns either
     *        - a raw link (html/xml encoded) when no linktext is provided
     *        - a (x)html <a href... link when a text is present (text htmlencoded)
     */
    public function _link($url, $linktext = '')
    {
        $u = hsc($url);
        $u = preg_replace("/&amp;amp;/", '&amp;', $u); // fix URLs that already had encoded ampersands
        if ($linktext != '') {
            $l = '<a href="' . $u . '">' . hsc($linktext) . '</a>';
        } else {
            $l = $u;
        }
        return $l;
    }

    /**
     * Outputs a next/prev link
     *
     * @param $maxresults
     *        The maximum amount of items shown per page (e.g. 10)
     * @param $startpos
     *        Current start position (requestVar('startpos'))
     * @param $direction
     *        either 'prev' or 'next'
     * @param $linktext
     *        When present, the output will be a full <a href...> link. When empty,
     *        only a raw link will be outputted
     */
    public function _searchlink($maxresults, $startpos, $direction, $linktext = '', $recount = '')
    {
        global $CONF, $blog, $query, $amount;
        // TODO: Move request uri to linkparams. this is ugly. sorry for that.
        $startpos = intval($startpos);        // will be 0 when empty.
        $parsed   = parse_url(serverVar('REQUEST_URI'));
        $path     = (isset($parsed['path']) ? $parsed['path'] : '');
        $parsed   = $parsed['query'];
        $url      = '';

        switch ($direction) {
            case 'prev':
                if (intval($startpos) - intval($maxresults) >= 0) {
                    $startpos = intval($startpos) - intval($maxresults);
                    //$url        = $CONF['SearchURL'].'?'.alterQueryStr($parsed,'startpos',$startpos);
                    switch ($this->skintype) {
                        case 'index':
                            $url = $path;
                            break;
                        case 'search':
                            $url = $CONF['SearchURL'];
                            break;
                    }
                    $url .= '?' . alterQueryStr($parsed, 'startpos', $startpos);
                }
                break;
            case 'next':
                global $navigationItems;
                if (!isset($navigationItems)) {
                    $navigationItems = 0;
                }

                if ($recount) {
                    $iAmountOnPage = 0;
                } else {
                    $iAmountOnPage = $this->amountfound;
                }

                if (intval($navigationItems) > 0) {
                    $iAmountOnPage = intval($navigationItems) - intval($startpos);
                } elseif ($iAmountOnPage == 0) {
                    // [%nextlink%] or [%prevlink%] probably called before [%blog%] or [%searchresults%]
                    // try a count query
                    switch ($this->skintype) {
                        case 'index':
                            $sqlquery = $blog->getSqlBlog('', 'count');
                            $url      = $path;
                            break;
                        case 'search':
                            $unused_highlight = '';
                            $sqlquery         = $blog->getSqlSearch($query, $amount, $unused_highlight, 'count');
                            $url              = $CONF['SearchURL'];
                            break;
                    }
                    if ($sqlquery) {
                        $iAmountOnPage = intval(quickQuery($sqlquery)) - intval($startpos);
                    }
                }
                if (intval($iAmountOnPage) >= intval($maxresults)) {
                    $startpos = intval($startpos) + intval($maxresults);
                    //$url        = $CONF['SearchURL'].'?'.alterQueryStr($parsed,'startpos',$startpos);
                    $url .= '?' . alterQueryStr($parsed, 'startpos', $startpos);
                } else {
                    $url = '';
                }
                break;
            default:
                break;
        } // switch($direction)

        if ($url != '') {
            echo $this->_link($url, $linktext);
        }
    }

    /**
     *  Creates an item link and if no id is given a todaylink
     */
    public function _itemlink($id, $linktext = '')
    {
        global $CONF;
        if ($id) {
            echo $this->_link(createItemLink($id, $this->linkparams), $linktext);
        } else {
            $this->parse_todaylink($linktext);
        }
    }

    /**
     *  Creates an archive link and if no id is given a todaylink
     */
    public function _archivelink($id, $linktext = '')
    {
        global $CONF, $blog;
        if ($id) {
            echo $this->_link(createArchiveLink($blog->getID(), $id, $this->linkparams), $linktext);
        } else {
            $this->parse_todaylink($linktext);
        }
    }

    /**
     * Helper function that sets the category that a blog will need to use
     *
     * @param $blog
     *        An object of the blog class, passed by reference (we want to make changes to it)
     * @param $catname
     *        The name of the category to use
     */
    public function _setBlogCategory($blog, $catname)
    {
        global $catid;
        if ($catname != '') {
            $blog->setSelectedCategoryByName($catname);
        } else {
            $blog->setSelectedCategory($catid);
        }
    }

    /**
     *  Notifies the Manager that a PreBlogContent event occurs
     */
    public function _preBlogContent($type, &$blog)
    {
        global $manager;
        $param = array(
            'blog' => &$blog,
            'type' => $type
        );
        $manager->notify('PreBlogContent', $param);
    }

    /**
     *  Notifies the Manager that a PostBlogContent event occurs
     */
    public function _postBlogContent($type, &$blog)
    {
        global $manager;
        $param = array(
            'blog' => &$blog,
            'type' => $type
        );
        $manager->notify('PostBlogContent', $param);
    }

    /**
     * Parse skinvar additemform
     */
    public function parse_additemform()
    {
        global $blog, $CONF;
        $this->formdata = array(
            'adminurl' => hsc($CONF['AdminURL']),
            'catid'    => $blog->getDefaultCategory()
        );
        $blog->InsertJavaScriptInfo();
        $this->doForm('additemform');
    }

    /**
     * Parse skinvar addlink
     * A Link that allows to open a bookmarklet to add an item
     */
    public function parse_addlink()
    {
        global $CONF, $member, $blog;
        if (isset($blog) && is_object($blog)) {
            if ($member->isLoggedIn() && $member->isTeamMember($blog->blogid)) {
                echo $CONF['AdminURL'] . 'bookmarklet.php?blogid=' . $blog->blogid;
            }
        }
    }

    /**
     * Parse skinvar addpopupcode
     * Code that opens a bookmarklet in an popup window
     */
    public function parse_addpopupcode()
    {
        echo "if (event &amp;&amp; event.preventDefault) event.preventDefault();";
        echo "winbm=window.open(this.href,'nucleusbm','scrollbars=yes,width='+window.parent.screen.width*0.9+',height='+window.parent.screen.height*0.9+',left=10,top=10,status=yes,resizable=yes');";
        echo "winbm.focus();return false;";
    }

    /**
     * Parse skinvar adminurl
     * (shortcut for admin url)
     */
    public function parse_adminurl()
    {
        $this->parse_sitevar('adminurl');
    }

    /**
     * Parse skinvar archive
     */
    public function parse_archive($template, $category = '')
    {
        global $blog, $archive;
        $y = $m = $d = 0;
        // can be used with either yyyy-mm or yyyy-mm-dd
        sscanf($archive, '%d-%d-%d', $y, $m, $d);
        $this->_setBlogCategory($blog, $category);
        $this->_preBlogContent('achive', $blog);
        $blog->showArchive($template, $y, $m, $d);
        $this->_postBlogContent('achive', $blog);
    }

    /**
     * %archivedate(locale,date format)%
     */
    public function parse_archivedate($locale = '-def-')
    {
        global $archive;

        // get format
        $args = func_get_args();

        // FIXME: check valid locale name
        //       PHP7.0RC7 (win) hangup when invalid strings
        $pattern = '@^[0-9a-z\._\-]{2,}$@i';
        if ($locale == '-def-') {
            // FIXME: can not determin default LOCALE
            global $manager, $currentTemplateName;
            if (isset($currentTemplateName) && TEMPLATE::exists($currentTemplateName)) {
                $template = & $manager->getTemplate($currentTemplateName);
                if (isset($template['LOCALE']) && preg_match($pattern, $template['LOCALE'])) {
                    setlocale(LC_TIME, $template['LOCALE']);
                }
            }
        } else {
            $locale = @trim($locale);
            if ($locale && preg_match($pattern, $locale)) {
                setlocale(LC_TIME, $locale);
            } else {
                if (func_num_args() == 1 && strlen($locale) > 0) {
                    array_unshift($args, '');
                }
            } // move to date format
        }

        // get archive date
        $y = $m = $d = 0;
        sscanf($archive, '%d-%d-%d', $y, $m, $d);

        // format can be spread over multiple parameters
        if (count($args) > 1) {
            // take away locale
            array_shift($args);
            // implode
            $format = implode(',', $args);
        } elseif ($d == 0 && $m != 0) {
            $format = (!defined('_DEFAULT_DATE_FORMAT_YB') ? '%B %Y' : _DEFAULT_DATE_FORMAT_YB);
        } elseif ($m == 0) {
            $format = (!defined('_DEFAULT_DATE_FORMAT_Y') ? '%Y' : _DEFAULT_DATE_FORMAT_Y);
        } else {
            $format = (!defined('_DEFAULT_DATE_FORMAT_YBD') ? '%d %B %Y' : _DEFAULT_DATE_FORMAT_YBD);
        }

        echo Utils::strftime($format, mktime(0, 0, 0, $m ? $m : 1, $d ? $d : 1, $y));
    }

    /**
     *  Parse skinvar archivedaylist
     */
    public function parse_archivedaylist($template, $category = 'all', $limit = 0)
    {
        global $blog;
        if ($category == 'all') {
            $category = '';
        }
        $this->_preBlogContent('archivelist', $blog);
        $this->_setBlogCategory($blog, $category);
        $blog->showArchiveList($template, 'day', $limit);
        $this->_postBlogContent('archivelist', $blog);
    }

    /**
     *    A link to the archives for the current blog (or for default blog)
     */
    public function parse_archivelink($linktext = '')
    {
        global $blog, $CONF;
        if ($blog) {
            echo $this->_link(createArchiveListLink($blog->getID(), $this->linkparams), $linktext);
        } else {
            echo $this->_link(createArchiveListLink(), $linktext);
        }
    }

    public function parse_archivelist($template, $category = 'all', $limit = 0)
    {
        global $blog;
        if ($category == 'all') {
            $category = '';
        }
        $this->_preBlogContent('archivelist', $blog);
        $this->_setBlogCategory($blog, $category);
        $blog->showArchiveList($template, 'month', $limit);
        $this->_postBlogContent('archivelist', $blog);
    }

    public function parse_archiveyearlist($template, $category = 'all', $limit = 0)
    {
        global $blog;
        if ($category == 'all') {
            $category = '';
        }
        $this->_preBlogContent('archivelist', $blog);
        $this->_setBlogCategory($blog, $category);
        $blog->showArchiveList($template, 'year', $limit);
        $this->_postBlogContent('archivelist', $blog);
    }

    /**
     * Parse skinvar archivetype
     */
    public function parse_archivetype()
    {
        global $archivetype;
        echo $archivetype;
    }

    /**
     * Parse skinvar blog
     */
    public function parse_blog($template, $amount = 10, $category = '')
    {
        global $blog, $startpos;

        list($limit, $offset) = sscanf($amount, '%d(%d)');
        $this->_setBlogCategory($blog, $category);
        $this->_preBlogContent('blog', $blog);
        $this->amountfound = $blog->readLog($template, $limit, $offset, $startpos);
        $this->_postBlogContent('blog', $blog);
    }

    /*
    *    Parse skinvar bloglist
    *    Shows a list of all blogs
    *    bnametype: whether 'name' or 'shortname' is used for the link text
    *    orderby: order criteria
    *    direction: order ascending or descending
    */
    public function parse_bloglist($template, $bnametype = '', $orderby = 'number', $direction = 'asc')
    {
        BLOG::showBlogList($template, $bnametype, $orderby, $direction);
    }

    /**
     * Parse skinvar blogsetting
     */
    public function parse_blogsetting($which)
    {
        global $blog;
        switch ($which) {
            case 'id':
                echo hsc($blog->getID());
                break;
            case 'url':
                echo hsc($blog->getRealURL());
                break;
            case 'name':
                echo hsc($blog->getName());
                break;
            case 'desc':
                echo hsc($blog->getDescription());
                break;
            case 'short':
                echo hsc($blog->getShortName());
                break;
        }
    }

    /**
     * Parse callback
     */
    public function parse_callback($eventName, $type)
    {
        global $manager;
        $param = array('type' => $type);
        $manager->notify($eventName, $param);
    }

    /**
     * Parse skinvar category
     */
    public function parse_category($type = 'name')
    {
        global $catid, $blog;
        if (!$blog->isValidCategory($catid)) {
            return;
        }

        switch ($type) {
            case 'name':
                echo $blog->getCategoryName($catid);
                break;
            case 'desc':
                echo $blog->getCategoryDesc($catid);
                break;
            case 'id':
                echo $catid;
                break;
        }
    }

    /**
     * Parse categorylist
     */
    public function parse_categorylist($template, $blogname = '')
    {
        global $blog, $manager;

        // when no blog found
        if (($blogname == '') && (!is_object($blog))) {
            return 0;
        }

        if ($blogname == '') {
            $this->_preBlogContent('categorylist', $blog);
            $blog->showCategoryList($template);
            $this->_postBlogContent('categorylist', $blog);
        } else {
            $b = & $manager->getBlog(getBlogIDFromName($blogname));
            $this->_preBlogContent('categorylist', $b);
            $b->showCategoryList($template);
            $this->_postBlogContent('categorylist', $b);
        }
    }

    /**
     * Parse skinvar charset
     */
    public function parse_charset()
    {
        echo _CHARSET;
    }

    /**
     * Parse skinvar commentform
     */
    public function parse_commentform($destinationurl = '')
    {
        global $blog, $itemid, $member, $CONF, $manager, $DIR_LIBS, $errormessage;

        // warn when trying to provide a actionurl (used to be a parameter in Nucleus <2.0)
        if (stristr($destinationurl, 'action.php')) {
            $args           = func_get_args();
            $destinationurl = $args[1];
            ACTIONLOG::add(WARNING, _ACTIONURL_NOTLONGER_PARAMATER);
        }

        $actionurl = $CONF['ActionURL'];

        // if item is closed, show message and do nothing
        $item = & $manager->getItem($itemid, 0, 0);
        if ($item['closed'] || !$blog->commentsEnabled()) {
            $this->doForm('commentform-closed');
            return;
        }

        if (!$blog->isPublic() && !$member->isLoggedIn()) {
            $this->doForm('commentform-closedtopublic');
            return;
        }

        if (!$destinationurl) {
            $destinationurl = createLink(
                'item',
                array(
                    'itemid'    => $itemid,
                    'title'     => $item['title'],
                    'timestamp' => $item['timestamp'],
                    'extra'     => $this->linkparams
                )
            );

        // note: createLink returns an HTML encoded URL
        } else {
            // HTML encode URL
            $destinationurl = hsc($destinationurl);
        }

        // values to prefill
        $user = cookieVar($CONF['CookiePrefix'] . 'comment_user');
        if (!$user) {
            $user = postVar('user');
        }
        $userid = cookieVar($CONF['CookiePrefix'] . 'comment_userid');
        if (!$userid) {
            $userid = postVar('userid');
        }
        $email = cookieVar($CONF['CookiePrefix'] . 'comment_email');
        if (!$email) {
            $email = postVar('email');
        }
        $body = postVar('body');

        $this->formdata = array(
            'destinationurl'  => $destinationurl,    // url is already HTML encoded
            'actionurl'       => hsc($actionurl),
            'itemid'          => $itemid,
            'user'            => hsc($user),
            'userid'          => hsc($userid),
            'email'           => hsc($email),
            'body'            => hsc($body),
            'membername'      => $member->getDisplayName(),
            'rememberchecked' => cookieVar($CONF['CookiePrefix'] . 'comment_user') ? 'checked="checked"' : ''
        );

        if (!$member->isLoggedIn()) {
            $this->doForm('commentform-notloggedin');
        } else {
            $this->doForm('commentform-loggedin');
        }
    }

    /**
     * Parse skinvar comments
     * include comments for one item
     */
    public function parse_comments($template)
    {
        global $itemid, $manager, $blog, $highlight;
        $template = & $manager->getTemplate($template);

        // create parser object & action handler
        $actions = new ITEMACTIONS($blog);
        $parser  = new PARSER($actions->getDefinedActions(), $actions);
        $actions->setTemplate($template);
        $actions->setParser($parser);
        $item = ITEM::getitem($itemid, 0, 0);
        $actions->setCurrentItem($item);

        $comments = new COMMENTS($itemid);
        $comments->setItemActions($actions);
        $comments->showComments($template, -1, 1, $highlight);    // shows ALL comments
    }

    /**
     * Parse errordiv
     */
    public function parse_errordiv()
    {
        global $errormessage;
        if ($errormessage) {
            echo '<div class="error">', hsc($errormessage), '</div>';
        }
    }

    /**
     * Parse skinvar errormessage
     */
    public function parse_errormessage()
    {
        global $errormessage;
        echo $errormessage;
    }

    /**
     * Parse formdata
     */
    public function parse_formdata($what)
    {
        echo $this->formdata[$what];
    }

    /**
     * Parse ifcat
     */
    public function parse_ifcat($text = '')
    {
        if ($text == '') {
            // new behaviour
            $this->parse_if('category');
        } else {
            // old behaviour
            global $catid, $blog;
            if ($blog->isValidCategory($catid)) {
                echo $text;
            }
        }
    }

    /**
     * Parse skinvar image
     */
    public function parse_image($what = 'imgtag')
    {
        global $CONF;

        $imagetext  = hsc(requestVar('imagetext'));
        $imagepopup = requestVar('imagepopup');
        $width      = intRequestVar('width');
        $height     = intRequestVar('height');
        $fullurl    = hsc($CONF['MediaURL'] . $imagepopup);

        switch ($what) {
            case 'url':
                echo $fullurl;
                break;
            case 'width':
                echo $width;
                break;
            case 'height':
                echo $height;
                break;
            case 'caption':
            case 'text':
                echo $imagetext;
                break;
            case 'imgtag':
            default:
                echo "<img src=\"{$fullurl}\" width=\"{$width}\" height=\"{$height}\" alt=\"{$imagetext}\" title=\"{$imagetext}\" />";
                break;
        }
    }

    /**
     * Parse skinvar imagetext
     */
    public function parse_imagetext()
    {
        echo hsc(requestVar('imagetext'));
    }

    /**
     * Parse skinvar item
     * include one item (no comments)
     */
    public function parse_item($template)
    {
        global $blog, $itemid, $highlight;
        $this->_setBlogCategory($blog, '');    // need this to select default category
        $this->_preBlogContent('item', $blog);
        $r = $blog->showOneitem($itemid, $template, $highlight);
        if ($r == 0) {
            echo _ERROR_NOSUCHITEM;
        }
        $this->_postBlogContent('item', $blog);
    }

    /**
     * Parse skinvar itemid
     */
    public function parse_itemid()
    {
        global $itemid;
        echo $itemid;
    }

    /**
     * Parse skinvar itemlink
     */
    public function parse_itemlink($linktext = '')
    {
        global $itemid;
        $this->_itemlink($itemid, $linktext);
    }

    /**
     * Parse itemtitle
     */
    public function parse_itemtitle($format = '')
    {
        global $manager, $itemid;
        $item = & $manager->getItem($itemid, 0, 0);

        switch ($format) {
            case 'xml':
                echo stringToXML($item['title']);
                break;
            case 'attribute':
                echo stringToAttribute($item['title']);
                break;
            case 'raw':
                echo $item['title'];
                break;
            default:
                echo hsc(strip_tags($item['title']));
                break;
        }
    }

    /**
     * Parse skinvar loginform
     */
    public function parse_loginform()
    {
        global $member, $CONF;
        if (!$member->isLoggedIn()) {
            $filename       = 'loginform-notloggedin';
            $this->formdata = array();
        } else {
            $filename       = 'loginform-loggedin';
            $this->formdata = array(
                'membername' => $member->getDisplayName(),
            );
        }
        $this->doForm($filename);
    }

    /**
     * Parse skinvar member
     * (includes a member info thingie)
     */
    public function parse_member($what)
    {
        global $memberinfo, $member, $CONF;

        // 1. only allow the member-details-page specific variables on member pages
        if ($this->skintype == 'member') {
            switch ($what) {
                case 'name':
                    echo hsc($memberinfo->getDisplayName());
                    break;
                case 'realname':
                    echo hsc($memberinfo->getRealName());
                    break;
                case 'notes':
                    echo hsc($memberinfo->getNotes());
                    break;
                case 'url':
                    echo hsc($memberinfo->getURL());
                    break;
                case 'email':
                    echo hsc($memberinfo->getEmail());
                    break;
                case 'id':
                    echo hsc($memberinfo->getID());
                    break;
            }
        }

        // 2. the next bunch of options is available everywhere, as long as the user is logged in
        if ($member->isLoggedIn()) {
            switch ($what) {
                case 'yourname':
                    echo $member->getDisplayName();
                    break;
                case 'yourrealname':
                    echo $member->getRealName();
                    break;
                case 'yournotes':
                    echo $member->getNotes();
                    break;
                case 'yoururl':
                    echo $member->getURL();
                    break;
                case 'youremail':
                    echo $member->getEmail();
                    break;
                case 'yourid':
                    echo $member->getID();
                    break;
                case 'yourprofileurl':
                    if ($CONF['URLMode'] == 'pathinfo') {
                        echo createMemberLink($member->getID());
                    } else {
                        echo $CONF['IndexURL'] . createMemberLink($member->getID());
                    }
                    break;
            }
        }
    }

    /**
     * Parse skinvar membermailform
     */
    public function parse_membermailform($rows = 10, $cols = 40, $desturl = '')
    {
        global $member, $CONF, $memberid;

        if ($desturl == '') {
            if ($CONF['URLMode'] == 'pathinfo') {
                $desturl = createMemberLink($memberid);
            } else {
                $desturl = $CONF['IndexURL'] . createMemberLink($memberid);
            }
        }

        $message  = postVar('message');
        $frommail = postVar('frommail');

        $this->formdata = array(
            'url'       => hsc($desturl),
            'actionurl' => hsc($CONF['ActionURL']),
            'memberid'  => $memberid,
            'rows'      => $rows,
            'cols'      => $cols,
            'message'   => hsc($message),
            'frommail'  => hsc($frommail)
        );
        if ($member->isLoggedIn()) {
            $this->doForm('membermailform-loggedin');
        } else {
            if ($CONF['NonmemberMail']) {
                $this->doForm('membermailform-notloggedin');
            } else {
                $this->doForm('membermailform-disallowed');
            }
        }
    }

    /**
     * Parse skinvar nextarchive
     */
    public function parse_nextarchive()
    {
        global $archivenext;
        echo $archivenext;
    }

    /**
     * Parse skinvar nextitem
     * (include itemid of next item)
     */
    public function parse_nextitem()
    {
        global $itemidnext;
        if (isset($itemidnext)) {
            echo (int)$itemidnext;
        }
    }

    /**
     * Parse skinvar nextitemtitle
     * (include itemtitle of next item)
     */
    public function parse_nextitemtitle($format = '')
    {
        global $itemtitlenext;

        switch ($format) {
            case 'xml':
                echo stringToXML($itemtitlenext);
                break;
            case 'attribute':
                echo stringToAttribute($itemtitlenext);
                break;
            case 'raw':
                echo $itemtitlenext;
                break;
            default:
                echo hsc($itemtitlenext);
                break;
        }
    }

    /**
     * Parse skinvar nextlink
     */
    public function parse_nextlink($linktext = '', $amount = 10, $recount = '')
    {
        global $itemidnext, $archivenext, $startpos;
        if ($this->skintype == 'item') {
            $this->_itemlink($itemidnext, $linktext);
        } else {
            if ($this->skintype == 'search' || $this->skintype == 'index') {
                $this->_searchlink($amount, $startpos, 'next', $linktext, $recount);
            } else {
                $this->_archivelink($archivenext, $linktext);
            }
        }
    }

    /**
     * Parse skinvar nucleusbutton
     */
    public function parse_nucleusbutton(
        $imgurl = '',
        $imgwidth = '85',
        $imgheight = '31'
    ) {
        global $CONF;
        if ($imgurl == '') {
            $imgurl = $CONF['AdminURL'] . 'nucleus.gif';
        } else {
            if (PARSER::getProperty('IncludeMode') == 'skindir') {
                // when skindit IncludeMode is used: start from skindir
                $imgurl = $CONF['SkinsURL'] . PARSER::getProperty('IncludePrefix') . $imgurl;
            }
        }

        $this->formdata = array(
            'imgurl'    => $imgurl,
            'imgwidth'  => $imgwidth,
            'imgheight' => $imgheight,
        );
        $this->doForm('nucleusbutton');
    }

    /**
     * Parse skinvar otherarchive
     */
    public function parse_otherarchive($blogname, $template, $category = '')
    {
        global $archive, $manager;
        $y = $m = $d = 0;
        sscanf($archive, '%d-%d-%d', $y, $m, $d);
        $b = & $manager->getBlog(getBlogIDFromName($blogname));
        $this->_setBlogCategory($b, $category);
        $this->_preBlogContent('otherachive', $b);
        $b->showArchive($template, $y, $m, $d);
        $this->_postBlogContent('otherachive', $b);
    }

    /**
     * Parse skinvar otherarchivedaylist
     */
    public function parse_otherarchivedaylist($blogname, $template, $category = 'all', $limit = 0)
    {
        global $manager;
        if ($category == 'all') {
            $category = '';
        }
        $b = & $manager->getBlog(getBlogIDFromName($blogname));
        $this->_setBlogCategory($b, $category);
        $this->_preBlogContent('otherarchivelist', $b);
        $b->showArchiveList($template, 'day', $limit);
        $this->_postBlogContent('otherarchivelist', $b);
    }

    /**
     * Parse skinvar otherarchivelist
     */
    public function parse_otherarchivelist($blogname, $template, $category = 'all', $limit = 0)
    {
        global $manager;
        if ($category == 'all') {
            $category = '';
        }
        $b = & $manager->getBlog(getBlogIDFromName($blogname));
        $this->_setBlogCategory($b, $category);
        $this->_preBlogContent('otherarchivelist', $b);
        $b->showArchiveList($template, 'month', $limit);
        $this->_postBlogContent('otherarchivelist', $b);
    }

    /**
     * Parse skinvar otherarchiveyearlist
     */
    public function parse_otherarchiveyearlist($blogname, $template, $category = 'all', $limit = 0)
    {
        global $manager;
        if ($category == 'all') {
            $category = '';
        }
        $b = & $manager->getBlog(getBlogIDFromName($blogname));
        $this->_setBlogCategory($b, $category);
        $this->_preBlogContent('otherarchivelist', $b);
        $b->showArchiveList($template, 'year', $limit);
        $this->_postBlogContent('otherarchivelist', $b);
    }

    /**
     * Parse skinvar otherblog
     */
    public function parse_otherblog($blogname, $template, $amount = 10, $category = '')
    {
        global $manager;

        list($limit, $offset) = sscanf($amount, '%d(%d)');

        $b = & $manager->getBlog(getBlogIDFromName($blogname));
        $this->_setBlogCategory($b, $category);
        $this->_preBlogContent('otherblog', $b);
        $this->amountfound = $b->readLog($template, $limit, $offset);
        $this->_postBlogContent('otherblog', $b);
    }

    /**
     * Parse skinvar othersearchresults
     */
    public function parse_othersearchresults($blogname, $template, $maxresults = 50)
    {
        global $query, $amount, $manager, $startpos;
        $b = & $manager->getBlog(getBlogIDFromName($blogname));
        $this->_setBlogCategory($b, '');    // need this to select default category
        $this->_preBlogContent('othersearchresults', $b);
        $b->search($query, $template, $amount, $maxresults, $startpos);
        $this->_postBlogContent('othersearchresults', $b);
    }

    /**
     * Executes a plugin skinvar
     *
     * @param pluginName name of plugin (without the NP_)
     *
     * extra parameters can be added
     */
    public function parse_plugin($pluginName)
    {
        global $manager;

        // should be already tested from the parser (PARSER.php)
        // only continue when the plugin is really installed
        /*if (!$manager->pluginInstalled('NP_' . $pluginName))
            return;*/

        $plugin = & $manager->getPlugin('NP_' . $pluginName);
        if (!$plugin) {
            return;
        }

        // get arguments
        $params = func_get_args();

        // remove plugin name
        array_shift($params);

        // add skin type on front
        array_unshift($params, $this->skintype);

        call_user_func_array(array($plugin, 'doSkinVar'), $params);
    }

    /**
     * Parse skinvar prevarchive
     */
    public function parse_prevarchive()
    {
        global $archiveprev;
        echo $archiveprev;
    }

    /**
     * Parse skinvar preview
     */
    public function parse_preview($template)
    {
        global $blog, $CONF, $manager;

        $template        = & $manager->getTemplate($template);
        $row['body']     = '<span id="prevbody"></span>';
        $row['title']    = '<span id="prevtitle"></span>';
        $row['more']     = '<span id="prevmore"></span>';
        $row['itemlink'] = '';
        $row['itemid']   = 0;
        $row['blogid']   = $blog->getID();
        echo TEMPLATE::fill($template['ITEM_HEADER'], $row);
        echo TEMPLATE::fill($template['ITEM'], $row);
        echo TEMPLATE::fill($template['ITEM_FOOTER'], $row);
    }

    /*
     * Parse skinvar previtem
     * (include itemid of prev item)
     */
    public function parse_previtem()
    {
        global $itemidprev;
        if (isset($itemidprev)) {
            echo (int)$itemidprev;
        }
    }

    /**
     * Parse skinvar previtemtitle
     * (include itemtitle of prev item)
     */
    public function parse_previtemtitle($format = '')
    {
        global $itemtitleprev;

        switch ($format) {
            case 'xml':
                echo stringToXML($itemtitleprev);
                break;
            case 'attribute':
                echo stringToAttribute($itemtitleprev);
                break;
            case 'raw':
                echo $itemtitleprev;
                break;
            default:
                echo hsc($itemtitleprev);
                break;
        }
    }

    /**
     * Parse skinvar prevlink
     */
    public function parse_prevlink($linktext = '', $amount = 10)
    {
        global $itemidprev, $archiveprev, $startpos;

        if ($this->skintype == 'item') {
            $this->_itemlink($itemidprev, $linktext);
        } else {
            if ($this->skintype == 'search' || $this->skintype == 'index') {
                $this->_searchlink($amount, $startpos, 'prev', $linktext);
            } else {
                $this->_archivelink($archiveprev, $linktext);
            }
        }
    }

    /**
     * Parse skinvar query
     * (includes the search query)
     */
    public function parse_query()
    {
        global $query;
        echo hsc($query);
    }

    /**
     * Parse skinvar referer
     */
    public function parse_referer()
    {
        echo hsc(serverVar('HTTP_REFERER'));
    }

    /**
     * Parse skinvar searchform
     */
    public function parse_searchform($blogname = '')
    {
        global $CONF, $manager, $maxresults;
        if ($blogname) {
            $blog = & $manager->getBlog(getBlogIDFromName($blogname));
        } else {
            global $blog;
        }
        // use default blog when no blog is selected
        $this->formdata = array(
            'id'    => $blog ? $blog->getID() : $CONF['DefaultBlog'],
            'query' => hsc(getVar('query')),
        );
        $this->doForm('searchform');
    }

    /**
     * Parse skinvar searchresults
     */
    public function parse_searchresults($template, $maxresults = 50)
    {
        global $blog, $query, $amount, $startpos;

        $this->_setBlogCategory($blog, '');    // need this to select default category
        $this->_preBlogContent('searchresults', $blog);
        $this->amountfound = $blog->search($query, $template, $amount, $maxresults, $startpos);
        $this->_postBlogContent('searchresults', $blog);
    }

    /**
     * Parse skinvar self
     */
    public function parse_self()
    {
        global $CONF;
        echo $CONF['Self'];
    }

    /**
     * Parse skinvar sitevar
     * (include a sitevar)
     */
    public function parse_sitevar($which)
    {
        global $CONF;
        switch ($which) {
            case 'url':
                echo $CONF['IndexURL'];
                break;
            case 'name':
                echo $CONF['SiteName'];
                break;
            case 'admin':
                echo $CONF['AdminEmail'];
                break;
            case 'adminurl':
                echo $CONF['AdminURL'];
                break;
            default:
                if (isset($CONF[$which])) {
                    echo $CONF[$which];
                }
        }
    }

    /**
     * Parse skinname
     */
    public function parse_skinname()
    {
        echo $this->skin->getName();
    }

    /**
     * Parse skintype (experimental)
     */
    public function parse_skintype()
    {
        echo $this->skintype;
    }

    /**
     * Parse text
     */
    public function parse_text($which)
    {
        // constant($which) only available from 4.0.4 :(
        if (defined($which)) {
            eval("echo {$which};");
        }
    }

    /**
     * Parse ticket
     */
    public function parse_ticket()
    {
        global $manager;
        $manager->addTicketHidden();
    }

    /**
     * Parse ticket_id
     */
    public function parse_ticket_id()
    {
        global $manager;
        printf("%s", $manager->_generateTicket());
    }

    /**
     *    Parse skinvar todaylink
     *    A link to the today page (depending on selected blog, etc...)
     */
    public function parse_todaylink($linktext = '')
    {
        global $blog, $CONF;
        if ($blog) {
            echo $this->_link(createBlogidLink($blog->getID(), $this->linkparams), $linktext);
        } else {
            if (isset($CONF['SiteUrl']) && strlen($CONF['SiteUrl']) > 0) {
                echo $this->_link($CONF['SiteUrl'], $linktext);
            } else {
                echo hsc($linktext);
            }
        }
    }

    /**
     * Parse vars
     * When commentform is not used, to include a hidden field with itemid
     */
    public function parse_vars()
    {
        global $itemid;
        echo '<input type="hidden" name="itemid" value="' . $itemid . '" />';
    }

    /**
     * Parse skinvar version
     * (include nucleus versionnumber)
     */
    public function parse_version()
    {
        global $nucleus;
        echo sprintf('%s %s', hsc(CORE_APPLICATION_NAME), CORE_APPLICATION_VERSION);
    }

    /**
     * Parse skinvar sticky
     */
    public function parse_sticky($itemnumber = 0, $template = '')
    {
        global $manager;

        $itemnumber = intval($itemnumber);
        $itemarray  = array($itemnumber);

        $b = & $manager->getBlog(getBlogIDFromItemID($itemnumber));
        $this->_preBlogContent('sticky', $b);
        $this->amountfound = $b->readLogFromList($itemarray, $template);
        $this->_postBlogContent('sticky', $b);
    }

    public function parse_commentclosed()
    {
        global $blog, $itemid, $manager;

        $itemid = intval($itemid);
        // if item is closed, show message and do nothing
        $item = & $manager->getItem($itemid, 0, 0);
        if ($item['closed'] || !$blog->commentsEnabled()) {
            return true;
        } else {
            return false;
        }
    }

    public function parse_hascomment()
    {
        global $itemid;

        $itemid  = intval($itemid);
        $sqlText = sprintf(
            "SELECT COUNT(*) as result FROM %s WHERE citem = %d LIMIT 1",
            sql_table('comment'),
            intval($itemid)
        );
        $res = intval(quickQuery($sqlText));
        return ($res > 0);
    }
}
