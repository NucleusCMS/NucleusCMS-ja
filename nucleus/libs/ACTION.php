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
 * Actions that can be called via action.php
 *
 * @license http://nucleuscms.org/license.txt GNU General Public License
 * @copyright Copyright (C) The Nucleus Group
 */

class ACTION
{
    /**
     *  Constructor for an new ACTION object
     */
    public function __construct()
    {
        // do nothing
    }

    /**
     *  Calls functions that handle an action called from action.php
     */
    public function doAction($action)
    {
        switch ($action) {
            case 'autodraft':
                return $this->autoDraft();
                break;

            case 'updateticket':
                return $this->updateTicket();
                break;

            case 'addcomment':
                return $this->addComment();
                break;

            case 'sendmessage':
                return $this->sendMessage();
                break;

            case 'createaccount':
                return $this->createAccount();
                break;

            case 'forgotpassword':
                return $this->forgotPassword();
                break;

            case 'votenegative':
            case 'votepositive':
                return $this->doVote($action == 'votepositive' ? '+' : '-');
                break;

            case 'plugin':
                return $this->callPlugin();
                break;

            default:
                doError(_ERROR_BADACTION);
                break;
        }
    }

    /**
     *  Adds a new comment to an item (if IP isn't banned)
     */
    public function addComment()
    {
        global $CONF, $errormessage, $manager;

        $post['itemid']   = intPostVar('itemid');
        $post['user']     = postVar('user');
        $post['userid']   = postVar('userid');
        $post['email']    = postVar('email');
        $post['body']     = postVar('body');
        $post['remember'] = intPostVar('remember');

        // set cookies when required
        #$remember = intPostVar('remember');

        // begin if: "Remember Me" box checked
        if ($post['remember'] == 1) {
            $lifetime = time() + 2592000;
            setcookie($CONF['CookiePrefix'] . 'comment_user', $post['user'], $lifetime, '/', '', 0);
            setcookie($CONF['CookiePrefix'] . 'comment_userid', $post['userid'], $lifetime, '/', '', 0);
            setcookie($CONF['CookiePrefix'] . 'comment_email', $post['email'], $lifetime, '/', '', 0);
        } // end if

        $comments = new COMMENTS($post['itemid']);

        $blog_id = getBlogIDFromItemID($post['itemid']);
        $this->checkban($blog_id);
        $blog = & $manager->getBlog($blog_id);

        // note: PreAddComment and PostAddComment gets called somewhere inside addComment
        $errormessage = $comments->addComment($blog->getCorrectTime(), $post);

        // begin if:
        if ($errormessage == '1') {
            // redirect when adding comments succeeded
            if (postVar('url')) {
                redirect(postVar('url'));
            } else {
                $url = createItemLink($post['itemid']);
                redirect($url);
            } // end if
        } // else, show error message using default skin for blog
        else {
            return array(
                'message' => $errormessage,
                'skinid'  => $blog->getDefaultSkin()
            );
        } // end if

        exit;
    }

    /**
     *  Sends a message from the current member to the member given as argument
     */
    public function sendMessage()
    {
        global $CONF, $member;

        $error = $this->validateMessage();

        if ($error != '') {
            return array('message' => $error);
        }

        if (!$member->isLoggedIn()) {
            $fromMail = postVar('frommail');
            $fromName = _MMAIL_FROMANON;
        } else {
            $fromMail = $member->getEmail();
            $fromName = $member->getDisplayName();
        }

        $tomem = new MEMBER();
        $tomem->readFromId(postVar('memberid'));

        $message = _MMAIL_MSG . ' ' . $fromName . "\n"
            . '(' . _MMAIL_FROMNUC . ' ' . $CONF['IndexURL'] . ") \n\n"
            . _MMAIL_MAIL . " \n\n"
            . postVar('message');
        $message .= getMailFooter();

        $title = _MMAIL_TITLE . ' ' . $fromName;

        @Utils::mail($tomem->getEmail(), $title, $message, 'From: ' . $fromMail);

        if (postVar('url')) {
            redirect(postVar('url'));
        } else {
            $CONF['MemberURL'] = $CONF['IndexURL'];

            if ($CONF['URLMode'] == 'pathinfo') {
                $url = createLink('member', array('memberid' => $tomem->getID(), 'name' => $tomem->getDisplayName()));
            } else {
                $url = $CONF['IndexURL'] . createMemberLink($tomem->getID());
            }

            redirect($url);
        }

        exit;
    }

