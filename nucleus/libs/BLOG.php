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
 * A class representing a blog and containing functions to get that blog shown
 * on the screen
 *
 * @license http://nucleuscms.org/license.txt GNU General Public License
 * @copyright Copyright (C) The Nucleus Group
 */

if (! function_exists('requestVar')) {
    exit;
}
require_once __DIR__ . '/ITEMACTIONS.php';

class BLOG
{
    // blog id
    public $blogid;

    // ID of currently selected category
    public $selectedcatid;

    // After creating an object of the blog class, contains true if the BLOG object is
    // valid (the blog exists)
    public $isValid;

    // associative array, containing all blogsettings (use the get/set functions instead)
    public $settings;

    /**
     * Creates a new BLOG object for the given blog
     *
     * @param $id blogid
     */
    public function __construct($id)
    {
        $this->blogid = (int)$id;
        $this->readSettings();

        // try to set catid
        // (the parse functions in SKIN.php will override this, so it's mainly useless)
        global $catid;
        $this->setSelectedCategory($catid);
    }

    /**
     * Shows the given amount of items for this blog
     *
     * @param $template
     *      String representing the template _NAME_ (!)
     * @param $amountEntries
     *      amount of entries to show
     * @param $startpos
     *      offset from where items should be shown (e.g. 5 = start at fifth
     *      item)
     *
     * @returns int
     *      amount of items shown
     */
    public function readLog($template, $amountEntries, $offset = 0, $startpos = 0)
    {
        return $this->readLogAmount(
            $template,
            $amountEntries,
            '',
            '',
            1,
            1,
            $offset,
            $startpos
        );
    }

    /**
     * Shows an archive for a given month
     *
     * @param $year
     *      year
     * @param $month
     *      month
     * @param $template
     *      String representing the template name to be used
     */
    public function showArchive($templatename, $year, $month = 0, $day = 0)
    {
        // create extra where clause for select query
        if ($day == 0 && $month != 0) {
            $timestamp_start = mktime(0, 0, 0, $month, 1, $year);
            $timestamp_end   = mktime(
                0,
                0,
                0,
                $month + 1,
                1,
                $year
            );  // also works when $month==12
        } elseif ($month == 0) {
            $timestamp_start = mktime(0, 0, 0, 1, 1, $year);
            $timestamp_end   = mktime(
                0,
                0,
                0,
                12,
                31,
                $year
            );  // also works when $month==12
        } else {
            $timestamp_start = mktime(0, 0, 0, $month, $day, $year);
            $timestamp_end   = mktime(0, 0, 0, $month, $day + 1, $year);
        }
        $extra_query = sprintf(
            ' and i.itime>=%s and i.itime<%s',
            mysqldate($timestamp_start),
            mysqldate($timestamp_end)
        );

        $this->readLogAmount($templatename, 0, $extra_query, '', 1, 1);
    }

    /**
     * Sets the selected category by id (only when category exists)
     */
    public function setSelectedCategory($catid)
    {
        if ($this->isValidCategory($catid) || ((int)$catid == 0)) {
            $this->selectedcatid = (int)$catid;
        }
    }

    /**
     * Sets the selected category by name
     */
    public function setSelectedCategoryByName($catname)
    {
        $this->setSelectedCategory($this->getCategoryIdFromName($catname));
    }

    /**
     * Returns the selected category
     */
    public function getSelectedCategory()
    {
        return $this->selectedcatid;
    }

    /**
     * Shows the given amount of items for this blog
     *
     * @param $template
     *      String representing the template _NAME_ (!)
     * @param $amountEntries
     *      amount of entries to show (0 = no limit)
     * @param $extraQuery
     *      extra conditions to be added to the query
     * @param $highlight
     *      contains a query that should be highlighted
     * @param $comments
     *      1=show comments 0=don't show comments
     * @param $dateheads
     *      1=show dateheads 0=don't show dateheads
     * @param $offset
     *      offset
     *
     * @returns int
     *      amount of items shown
     */
    public function readLogAmount(
        $template,
        $amountEntries,
        $extraQuery,
        $highlight,
        $comments,
        $dateheads,
        $offset = 0,
        $startpos = 0
    ) {
        $query = $this->getSqlBlog($extraQuery);

        if ($amountEntries > 0) {
            // $offset zou moeten worden:
            // (($startpos / $amountentries) + 1) * $offset ... later testen ...
            $query .= ' LIMIT ' . (int)($startpos + $offset) . ','
                      . (int)$amountEntries;
        }

        return $this->showUsingQuery(
            $template,
            $query,
            $highlight,
            $comments,
            $dateheads
        );
    }

    /**
     * Do the job for readLogAmmount
     */
    public function showUsingQuery(
        $templateName,
        $query,
        $highlight = '',
        $comments = 0,
        $dateheads = 1
    ) {
        global $CONF, $manager;

        $lastVisit = cookieVar($CONF['CookiePrefix'] . 'lastVisit');
        if ($lastVisit != 0) {
            $lastVisit = $this->getCorrectTime($lastVisit);
        }

        // set templatename as global variable (so plugins can access it)
        global $currentTemplateName;
        $currentTemplateName = $templateName;

        $template = & $manager->getTemplate($templateName);

        // create parser object & action handler
        $actions = new ITEMACTIONS($this);
        $parser  = new PARSER($actions->getDefinedActions(), $actions);
        $actions->setTemplate($template);
        $actions->setHighlight($highlight);
        $actions->setLastVisit($lastVisit);
        $actions->setParser($parser);
        $actions->setShowComments($comments);

        // execute query
        $items = sql_query($query);

        $numrows = 0;
        // loop over all items
        $old_date = 0;
        while ($item = sql_fetch_object($items)) {
            $numrows++;
            $item->timestamp
                = strtotime($item->itime); // string timestamp -> unix timestamp

            // action handler needs to know the item we're handling
            $actions->setCurrentItem($item);

            // add date header if needed
            if ($dateheads) {
                $new_date = date('dFY', $item->timestamp);
                if ($new_date != $old_date) {
                    // unless this is the first time, write date footer
                    $timestamp = $item->timestamp;
                    if ($old_date != 0) {
                        $oldTS = strtotime($old_date);
                        $param = [
                            'blog'      => &$this,
                            'timestamp' => $oldTS,
                        ];
                        $manager->notify('PreDateFoot', $param);
                        $tmp_footer
                            = Utils::strftime(
                                isset($template['DATE_FOOTER'])
                                ? $template['DATE_FOOTER'] : '',
                                $oldTS
                            );
                        $parser->parse($tmp_footer);
                        $param = [
                            'blog'      => &$this,
                            'timestamp' => $oldTS,
                        ];
                        $manager->notify('PostDateFoot', $param);
                    }
                    $param = [
                        'blog'      => &$this,
                        'timestamp' => $timestamp,
                    ];
                    $manager->notify('PreDateHead', $param);
                    // note, to use templatvars in the dateheader, the %-characters need to be doubled in
                    // order to be preserved by strftime
                    $tmp_header
                        = Utils::strftime(
                            (isset($template['DATE_HEADER'])
                            ? $template['DATE_HEADER'] : null),
                            $timestamp
                        );
                    $parser->parse($tmp_header);
                    $param = [
                        'blog'      => &$this,
                        'timestamp' => $timestamp,
                    ];
                    $manager->notify('PostDateHead', $param);
                }
                $old_date = $new_date;
            }

            if (!defined('DISABLED_BLOG_CLEANITEMS') || (bool) constant('DISABLED_BLOG_CLEANITEMS') === false) {
                // cleaning item
                $this->cleanItem($item);
            }

            // parse item
            $parser->parse($template['ITEM_HEADER']);
            $param = [
                'blog' => &$this,
                'item' => &$item,
            ];
            $manager->notify('PreItem', $param);
            $parser->parse($template['ITEM']);
            $param = [
                'blog' => &$this,
                'item' => &$item,
            ];
            $manager->notify('PostItem', $param);
            $parser->parse($template['ITEM_FOOTER']);
        }

        // add another date footer if there was at least one item
        if (($numrows > 0) && $dateheads) {
            $param = [
                'blog'      => &$this,
                'timestamp' => strtotime($old_date),
            ];
            $manager->notify('PreDateFoot', $param);
            $parser->parse($template['DATE_FOOTER']);
            $param = [
                'blog'      => &$this,
                'timestamp' => strtotime($old_date),
            ];
            $manager->notify('PostDateFoot', $param);
        }

        sql_free_result($items);    // free memory

        return $numrows;
    }

