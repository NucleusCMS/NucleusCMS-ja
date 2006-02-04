/**
  * Nucleus: PHP/MySQL Weblog CMS (http://nucleuscms.org/) 
  * Copyright (C) 2002-2006 The Nucleus Group
  *
  * This program is free software; you can redistribute it and/or
  * modify it under the terms of the GNU General Public License
  * as published by the Free Software Foundation; either version 2
  * of the License, or (at your option) any later version.
  * (see nucleus/documentation/index.html#license for more info)
  *
  *
  * This page contains xmlHTTPRequest functions for:
  * - AutoSaveDraft
  *
  *
  * Usage:
  * - Add in the page at the top:
  *     var xmlhttprequest = new Array();
  *     xmlhttprequest[0] = createHTTPHandler();
  *     xmlhttprequest[1] = createHTTPHandler();
  *     var seconds = now();
  *     var checks = 0;
  *     var addform = document.getElementById('addform'); // The form id
  *     var goal = document.getElementById('lastsaved'); // The html div id where 'Last saved: date time' must come
  *     var goalurl = 'index.php'; // The PHP file where the content must be posted to
  *     var lastsavedtext = 'Last saved'; // The language variable for 'Last saved'
  *
  *
  * $Id$
  */

/**
 * Creates the xmlHTTPRequest handler
 */
function createHTTPHandler() {
	var httphandler = false;
	/*@cc_on @*/
	/*@if (@_jscript_version >= 5)
		// JScript gives us Conditional compilation, we can cope with old IE versions.
		// and security blocked creation of the objects.
		try {
			httphandler = new ActiveXObject("Msxml2.XMLHTTP");
		}
		catch (e) {
			try {
				httphandler = new ActiveXObject("Microsoft.XMLHTTP");
			}
			catch (E) {
				httphandler = false;
			}
		}
	@end @*/
	if (!httphandler && typeof XMLHttpRequest != 'undefined') {
		httphandler = new XMLHttpRequest();
	}
	return httphandler;
}

/**
 * Monitors the edits
 */
function doMonitor() {
	if (checks * (now() - seconds) > 120 * 1000 * 50) {
		checks = 0;
		seconds = now();

		var title = encodeURI(addform.title.value);
		var body = encodeURI(addform.body.value);
		var catid = addform.catid.options[addform.catid.selectedindex].value;
		var more = encodeURI(addform.more.value);
		var closed = 0;
		if (addform.closed[0].checked) {
			closed = addform.closed[0].value;
		}
		else if (addform.closed[1].checked) {
			closed = addform.closed[1].value;
		}
		var ticket = addform.ticket.value;
		var blogid = addform.blogid.value;

		var querystring = 'action=autodraft';
		querystring += '&title=' + title;
		querystring += '&body=' + body;
		querystring += '&catid=' + catid;
		querystring += '&more=' + more;
		querystring += '&closed=' + closed;
		querystring += '&ticket=' + ticket;
		querystring += '&blogid=' + blogid;

		xmlhttprequest[0].open('POST', goalurl, true);
		xmlhttprequest[0].onreadystatechange = checkMonitor;
		xmlhttprequest[0].setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		xmlhttprequest[0].send(querystring);

		var querystring = 'action=updateticket&ticket=' + ticket;

		xmlhttprequest[1].open('POST', goalurl, true);
		xmlhttprequest[1].onreadystatechange = updateTicket;
		xmlhttprequest[1].setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		xmlhttprequest[1].send(querystring);
	}
	else {
		checks++;
	}
}

/**
 * Checks the process of the saving
 */
function checkMonitor() {
	if (xmlhttprequest[0].readyState == 4) {
		if (xmlhttprequest[0].responseText == 'updated') {
			goal.innerHTML = '<p>' + lastsavedtext + ': ' + formattedDate() + '</p>';
		}
		else {
			goal.innerHTML = '<p>' + xmlhttprequest.responseText + ' (' + formattedDate() + ')</p>';
		}
	}
}

/**
 * Checks the process of the ticket updating
 */
function updateTicket() {
	if (xmlhttprequest[1].readyState == 4) {
		if (xmlhttprequest[1].responseText) {
			addform.ticket.value = xmlhttprequest[1].responseText;
		}
	}
}

/**
 * Gets now in milliseconds
 */
function now() {
	var now = new Date();
	return now.getTime();
}

/**
 * Gets now in the local dateformat
 */
function formattedDate() {
	var now = new Date();
	return now.toLocaleDateString() + ' ' + now.toLocaleTimeString();
}