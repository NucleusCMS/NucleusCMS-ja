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
 */

include('upgrade.functions.php');

load_upgrade_lang();

// check if logged in etc
if (!$member->isLoggedIn()) {
    upgrade_showLogin('upgrade.php?from=' . intGetVar('from'));
}

if (!$member->isAdmin()) {
    upgrade_error(_UPG_TEXT_ONLY_SUPER_ADMIN);
}

$from = intGetVar('from');

upgrade_start();

switch($from) {
    case 300:
        include_once('upgrade3.1.php');
        upgrade_do310();
        // no break
    case 310:
        include_once('upgrade3.2.php');
        upgrade_do320();
        // no break
    case 320:
        include_once('upgrade3.3.php');
        upgrade_do330();
        // no break
    case 330:
        include_once('upgrade3.31.php');
        upgrade_do331();
        // no break
    case 331:
        include_once('upgrade3.4.php');
        upgrade_do340();
        // no break
    case 340:
        include_once('upgrade3.5.php');
        upgrade_do350();
        // no break
    case 350:
        include_once('upgrade3.6.php');
        upgrade_do360();
        // no break
    case 360:
        include_once('upgrade3.7.php');
        upgrade_do370();
        // no break
    case 370:
        include_once('upgrade3.7.php');
        upgrade_do371();
        break;
    default:
        echo "<li>" . _UPG_TEXT_ERROR_NO_UPDATES_TO_EXECUTE . "</li>";
        break;
}

upgrade_end(_UPG_TEXT_UPGRADE_COMPLETED);