    /**
     * Simplified function for showing only one item
     */
    public function showOneitem($itemid, $template, $highlight)
    {
        $extraQuery = ' and inumber=' . (int)$itemid;

        return $this->readLogAmount(
            $template,
            1,
            $extraQuery,
            $highlight,
            0,
            0
        );
    }

    /**
     * Adds an item to this blog
     */
    public function additem(
        $icat,
        $ititle,
        $ibody,
        $imore,
        $iblog,
        $iauthor,
        $itime,
        $iclosed,
        $idraft,
        $iposted = '1'
    ) {
        global $manager;

        $iblog    = (int)$iblog;
        $iauthor  = (int)$iauthor;
        $icat     = (int)$icat;
        $isFuture = 0;

        // convert newlines to <br />
        if ($this->convertBreaks()) {
            $ibody = addBreaks($ibody);
            $imore = addBreaks($imore);
        }

        if ($iclosed != 1) {
            $iclosed = '0';
        }
        if ($idraft != 0) {
            $idraft = '1';
        }

        if (! $this->isValidCategory($icat)) {
            $icat = $this->getDefaultCategory();
        }

        if ($itime > $this->getCorrectTime()) {
            $isFuture = 1;
        }

        $itime = date('Y-m-d H:i:s', $itime);

        $param = [
            'title'     => &$ititle,
            'body'      => &$ibody,
            'more'      => &$imore,
            'blog'      => &$this,
            'authorid'  => &$iauthor,
            'timestamp' => &$itime,
            'closed'    => &$iclosed,
            'draft'     => &$idraft,
            'catid'     => &$icat,
        ];
        $manager->notify('PreAddItem', $param);

        $ititle = sql_quote_string($ititle);
        $ibody  = sql_quote_string($ibody);
        $imore  = sql_quote_string($imore);

        $query = parseQuery(
            "INSERT INTO [@prefix@]item (ititle, ibody, imore, iblog, iauthor, itime, iclosed, idraft, icat, iposted) VALUES ({$ititle}, {$ibody}, {$imore}, {$iblog}, {$iauthor}, '{$itime}', {$iclosed}, {$idraft}, {$icat}, {$iposted})"
        );
        sql_query($query);
        $itemid = sql_insert_id();

        $param = ['itemid' => $itemid];
        $manager->notify('PostAddItem', $param);

        if (! $idraft) {
            $this->updateUpdateFile();
        }

        // send notification mail
        if (! $idraft && ! $isFuture && $this->getNotifyAddress()
             && $this->notifyOnNewItem()) {
            $this->sendNewItemNotification($itemid, $ititle, $ibody);
        }

        return $itemid;
    }

    /**
     * Send a new item notification to the notification list
     *
     * @param $itemid
     *        ID of the item
     * @param $title
     *        title of the item
     * @param $body
     *        body of the item
     */
    public function sendNewItemNotification($itemid, $title, $body)
    {
        global $CONF, $member;

        // create text version of html post
        $ascii = toAscii($body);

        $mailto_msg = _NOTIFY_NI_MSG . " \n";
        //        $mailto_msg .= $CONF['IndexURL'] . 'index.php?itemid=' . $itemid . "\n\n";
        $temp = parse_url($CONF['Self']);
        if ($temp['scheme']) {
            $mailto_msg .= createItemLink($itemid) . "\n\n";
        } else {
            // Todo:
            $tempurl = $this->getURL();
            if (substr($tempurl, -1) === '/'
                || substr($tempurl, -4) === '.php') {
                $mailto_msg .= $tempurl . '?itemid=' . $itemid . "\n\n";
            } else {
                $mailto_msg .= $tempurl . '/?itemid=' . $itemid . "\n\n";
            }
        }
        $mailto_msg .= _NOTIFY_TITLE . ' ' . strip_tags($title) . "\n";
        $mailto_msg .= _NOTIFY_CONTENTS . "\n " . $ascii . "\n";
        $mailto_msg .= getMailFooter();

        $mailto_title = $this->getName() . ': ' . _NOTIFY_NI_TITLE;

        $frommail = $member->getNotifyFromMailAddress();

        $notify = new NOTIFICATION($this->getNotifyAddress());
        $notify->notify($mailto_title, $mailto_msg, $frommail);
    }

    /**
     * Creates a new category for this blog
     *
     * @param $catName
     *        name of the new category. When empty, a name is generated
     *        automatically
     *        (starting with newcat)
     * @param $catDescription
     *        description of the new category. Defaults to 'New Category'
     *
     * @returns
     *        the new category-id in case of success.
     *        0 on failure
     */
    public function createNewCategory(
        $catName = '',
        $catDescription = _CREATED_NEW_CATEGORY_DESC,
        $corder = null
    ) {
        global $member, $manager;

        if ($member->blogAdminRights($this->getID())) {
            // generate
            if ($catName == '') {
                $catName = _CREATED_NEW_CATEGORY_NAME;
                $i       = 1;

                $res = true;
                while ($res !== false) {
                    $ph = [
                        'cname' => sql_quote_string($catName . $i),
                        'cblog' => (int)$this->getID(),
                    ];
                    $sql
                         = parseQuery(
                             'SELECT catid AS result FROM [@prefix@]category WHERE cname=[@cname@] and cblog=[@cblog@]',
                             $ph
                         );
                    $res = quickQuery($sql);
                    if (empty($res)) {
                        break;
                    }
                    $i++;
                }

                $catName = $catName . $i;
            }

            $param = [
                'blog'        => &$this,
                'name'        => &$catName,
                'description' => $catDescription,
                'order'       => &$corder,
            ];
            $manager->notify('PreAddCategory', $param);

            $ph['cblog'] = $this->getID();
            $ph['cname'] = sql_quote_string($catName);
            $ph['cdesc'] = sql_quote_string($catDescription);
            if (! is_null($corder)) {
                $ph['corder'] = (int)$corder;
                $query
                              = 'INSERT INTO [@prefix@]category (cblog, cname, cdesc, corder) VALUES ([@cblog@],[@cname@],[@cdesc@],[@corder@])';
            } else {
                $query
                    = 'INSERT INTO [@prefix@]category (cblog, cname, cdesc) VALUES ([@cblog@], [@cname@], [@cdesc@])';
            }
            sql_query(parseQuery($query, $ph));
            $catid = sql_insert_id();

            $param = [
                'blog'        => &$this,
                'name'        => $catName,
                'description' => $catDescription,
                'catid'       => $catid,
                'order'       => $corder,
            ];
            $manager->notify('PostAddCategory', $param);

            return $catid;
        }

        return 0;
    }