    /**
     *  Checks if a mail to a member is allowed
     *  Returns a string with the error message if the mail is disallowed
     */
    public function validateMessage()
    {
        global $CONF, $member, $manager;

        if (!$CONF['AllowMemberMail']) {
            return _ERROR_MEMBERMAILDISABLED;
        }

        if (!$member->isLoggedIn() && !$CONF['NonmemberMail']) {
            return _ERROR_DISALLOWED;
        }

        if (!$member->isLoggedIn() && (!isValidMailAddress(postVar('frommail')))) {
            return _ERROR_BADMAILADDRESS;
        }

        // let plugins do verification (any plugin which thinks the comment is invalid
        // can change 'error' to something other than '')
        $result = '';
        $param  = array(
            'type'  => 'membermail',
            'error' => &$result
        );
        $manager->notify('ValidateForm', $param);

        return $result;
    }

    /**
     *  Creates a new user account
     */
    public function createAccount()
    {
        global $CONF, $manager;

        if (!$CONF['AllowMemberCreate']) {
            doError(_ERROR_MEMBERCREATEDISABLED);
        }

        // evaluate content from FormExtra
        $result = 1;
        $param  = array(
            'type'  => 'membermail',
            'error' => &$result
        );
        $manager->notify('ValidateForm', $param);

        if ($result != 1) {
            return $result;
        } else {
            // even though the member can not log in, set some random initial password. One never knows.
            mt_srand((float)microtime() * 1000000);
            $initialPwd = md5(uniqid(mt_rand(), true));

            // create member (non admin/can not login/no notes/random string as password)
            $name = shorten(postVar('name'), 32, '');
            $r    = MEMBER::create($name, postVar('realname'), $initialPwd, postVar('email'), postVar('url'), 0, 0, '');

            if ($r != 1) {
                return $r;
            }

            // send message containing password.
            $newmem = new MEMBER();
            $newmem->readFromName($name);
            $newmem->sendActivationLink('register');

            $param = array('member' => &$newmem);
            $manager->notify('PostRegister', $param);

            if (postVar('desturl')) {
                redirect(postVar('desturl'));
            } else {
                if (!headers_sent()) {
                    sendContentType('text/html', '', _CHARSET);
                }
                echo _MSG_ACTIVATION_SENT;
                echo '<br /><br />Return to <a href="' . $CONF['IndexURL'] . '" title="' . $CONF['SiteName'] . '">' . $CONF['SiteName'] . '</a>';
                echo "\n</body>\n</html>";
            }

            exit;
        }
    }

    /**
     *  Sends a new password
     */
    public function forgotPassword()
    {
        $membername = trim(postVar('name'));

        if (!MEMBER::exists($membername)) {
            doError(_ERROR_NOSUCHMEMBER);
        }

        $mem = MEMBER::createFromName($membername);

        /* below keeps regular users from resetting passwords using forgot password feature
             Removing for now until clear why it is required.*/
        /*if (!$mem->canLogin())
            doError(_ERROR_NOLOGON_NOACTIVATE);*/

        // check if user halt or invalid
        if ($mem->isHalt()) {
            doError(_ERROR_LOGIN_MEMBER_HALT_OR_INVALID);
        }

        // check if e-mail address is correct
        if (!($mem->getEmail() == postVar('email'))) {
            doError(_ERROR_INCORRECTEMAIL);
        }

        // send activation link
        $mem->sendActivationLink('forgot');

        if (postVar('url')) {
            redirect(postVar('url'));
        } else {
            global $CONF;
            sendContentType('text/html', '', _CHARSET);
            echo _MSG_ACTIVATION_SENT;
            echo '<br /><br />Return to <a href="' . $CONF['IndexURL'] . '" title="' . $CONF['SiteName'] . '">' . $CONF['SiteName'] . '</a>';
        }

        exit;
    }

    /**
     *  Handle karma votes
     */
    public function doKarma($type)
    {
        return doVote(($type == 'pos' || $type == '+') ? '+' : '-');
    }