    /**
     * Searches all months of this blog for the given query
     *
     * @param $keywords
     *      search query
     * @param $template
     *      template to be used (__NAME__ of the template)
     * @param $amountMonths
     *      max amount of months to be search (0 = all)
     * @param $maxresults
     *      max number of results to show
     * @param $startpos
     *      offset
     *
     * @returns
     *      amount of hits found
     */
    public function search($keywords, $template, $amountMonths, $maxresults, $startpos)
    {
        global $manager;

        $highlight = '';
        $sqlquery  = $this->getSqlSearch($keywords, $amountMonths, $highlight);

        if ($sqlquery == '') {
            // no query -> show everything
            $extraquery  = '';
            $amountfound = $this->readLogAmount(
                $template,
                $maxresults,
                $extraquery,
                $keywords,
                1,
                1
            );
        } else {
            // add LIMIT to query (to split search results into pages)
            if ((int)($maxresults > 0)) {
                $sqlquery .= ' LIMIT ' . (int)$startpos . ','
                             . (int)$maxresults;
            }

            // show results
            $amountfound = $this->showUsingQuery(
                $template,
                $sqlquery,
                $highlight,
                1,
                1
            );

            // when no results were found, show a message
            if ($amountfound == 0) {
                $template = & $manager->getTemplate($template);
                $vars     = [
                    'query'  => hsc($keywords),
                    'blogid' => $this->getID(),
                ];
                echo TEMPLATE::fill($template['SEARCH_NOTHINGFOUND'], $vars);
            }
        }

        return $amountfound;
    }

    /**
     * Returns an SQL query to use for a search query
     *
     * @param $keywords
     *      search query
     * @param $amountMonths
     *      amount of months to search back. Default = 0 = unlimited
     * @returns $highlight
     *      words to highlight (out parameter)
     * @param $mode
     *      either empty, or 'count'. In this case, the query will be a SELECT
     *      COUNT(*) query
     *
     * @returns
     *      either a full SQL query, or an empty string (if querystring empty)
     * @note
     *      No LIMIT clause is added. (caller should add this if multiple pages
     *      are requested)
     */
    public function getSqlSearch($keywords, $amountMonths = 0, &$highlight = null, $mode = '')
    {
        $search = new SEARCH($keywords);
        $search->set('fields', 'ititle,ibody,imore');

        if (stripos(getLanguageName(), 'japanese') !== false) {
            $search->set('mode', 'likeonly');
        }

        $highlight = $search->remove_boolean_operators();

        // if querystring is empty, return empty string
        if ($highlight == '') {
            return '';
        }

        $score = $search->get_score();

        if ($mode == '') {
            $fields = [];
            $fields[]
                      = 'i.inumber as itemid, i.ititle as title, i.ibody as body, i.itime, i.imore as more, i.icat as catid, i.iclosed as closed';
            $fields[] = 'c.cname as category';
            $fields[]
                      = 'm.mname as author, m.mrealname as authorname, m.mnumber as authorid, m.memail as authormail, m.murl as authorurl';
            if ($score) {
                $fields[] = $score . ' as score ';
            }
        } else {
            $fields = 'COUNT(*) as result ';
        }

        $from   = [];
        $from[] = '[@prefix@]item i';
        $from[] = 'LEFT JOIN [@prefix@]member m ON i.iauthor=m.mnumber';
        $from[] = 'LEFT JOIN [@prefix@]category c ON i.icat=c.catid';

        $where   = [];
        $where[] = 'i.idraft=0';  // exclude drafts
        $blogs
                 = $this->searchableBlogs(); // array containing blogs that always need to be included
        if (! in_array($this->getID(), $blogs)) {
            $blogs[] = $this->getID();   // also search current blog (duh)
        }
        if (1 < count($blogs)) {
            $where[] = sprintf('AND i.iblog IN (%s)', implode(',', $blogs));
        } else {
            $where[] = sprintf('AND i.iblog=%s', $this->getID());
        }
        // don't show future items
        $where[] = 'AND i.itime<=' . mysqldate($this->getCorrectTime());
        $where[] = 'AND ' . $search->get_where_phrase();

        // take into account amount of months to search
        if ($amountMonths > 0) {
            $localtime       = getdate($this->getCorrectTime());
            $timestamp_start = mktime(
                0,
                0,
                0,
                $localtime['mon'] - $amountMonths,
                1,
                $localtime['year']
            );
            $where[] = 'AND i.itime>' . mysqldate($timestamp_start);
        }

        if ($mode == '') {
            if ($score) {
                $extra = ' ORDER BY score DESC';
            } else {
                $extra = ' ORDER BY i.itime DESC ';
            }
        } else {
            $extra = '';
        }

        return selectQuery($from, $where, $fields, $extra);
    }

    public function searchableBlogs()
    {
        $res
               = sql_query(parseQuery('SELECT bnumber FROM [@prefix@]blog WHERE bincludesearch=1'));
        $blogs = [];
        while ($obj = sql_fetch_object($res)) {
            $blogs[] = (int)$obj->bnumber;
        }

        return $blogs;
    }

    /**
     * Returns the SQL query that's normally used to display the blog items on
     * the index type skins
     *
     * @param $mode
     *      either empty, or 'count'. In this case, the query will be a SELECT
     *      COUNT(*) query
     *
     * @returns
     *      either a full SQL query, or an empty string
     * @note
     *      No LIMIT clause is added. (caller should add this if multiple pages
     *      are requested)
     */
    public function getSqlBlog($extraQuery, $mode = '')
    {
        if ($mode == '') {
            $query
                = 'SELECT i.inumber as itemid, i.ititle as title, i.ibody as body, m.mname as author, m.mrealname as authorname, i.itime, i.imore as more, m.mnumber as authorid, m.memail as authormail, m.murl as authorurl, c.cname as category, i.icat as catid, i.iclosed as closed';
        } else {
            $query = 'SELECT COUNT(*) as result ';
        }

        $query .= parseQuery(' FROM [@prefix@]item as i, [@prefix@]member as m, [@prefix@]category as c');
        $query .= sprintf(
            " WHERE i.iblog=%d and i.iauthor=m.mnumber and i.icat=c.catid and i.idraft=0 and i.itime<=%s",
            $this->blogid,
            mysqldate($this->getCorrectTime())
        );

        if ($this->getSelectedCategory()) {
            $query .= ' and i.icat=' . $this->getSelectedCategory() . ' ';
        }

        $query .= $extraQuery;

        if ($mode == '') {
            $query .= ' ORDER BY i.itime DESC';
        }

        return $query;
    }

    public function _workaround_gettext_callback($m)
    {
        return SKIN::_getText($m[1]);
    }

    private function _workaround_gettext_template(&$template)
    {
        // Note: ArchiveList is not parced. parcer not called.
        // MARKER_FEATURE_LOCALIZATION_SKIN_TEXT
        // workaround for <%_()%>
        foreach ($template as $key => $value) {
            if (!is_null($value) && (strlen($value) > 0) && str_contains($value, '<%_(')) {
                $template[$key] = preg_replace_callback(
                    '#<%_\(([^)]*?)\)%>#',
                    [$this, '_workaround_gettext_callback'],
                    $value
                );
            }
        }
        //        var_dump($template);
        if (! isset($template['LOCALE']) || ! $template['LOCALE']) {
            if (PHP_OS === 'WINNT' && defined('_LOCALE_NAME_WINDOWS')) {
                $template['LOCALE'] = _LOCALE_NAME_WINDOWS;
            } else {
                $template['LOCALE'] = _LOCALE;
            }
            setlocale(LC_TIME, $template['LOCALE']);
        }
        if ((_LOCALE === 'ja_JP') && isset($template['FORMAT_DATE'])) {
            if ($template['FORMAT_DATE'] === '%d/%m') {
                $template['FORMAT_DATE'] = '%m/%d';
            }
        }
        //        var_dump($template['LOCALE']);
    }

    /**
     * Shows the archivelist using the given template
     */
    public function showArchiveList($template, $mode = 'month', $limit = 0)
    {
        global $catid, $manager;

        $linkparams = [];
        if ($catid) {
            $linkparams = ['catid' => $catid];
        }

        $template           = & $manager->getTemplate($template);
        $archdata           = [];
        $archdata['blogid'] = $this->getID();

        // Note: ArchiveList is not parced. parcer not called.
        // MARKER_FEATURE_LOCALIZATION_SKIN_TEXT
        // workaround for <%_()%>
        $this->_workaround_gettext_template($template);

        //
        $tplt = ! isset($template['ARCHIVELIST_HEADER']) ? ''
            : $template['ARCHIVELIST_HEADER'];

        echo TEMPLATE::fill($tplt, $archdata);

        $ph['iblog'] = $this->getID();
        $ph['itime']
                     = mysqldate($this->getCorrectTime()); // don't show future items!
        $query
                     = 'SELECT itime, SUBSTRING(itime,1,4) AS Year, SUBSTRING(itime,6,2) AS Month, SUBSTRING(itime,9,2) as Day FROM [@prefix@]item'
                       . ' WHERE iblog=[@iblog@] AND itime <=[@itime@] AND idraft=0';

        if ($catid) {
            $query .= ' AND icat=' . (int)$catid;
        }

        $query .= ' GROUP BY Year';
        if ($mode === 'month' || $mode === 'day') {
            $query .= ', Month';
        }
        if ($mode === 'day') {
            $query .= ', Day';
        }

        $query .= ' ORDER BY itime DESC';

        if ($limit > 0) {
            $query .= ' LIMIT ' . (int)$limit;
        }

        $res = sql_query(parseQuery($query, $ph));

        while ($current = sql_fetch_object($res)) {
            $current->itime
                = strtotime($current->itime);    // string time -> unix timestamp

            if ($mode === 'day') {
                $archivedate       = date('Y-m-d', $current->itime);
                $archive['day']    = date('d', $current->itime);
                $archdata['day']   = date('d', $current->itime);
                $archdata['month'] = date('m', $current->itime);
                $archive['month']  = $archdata['month'];
            } elseif ($mode === 'year') {
                $archivedate       = date('Y', $current->itime);
                $archdata['day']   = '';
                $archdata['month'] = '';
                $archive['day']    = '';
                $archive['month']  = '';
            } else {
                $archivedate       = date('Y-m', $current->itime);
                $archdata['month'] = date('m', $current->itime);
                $archive['month']  = $archdata['month'];
                $archdata['day']   = '';
                $archive['day']    = '';
            }

            $archdata['year']        = date('Y', $current->itime);
            $archive['year']         = $archdata['year'];
            $archdata['archivelink'] = createArchiveLink(
                $this->getID(),
                $archivedate,
                $linkparams
            );

            $param = ['listitem' => &$archdata];
            $manager->notify('PreArchiveListItem', $param);

            $temp = TEMPLATE::fill(
                $template['ARCHIVELIST_LISTITEM'],
                $archdata
            );
            echo Utils::strftime($temp, $current->itime);
        }

        sql_free_result($res);

        $tplt = isset($template['ARCHIVELIST_FOOTER'])
            ? $template['ARCHIVELIST_FOOTER'] : '';
        echo TEMPLATE::fill($tplt, $archdata);
    }

    /**
     * Shows the list of categories using a given template
     */
    public function showCategoryList($template)
    {
        global $CONF, $manager;

        // determine arguments next to catids
        // I guess this can be done in a better way, but it works
        global $archive, $archivelist;

        $linkparams = [];
        if ($archive) {
            $blogurl = createArchiveLink(
                $this->getID(),
                $archive,
                ''
            );
            $linkparams['blogid']  = $this->getID();
            $linkparams['archive'] = $archive;
        } else {
            if ($archivelist) {
                $blogurl = createArchiveListLink(
                    $this->getID(),
                    ''
                );
                $linkparams['archivelist'] = $archivelist;
            } else {
                $blogurl              = createBlogidLink($this->getID(), '');
                $linkparams['blogid'] = $this->getID();
            }
        }

        //$blogurl = $this->getURL() . $qargs;
        //$blogurl = createBlogLink($this->getURL(), $linkparams);

        $template = & $manager->getTemplate($template);

        // Note: ArchiveList is not parced. parcer not called.
        // MARKER_FEATURE_LOCALIZATION_SKIN_TEXT
        // workaround for <%_()%>
        $this->_workaround_gettext_template($template);

        //: Change: Set nocatselected variable
        if ($this->getSelectedCategory()) {
            $nocatselected = 'no';
        } else {
            $nocatselected = 'yes';
        }

        echo TEMPLATE::fill(
            (isset($template['CATLIST_HEADER'])
            ? $template['CATLIST_HEADER'] : null),
            [
                'blogid'  => $this->getID(),
                'blogurl' => $blogurl,
                'self'    => $CONF['Self'],
                //: Change: Set catiscurrent template variable for header
                'catiscurrent' => $nocatselected,
                'currentcat'   => $nocatselected,
            ]
        );

        $ph['cblog'] = $this->getID();
        $query       = 'SELECT * FROM [@prefix@]category WHERE cblog=[@cblog@]';
        if ((int)$CONF['DatabaseVersion'] >= 371) {
            $query .= ' ORDER BY corder ASC,cname ASC';
        } else {
            $query .= ' ORDER BY cname ASC';
        }
        $res = sql_query(parseQuery($query, $ph));

        while ($catdata = sql_fetch_assoc($res)) {
            $catdata['catname']  = $catdata['cname'];
            $catdata['catdesc']  = $catdata['cdesc'];
            $catdata['catorder'] = $catdata['corder'];
            $catdata['blogid']   = $this->getID();
            $catdata['blogurl']  = $blogurl;
            $catdata['catlink']  = createLink(
                'category',
                [
                    'catid' => $catdata['catid'],
                    'name'  => $catdata['catname'],
                    'extra' => $linkparams,
                ]
            );
            $catdata['self'] = $CONF['Self'];

            //catiscurrent
            //: Change: Bugfix for catiscurrent logic so it gives catiscurrent = no when no category is selected.
            $catdata['catiscurrent'] = 'no';
            $catdata['currentcat']   = 'no';
            if ($this->getSelectedCategory()) {
                if ($this->getSelectedCategory() == $catdata['catid']) {
                    $catdata['catiscurrent'] = 'yes';
                    $catdata['currentcat']   = 'yes';
                }
                /*else {
                    $catdata['catiscurrent'] = 'no';
                    $catdata['currentcat'] = 'no';
                }*/
            } else {
                global $itemid;
                if ((int)$itemid && $manager->existsItem((int)$itemid, 0, 0)) {
                    $iobj = & $manager->getItem((int)$itemid, 0, 0);
                    $cid  = $iobj['catid'];
                    if ($cid == $catdata['catid']) {
                        $catdata['catiscurrent'] = 'yes';
                        $catdata['currentcat']   = 'yes';
                    }
                    /*else {
                        $catdata['catiscurrent'] = 'no';
                        $catdata['currentcat'] = 'no';
                    }*/
                }
            }

            $param = ['listitem' => &$catdata];
            $manager->notify('PreCategoryListItem', $param);

            echo TEMPLATE::fill(
                (isset($template['CATLIST_LISTITEM'])
                ? $template['CATLIST_LISTITEM'] : null),
                $catdata
            );
            //$temp = TEMPLATE::fill((isset($template['CATLIST_LISTITEM']) ? $template['CATLIST_LISTITEM'] : null), $catdata);
            //echo strftime($temp, $current->itime);
        }

        sql_free_result($res);

        echo TEMPLATE::fill(
            (isset($template['CATLIST_FOOTER'])
            ? $template['CATLIST_FOOTER'] : null),
            [
                'blogid'  => $this->getID(),
                'blogurl' => $blogurl,
                'self'    => $CONF['Self'],
                //: Change: Set catiscurrent template variable for footer
                'catiscurrent' => $nocatselected,
                'currentcat'   => $nocatselected,
            ]
        );
    }