    /**
     *  Handle votes
     */
    public function doVote($type)
    {
        global $itemid, $member, $CONF, $manager;

        // check if itemid exists
        if (!$manager->existsItem($itemid, 0, 0)) {
            doError(_ERROR_NOSUCHITEM);
        }

        $blogid = getBlogIDFromItemID($itemid);
        $this->checkban($blogid);

        $karma         = & $manager->getKarma($itemid);
        $isVoteAllowed = $karma->isVoteAllowed(serverVar('REMOTE_ADDR'));

        $params = array('done' => false, 'type' => $type, 'allow' => &$isVoteAllowed);
        $manager->notify('PreVote', $params);

        // check if not already voted
        if (!$isVoteAllowed) {
            doError(_ERROR_VOTEDBEFORE);
        }

        // check if item does allow voting
        $item = & $manager->getItem($itemid, 0, 0);

        if ($item['closed']) {
            doError(_ERROR_ITEMCLOSED);
        }

        switch ($type) {
            case '+':
                $karma->votePositive();
                break;

            case '-':
                $karma->voteNegative();
                break;
        }

        $params = array('done' => false, 'type' => $type);
        $manager->notify('PostVote', $params);

//        $blogid = getBlogIDFromItemID($itemid);
        $blog = & $manager->getBlog($blogid);

        // send email to notification address, if any
        if ($blog->getNotifyAddress() && $blog->notifyOnVote()) {
            $mailto_msg = _NOTIFY_KV_MSG . ' ' . $itemid . "\n";
            $itemLink   = createItemLink(intval($itemid));
            $temp       = parse_url($itemLink);

            if (!$temp['scheme']) {
                $itemLink = $CONF['IndexURL'] . $itemLink;
            }

            $mailto_msg .= $itemLink . "\n\n";

            if ($member->isLoggedIn()) {
                $mailto_msg .= _NOTIFY_MEMBER . ' ' . $member->getDisplayName() . ' (ID=' . $member->getID() . ")\n";
            }

            $mailto_msg .= _NOTIFY_IP . ' ' . serverVar('REMOTE_ADDR') . "\n";
            $mailto_msg .= _NOTIFY_HOST . ' ' . gethostbyaddr(serverVar('REMOTE_ADDR')) . "\n";
            $mailto_msg .= _NOTIFY_VOTE . "\n " . $type . "\n";
            $mailto_msg .= getMailFooter();

            $mailto_title = _NOTIFY_KV_TITLE . ' ' . strip_tags($item['title']) . ' (' . $itemid . ')';

            $frommail = $member->getNotifyFromMailAddress();

            $notify = new NOTIFICATION($blog->getNotifyAddress());
            $notify->notify($mailto_title, $mailto_msg, $frommail);
        }

        $refererUrl = serverVar('HTTP_REFERER');

        if ($refererUrl) {
            $url = $refererUrl;
        } else {
//            $url = $CONF['IndexURL'] . 'index.php?itemid=' . $itemid;
            $url = $itemLink;
        }

        redirect($url);
        exit;
    }

    /**
     * Calls a plugin action
     */
    public function callPlugin()
    {
        global $manager;

        $pluginName = 'NP_' . requestVar('name');
        $actionType = requestVar('type');

        // 1: check if plugin is installed
        if (!$manager->pluginInstalled($pluginName)) {
            doError(_ERROR_NOSUCHPLUGIN);
        }

        // 2: call plugin
        $pluginObject = & $manager->getPlugin($pluginName);

        if ($pluginObject) {
            $error = $pluginObject->doAction($actionType);
        } else {
            $error = 'Could not load plugin (see actionlog)';
        }

        // doAction returns error when:
        // - an error occurred (duh)
        // - no actions are allowed (doAction is not implemented)
        if ($error) {
            doError($error);
        }

        exit;
    }

    /**
     *  Checks if an IP or IP range is banned
     */
    public function checkban($blogid)
    {
        // check if banned
        $ban = BAN::isBanned($blogid, serverVar('REMOTE_ADDR'));

        if ($ban != 0) {
            doError(_ERROR_BANNED1 . $ban->iprange . _ERROR_BANNED2 . $ban->message . _ERROR_BANNED3);
        }
    }

    /**
     * Gets a new ticket
     */
    public function updateTicket()
    {
        global $manager;

        if ($manager->checkTicket()) {
            echo $manager->getNewTicket();
        } else {
            echo _ERROR . ':' . _ERROR_BADTICKET;
        }

        return false;
    }

    /**
     * Handles AutoSaveDraft
     */
    public function autoDraft()
    {
        global $manager;

        if ($manager->checkTicket()) {
            $manager->loadClass('ITEM');
            $info = ITEM::createDraftFromRequest();

            if ($info['status'] == 'error') {
                echo $info['message'];
            } else {
                echo $info['draftid'];
            }
        } else {
            echo _ERROR . ':' . _ERROR_BADTICKET;
        }

        return false;
    }
}