    /**
     * Shows a list of all blogs in the system using a given template
     * ordered by number, name, shortname or description
     * in ascending or descending order
     */
    public static function showBlogList(
        $template,
        $bnametype,
        $orderby,
        $direction
    ) {
        global $CONF, $manager;

        $template = & $manager->getTemplate($template);

        echo TEMPLATE::fill(
            (isset($template['BLOGLIST_HEADER'])
            ? $template['BLOGLIST_HEADER'] : null),
            [
                'sitename' => $CONF['SiteName'],
                'siteurl'  => $CONF['IndexURL'],
            ]
        );

        switch (strtolower($orderby)) {
            case 'name':
                $ph['orderby'] = 'bname';
                break;
            case 'shortname':
                $ph['orderby'] = 'bshortname';
                break;
            case 'description':
                $ph['orderby'] = 'bdesc';
                break;
            default:
                $ph['orderby'] = 'bnumber';
        }
        $ph['direction'] = (strtolower($direction) === 'desc') ? 'DESC' : 'ASC';

        $query
             = 'SELECT bnumber, bname, bshortname, bdesc, burl FROM [@prefix@]blog ORDER BY [@orderby@] [@direction@]';
        $res = sql_query(parseQuery($query, $ph));
        if ($res) {
            $usePathInfo = ($CONF['URLMode'] === 'pathinfo');
            while ($bldata = sql_fetch_assoc($res)) {
                $list = [];

                //            $list['bloglink'] = createLink('blog', array('blogid' => $data['bnumber']));
                if (strlen(trim($bldata['burl'])) > 0) {
                    $list['bloglink'] = $bldata['burl'];
                } else {
                    $list['bloglink'] = createBlogidLink($bldata['bnumber']);
                }

                $list['blogdesc'] = $bldata['bdesc'];

                $list['blogurl'] = $bldata['burl'];

                if ($bnametype === 'shortname') {
                    $list['blogname'] = $bldata['bshortname'];
                } else { // all other cases
                    $list['blogname'] = hsc($bldata['bname']);
                }

                $param = ['listitem' => &$list];
                $manager->notify('PreBlogListItem', $param);

                echo TEMPLATE::fill(
                    (isset($template['BLOGLIST_LISTITEM'])
                    ? $template['BLOGLIST_LISTITEM'] : null),
                    $list
                );
            }
            sql_free_result($res);
        }

        echo TEMPLATE::fill(
            (isset($template['BLOGLIST_FOOTER'])
            ? $template['BLOGLIST_FOOTER'] : null),
            [
                'sitename' => $CONF['SiteName'],
                'siteurl'  => $CONF['IndexURL'],
            ]
        );
    }

    /**
     * Read the blog settings
     */
    public function readSettings()
    {
        $ph['bnumber'] = $this->blogid;
        $query
                       = parseQuery(
                           'SELECT * FROM [@prefix@]blog WHERE bnumber=[@bnumber@]',
                           $ph
                       );
        $res = sql_query($query);

        $this->settings = ($res ? sql_fetch_assoc($res) : []);
        $this->isValid  = ! empty($this->settings);
        if (! $this->isValid) {
            $this->settings = [];
        }
    }

    /**
     * Write the blog settings
     */
    public function writeSettings()
    {
        $btimeoffset = $this->getTimeOffset();

        $v = [];

        $v['bname']        = $this->getName();
        $v['bshortname']   = $this->getShortName();
        $v['bcomments']    = (int)$this->commentsEnabled();
        $v['bmaxcomments'] = (int)$this->getMaxComments();
        $v['btimeoffset']  = is_float($btimeoffset) ? $btimeoffset
            : (int)$btimeoffset;
        $v['bpublic']        = (int)$this->isPublic();
        $v['breqemail']      = (int)$this->emailRequired();
        $v['bconvertbreaks'] = (int)$this->convertBreaks();
        $v['ballowpast']     = (int)$this->allowPastPosting();
        $v['bnotify']        = $this->getNotifyAddress();
        $v['bnotifytype']    = (int)$this->getNotifyType();
        $v['burl']           = $this->getURL();
        $v['bupdate']        = $this->getUpdateFile();
        $v['bdesc']          = $this->getDescription();
        $v['bdefcat']        = (int)$this->getDefaultCategory();
        $v['bdefskin']       = (int)$this->getDefaultSkin();
        $v['bincludesearch'] = (int)$this->getSearchable();
        $v['bnumber']        = (int)$this->getID();
        if (sql_existTableColumnName('[@prefix@]blog', 'bauthorvisible')) {
            $v['bauthorvisible'] = (int)$this->getAuthorVisible();
        }

        $where = parseQuery('bnumber=[@bnumber@]', $v);
        updateQuery('[@prefix@]blog', $v, $where);
    }

    /**
     * Update the update file if requested
     */
    public function updateUpdatefile()
    {
        if ($this->getUpdateFile()) {
            $f_update = fopen($this->getUpdateFile(), 'w');
            fwrite($f_update, $this->getCorrectTime());
            fclose($f_update);
        }
    }

    /**
     * Check if a category with a given catid is valid
     *
     * @param $catid
     *     category id
     */
    public function isValidCategory($catid)
    {
        $ph['cblog'] = $this->getID();
        $ph['catid'] = (int)$catid;
        $query
                     = parseQuery(
                         'SELECT count(*) FROM [@prefix@]category WHERE cblog=[@cblog@] AND catid=[@catid@] LIMIT 1',
                         $ph
                     );
        if ($res = sql_query($query)) {
            return ((int)sql_result($res) > 0);
        }

        return false;
    }

    /**
     * Get the category name for a given catid
     *
     * @param $catid
     *     category id
     */
    public function getCategoryName($catid)
    {
        $ph['cblog'] = $this->getID();
        $ph['catid'] = (int)$catid;
        $query
                     = parseQuery(
                         'SELECT cname FROM [@prefix@]category WHERE cblog=[@cblog@] AND catid=[@catid@]',
                         $ph
                     );
        if (($res = sql_query($query)) && ($o = sql_fetch_object($res))) {
            return $o->cname;
        }

        return "";
    }

    /**
     * Get the category description for a given catid
     *
     * @param $catid
     *     category id
     */
    public function getCategoryDesc($catid)
    {
        $ph['cblog'] = $this->getID();
        $ph['catid'] = (int)$catid;
        $query
                     = parseQuery(
                         'SELECT cdesc FROM [@prefix@]category WHERE cblog=[@cblog@] AND catid=[@catid@]',
                         $ph
                     );
        if (($res = sql_query($query)) && ($o = sql_fetch_object($res))) {
            return $o->cdesc;
        }

        return "";
    }

    public function getCategoryOrder($catid)
    {
        $ph['cblog'] = $this->getID();
        $ph['catid'] = (int)$catid;
        $query
                     = parseQuery(
                         'SELECT corder FROM [@prefix@]category WHERE cblog=[@cblog@] AND catid=[@catid@]',
                         $ph
                     );
        if (($res = sql_query($query)) && ($o = sql_fetch_object($res))) {
            return (int)$o->corder;
        }

        return 100; // default
    }

    /**
     * Get the category id for a given category name
     *
     * @param $name
     *     category name
     */
    public function getCategoryIdFromName($name)
    {
        $ph['cblog'] = $this->getID();
        $ph['cname'] = sql_real_escape_string($name);
        $query
                     = parseQuery(
                         "SELECT catid FROM [@prefix@]category WHERE cblog=[@cblog@] AND cname='[@cname@]'",
                         $ph
                     );
        $res = sql_query($query);
        if ($res && ($o = sql_fetch_object($res))) {
            return $o->catid;
        } else {
            return $this->getDefaultCategory();
        }
    }

    /**
     * Get the the setting for the line break handling
     * [should be named as getConvertBreaks()]
     */
    public function convertBreaks()
    {
        return $this->getSetting('bconvertbreaks');
    }

    /**
     * Set the the setting for the line break handling
     *
     * @param $val
     *     new value for bconvertbreaks
     */
    public function setConvertBreaks($val)
    {
        $this->setSetting('bconvertbreaks', $val);
    }

    /**
     * Insert a javascript that includes information about the settings
     * of an author:  ConvertBreaks, MediaUrl and AuthorId
     *
     * @param $authorid
     *     id of the author
     */
    public function insertJavaScriptInfo($authorid = '')
    {
        global $member, $CONF;

        if ($authorid == '') {
            $authorid = $member->getID();
        }

        ?>
        <script type="text/javascript">
            setConvertBreaks(<?php echo $this->convertBreaks() ? 'true'
                : 'false' ?>);
            setMediaUrl("<?php echo $CONF['MediaURL']?>");
            setAuthorId(<?php echo $authorid?>);
        </script>
        <?php
    }

    /**
     * Set the the setting for allowing to publish postings in the past
     *
     * @param $val
     *     new value for ballowpast
     */
    public function setAllowPastPosting($val)
    {
        $this->setSetting('ballowpast', $val);
    }

    /**
     * Get the the setting if it is allowed to publish postings in the past
     * [should be named as getAllowPastPosting()]
     */
    public function allowPastPosting()
    {
        return $this->getSetting('ballowpast');
    }

    public function allowScriptTagInItem()
    {
        return false; // not implemented yet
    }

    public function allowScriptEventAttributeInItem()
    {
        return false; // not implemented yet
    }

    public function getCorrectTime($t = 0)
    {
        if ($t == 0) {
            $t = serverVar('request_time');
        }

        return ($t + 3600 * $this->getTimeOffset());
    }

    public function getName()
    {
        return $this->getSetting('bname');
    }

    public function getShortName()
    {
        return $this->getSetting('bshortname');
    }

    public function getMaxComments()
    {
        return $this->getSetting('bmaxcomments');
    }

    public function getNotifyAddress()
    {
        return $this->getSetting('bnotify');
    }

    public function getNotifyType()
    {
        return $this->getSetting('bnotifytype');
    }

    public function notifyOnComment()
    {
        $n = $this->getNotifyType();

        return (($n != 0) && (($n % 3) == 0));
    }

    public function notifyOnVote()
    {
        $n = $this->getNotifyType();

        return (($n != 0) && (($n % 5) == 0));
    }

    public function notifyOnNewItem()
    {
        $n = $this->getNotifyType();

        return (($n != 0) && (($n % 7) == 0));
    }

    public function setNotifyType($val)
    {
        $this->setSetting('bnotifytype', $val);
    }

    public function getTimeOffset()
    {
        return $this->getSetting('btimeoffset');
    }

    public function commentsEnabled()
    {
        return $this->getSetting('bcomments');
    }

    public function getURL()
    {
        return $this->getSetting('burl');
    }

    public function getRealURL()
    {
        $url = $this->getSetting('burl');
        if (strlen(trim($url)) == 0) {
            $url = createBlogidLink($this->getID());
        }

        return $url;
    }

    public function getDefaultSkin()
    {
        return $this->getSetting('bdefskin');
    }

    public function getUpdateFile()
    {
        return $this->getSetting('bupdate');
    }

    public function getDescription()
    {
        return $this->getSetting('bdesc');
    }

    public function isPublic()
    {
        return $this->getSetting('bpublic');
    }

    public function emailRequired()
    {
        return $this->getSetting('breqemail');
    }

    public function getSearchable()
    {
        return $this->getSetting('bincludesearch');
    }

    public function getDefaultCategory()
    {
        return $this->getSetting('bdefcat');
    }

    public function setPublic($val)
    {
        $this->setSetting('bpublic', $val);
    }

    public function setSearchable($val)
    {
        $this->setSetting('bincludesearch', $val);
    }

    public function setDescription($val)
    {
        $this->setSetting('bdesc', $val);
    }

    public function setUpdateFile($val)
    {
        $this->setSetting('bupdate', $val);
    }

    public function setDefaultSkin($val)
    {
        $this->setSetting('bdefskin', $val);
    }

    public function setURL($val)
    {
        $this->setSetting('burl', $val);
    }

    public function setName($val)
    {
        $this->setSetting('bname', $val);
    }

    public function setShortName($val)
    {
        $this->setSetting('bshortname', $val);
    }

    public function setCommentsEnabled($val)
    {
        $this->setSetting('bcomments', $val);
    }

    public function setMaxComments($val)
    {
        $this->setSetting('bmaxcomments', $val);
    }

    public function setNotifyAddress($val)
    {
        $this->setSetting('bnotify', $val);
    }

    public function setEmailRequired($val)
    {
        $this->setSetting('breqemail', $val);
    }

    public function setTimeOffset($val)
    {
        // check validity of value
        // 1. replace , by . (common mistake)
        $val = str_replace(',', '.', $val);
        // 2. cast to float or int
        if (is_numeric($val) && str_contains($val, '.5')) {
            $val = (float)$val;
        } else {
            $val = (int)$val;
        }

        $this->setSetting('btimeoffset', $val);
    }

    public function setDefaultCategory($val)
    {
        $this->setSetting('bdefcat', $val);
    }

    public function existsSetting($key)
    {
        return isset($this->settings[$key]);
    }

    public function getSetting($key)
    {
        if (isset($this->settings[$key])) {
            return $this->settings[$key];
        }

        return '';
    }

    public function getSettingDefault($key, $dafalutvalue)
    {
        if (! isset($this->settings[$key])) {
            return $dafalutvalue;
        }

        return $this->settings[$key];
    }

    public function setSetting($key, $value)
    {
        $this->settings[$key] = $value;
    }

    /**
     * Tries to add a member to the team.
     * Returns false if the member was already on the team
     */
    public function addTeamMember($tmember, $tadmin)
    {
        global $manager;

        $tmember = (int)$tmember;
        $tadmin  = (int)$tadmin;

        // check if member is already a member
        $tmem = MEMBER::createFromID($tmember);

        if ($tmem->isTeamMember($this->getID())) {
            return 0;
        }

        $param = [
            'blog'   => &$this,
            'member' => &$tmem,
            'admin'  => &$tadmin,
        ];
        $manager->notify('PreAddTeamMember', $param);

        // add to team
        $ph            = [];
        $ph['tmember'] = $tmember;
        $ph['tblog']   = $this->getID();
        $ph['tadmin']  = $tadmin ? 1 : 0;
        $query
                       = parseQuery(
                           'INSERT INTO [@prefix@]team (tmember, tblog, tadmin) VALUES([@tmember@], [@tblog@], [@tadmin@])',
                           $ph
                       );
        sql_query($query);

        $param = [
            'blog'   => &$this,
            'member' => &$tmem,
            'admin'  => $tadmin,
        ];
        $manager->notify('PostAddTeamMember', $param);

        $logMsg = sprintf(
            _TEAM_ADD_NEWTEAMMEMBER,
            $tmem->getDisplayName(),
            $tmember,
            $this->getName()
        );
        ACTIONLOG::add(INFO, $logMsg);

        return 1;
    }

    public function getID()
    {
        return intVal($this->blogid);
    }

    /**
     * Checks if a blog with a given shortname exists
     * Returns true if there is a blog with the given shortname (static)
     *
     * @param $name
     *     blog shortname
     */
    public static function exists($name)
    {
        $ph['bshortname'] = sql_quote_string($name);
        $query
                          = parseQuery(
                              'SELECT count(*) AS result FROM [@prefix@]blog WHERE bshortname=[@bshortname@] LIMIT 1',
                              $ph
                          );

        return (int)quickQuery($query) > 0;
    }

    /**
     * Checks if a blog with a given id exists
     * Returns true if there is a blog with the given ID (static)
     *
     * @param $id
     *     blog id
     */
    public static function existsID($bnumber)
    {
        $ph['bnumber'] = (int)$bnumber;
        $query
                       = parseQuery(
                           'SELECT count(*) AS result FROM [@prefix@]blog WHERE bnumber=[@bnumber@] LIMIT 1',
                           $ph
                       );

        return (int)quickQuery($query) > 0;
    }

    /**
     * flag there is a future post pending
     */
    public function setFuturePost($bfuturepost = 1)
    {
        $ph['bnumber']     = $this->getID();
        $ph['bfuturepost'] = (int)$bfuturepost;
        $query
                           = parseQuery(
                               'UPDATE [@prefix@]blog SET bfuturepost=[@bfuturepost@] WHERE bnumber=[@bnumber@]',
                               $ph
                           );
        sql_query($query);
    }

    /**
     * clear there is a future post pending
     */
    public function clearFuturePost()
    {
        $this->setFuturePost(0);
    }

    /**
     * check if we should throw justPosted event
     */
    public function checkJustPosted()
    {
        global $manager;

        if ($this->settings['bfuturepost'] != 1) {
            return;
        }

        $ph['iblog'] = $this->getID();
        $sql
                     = parseQuery(
                         'SELECT count(*) AS result FROM [@prefix@]item WHERE iposted=0 AND iblog=[@iblog@] AND itime<NOW() LIMIT 1',
                         $ph
                     );
        if (! (int)quickQuery($sql)) {
            return;
        }

        // This $pinged is allow a plugin to tell other hook to the event that a ping is sent already
        // Note that the plugins's calling order is subject to thri order in the plugin list
        $pinged = false;
        $param  = [
            'blogid' => $ph['iblog'],
            'pinged' => &$pinged,
        ];
        $manager->notify('JustPosted', $param);

        // clear all expired future posts
        sql_query(parseQuery(
            'UPDATE [@prefix@]item SET iposted=1 WHERE iblog=[@iblog@] AND itime<NOW()',
            $ph
        ));

        // check to see any pending future post, clear the flag is none
        $sql
            = parseQuery(
                'SELECT count(*) AS result FROM [@prefix@]item WHERE iposted=0 AND iblog=[@iblog@] LIMIT 1',
                $ph
            );
        if ((int)quickQuery($sql)) {
            return;
        }

        $this->clearFuturePost();
    }

    /**
     * Shows the given list of items for this blog
     *
     * @param $itemarray
     *        array of item numbers to be displayed
     * @param $template
     *        String representing the template _NAME_ (!)
     * @param $highlight
     *        contains a query that should be highlighted
     * @param $comments
     *        1=show comments 0=don't show comments
     * @param $dateheads
     *        1=show dateheads 0=don't show dateheads
     * @param $showDrafts
     *        0=do not show drafts 1=show drafts
     * @param $showFuture
     *        0=do not show future posts 1=show future posts
     *
     * @returns int
     *      amount of items shown
     */
    public function readLogFromList(
        $itemarray,
        $template,
        $highlight = '',
        $comments = 1,
        $dateheads = 1,
        $showDrafts = 0,
        $showFuture = 0
    ) {
        $query = $this->getSqlItemList($itemarray, $showDrafts, $showFuture);

        return $this->showUsingQuery(
            $template,
            $query,
            $highlight,
            $comments,
            $dateheads
        );
    }

    /**
     * Returns the SQL query used to fill out templates for a list of items
     *
     * @param $itemarray
     *        an array holding the item numbers of the items to be displayed
     * @param $showDrafts
     *        0=do not show drafts 1=show drafts
     * @param $showFuture
     *        0=do not show future posts 1=show future posts
     *
     * @returns
     *      either a full SQL query, or an empty string
     * @note
     *      No LIMIT clause is added. (caller should add this if multiple pages
     *      are requested)
     */
    public function getSqlItemList($itemarray, $showDrafts = 0, $showFuture = 0)
    {
        if (! is_array($itemarray)) {
            return '';
        }
        $showDrafts = (int)$showDrafts;
        $showFuture = (int)$showFuture;
        $items      = [];
        foreach ($itemarray as $value) {
            if ((int)$value) {
                $items[] = (int)$value;
            }
        }
        if (! count($items)) {
            return '';
        }
        //$itemlist = join(',',$items);
        $i     = count($items);
        $query = '';
        foreach ($items as $inumber) {
            $query .= '('
                      . 'SELECT'
                      . ' i.inumber as itemid,'
                      . ' i.ititle as title,'
                      . ' i.ibody as body,'
                      . ' m.mname as author,'
                      . ' m.mrealname as authorname,'
                      . ' i.itime,'
                      . ' i.imore as more,'
                      . ' m.mnumber as authorid,'
                      . ' m.memail as authormail,'
                      . ' m.murl as authorurl,'
                      . ' c.cname as category,'
                      . ' i.icat as catid,'
                      . ' i.iclosed as closed';

            $query .= ' FROM [@prefix@]item as i, [@prefix@]member as m, [@prefix@]category as c'
                      . ' WHERE'
                      . ' i.iblog=' . $this->blogid
                      . ' and i.iauthor=m.mnumber'
                      . ' and i.icat=c.catid';

            if (! $showDrafts) {
                $query .= ' and i.idraft=0';
            }    // exclude drafts
            if (! $showFuture) {
                $query .= ' and i.itime<=' . mysqldate($this->getCorrectTime());
            } // don't show future items

            //$query .= ' and i.inumber IN ('.$itemlist.')';
            $query .= ' and i.inumber=' . (int)$inumber;
            $query .= ')';
            $i--;
            if ($i) {
                $query .= ' UNION ';
            }
        }

        return parseQuery($query);
    }

    public function getAuthorVisible()
    {
        return (int)$this->getSettingDefault('bauthorvisible', 1);
    }

    public function setAuthorVisible($val)
    {
        $this->setSetting('bauthorvisible', ($val ? 1 : 0));
    }

    public static function UpgardeAddColumnAuthorVisible()
    {
        if (sql_existTableColumnName(sql_table('blog'), 'bauthorvisible')) {
            return;
        }

        $query
             = parseQuery('ALTER TABLE `[@prefix@]blog` ADD COLUMN `bauthorvisible` tinyint(2) NOT NULL default 1');
        $res = sql_query($query);

        return $res !== false;
    }

    private function getAllowdTagClean()
    {
        return [
            'a',
            'b', 'big', 'blockquote', 'br',
            'caption', 'center', 'cite', 'code', 'col', 'colgroup',
            'dd', 'div', 'dl', 'dt',  'del', 'details', 'datalist ',
            'font', 'figure',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr',
            'i', 'img', 'ins',
            'label', 'li',
            'nav',
            'p', 'pre',
            'ol', 'option', 'optgroup',
            'progress',
            'q',
            's', 'span', 'summary', 'select', 'section', 'small', 'strong', 'sub', 'sup',
            'ruby', 'rp', 'rt', 'rtc',
            'table', 'tbody', 'td', 'tr', 'textarea', 'tfoot', 'th', 'thead', 'time',
            'u', 'ul',
            'picture', 'source',
            'wbr',
            //
            'strike',
        ];
        // https://developer.mozilla.org/ja/docs/Web/HTML/Element/source
    }

    public function cleanItem(&$item, $names = [])
    {
        // $item : object
        $alowed_tags = $this->getAllowdTagClean();
        if ($this->allowScriptTagInItem()) {
            $alowed_tags[] = 'script';
        }
        $lists = ['ititle', 'title', 'body', 'ibody', 'more', 'imore', 'cname', 'category'];
        if (!empty($names) && is_array($names)) {
            $lists = array_merge($lists, $names);
        }
        // todo: mrealname as authorname
        // todo: memail as authormail
        // todo: murl as authorurl
        // todo: memail as authormail

        foreach ($lists as $name) {
            if (!property_exists($item, $name)
                || null === $item->$name
                || 0 === strlen($item->$name)) {
                continue;
            }
            $xml = new DOMDocument();
            libxml_use_internal_errors(true);
            $xml_dec    = '<'.'?xml version="1.0" encoding="UTF-8" ?'.'>';
            $mark_start = sprintf('@-%s-@', md5('start'.((string)time())));
            $mark_end   = sprintf('@-%s-@', md5('end'.((string)time())));
            if (_CHARSET !== 'UTF-8') {
                // PHP[8.2] Deprecated: mb_convert_encoding(): Handling HTML entities via mbstring
                $src = mb_convert_encoding(strtr($item->$name, ['<%' => $mark_start, '%>' => $mark_end]), 'HTML-ENTITIES', _CHARSET);
            } else {
                $src = $xml_dec . strtr($item->$name, ['<%' => $mark_start, '%>' => $mark_end]);
            }

            $options = LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET | LIBXML_NOWARNING;
            if ($xml->loadHTML($src, $options)) {
                $errors   = libxml_get_errors();
                $modified = (count($errors) > 0) || (preg_match('/[<>]/i', $item->$name));

                foreach ($xml->getElementsByTagName("*") as $tag) {
                    if (!in_array($tag->tagName, $alowed_tags)) {
                        $modified = true;
//                        $tag->parentNode->removeChild($tag);
                        // escape text
                        $replacement = $xml->createTextNode($xml->saveHTML($tag));
                        $tag->parentNode->replaceChild($replacement, $tag);
                        continue;
                    }
                    if (!$this->allowScriptEventAttributeInItem()) {
                        foreach ($tag->attributes as $attr) {
                            // remove tags attribute
                            if (preg_match('/^on/i', strtolower($attr->nodeName))) {
                                $modified = true;
                                $tag->removeAttribute($attr->nodeName);
                            }
                        }
                    }
                }

                if ($modified) {
                    $item->$name = strtr($xml->saveHTML(), [$mark_start => '<%', $mark_end => '%>']);
                    // remove XML Declaration tag
                    $m = [];
                    if (preg_match('/^<\\?xml\s[^>]+>(.*)$/is', $item->$name, $m)) {
                        $item->$name = (string)$m[1];
                    } elseif (str_starts_with($item->$name, $xml_dec)) {
                        // preg_match bug? sometimes no hit
                        // retry remove
                        $item->$name = substr($item->$name, strlen($xml_dec));
                    }
                    $item->$name = preg_replace_callback(
                        '|&#([0-9]+);|',
                        function ($m) {
                            $i     = (int)$m[1];
                            $flags = ENT_SUBSTITUTE;
                            if ($i === 0) {
                                return '';
                            }
                            if ($i <= 255) {  // < > &
                                return $m[0]; // do nothing
                            }
                            // convert encording and decode htmlentity
                            $ch = html_entity_decode($m[0], $flags, _CHARSET); //mb_chr($i, 'UTF-8');
                            if ((false === $ch)
                                || ($ch === '?' && $i !== ord('?'))
                                || ($ch === 'U+FFFD' || $ch === ord('&#FFFD;')) // ENT_SUBSTITUTE
                            ) {
                                return $m[0]; // do nothing
                            }
                            return $ch;
                        },
                        $item->$name
                    );
                }
//                var_dump(hsc($item->$name), $item->$name);
                if (isDebugMode() && ! $this->allowScriptTagInItem() && preg_match('/<\\?xml\s|<script\s/i', $item->$name, $m)) {
                    $msg = sprintf("%s:Line:%d : %s<br />%s\n", basename(__FILE__), __LINE__, $name, hsc($item->$name));
                    trigger_error($msg, E_USER_ERROR);
                }
            } else {
                $item->$name = strtr($xml->saveHTML(), ['<' => '&lt;', '>' => '&gt;']);
            }
        }
    }
}
