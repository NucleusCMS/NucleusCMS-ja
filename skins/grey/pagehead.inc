<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<title><%blogsetting(name)%></title>

<!-- some meta information (search engines might read this) -->
<meta name="generator" content="<%version%>" />
<meta name="description" content="<%blogsetting(desc)%>" />

<!-- stylesheet definition (points to the place where colors -->
<!-- and layout is defined -->
<link rel="stylesheet" type="text/css" href="<%skinfile(default.css)%>" />

<!-- alternative versions (rss/atom feeds) -->
<link rel="alternate" type="application/rss+xml" title="RSS" href="<%blogurl%>xml-rss2.php" />

<!-- extra navigational links -->
<link rel="bookmark" title="Nucleus" href="http://nucleuscms.org/" />
<link rel="archives" title="Archives" href="<%archivelink%>" />
<link rel="top" title="Today" href="<%todaylink%>" />

</head>
<body>

<!-- this is a normally hidden link, included for accessibility reasons -->
<a href="#navigation" class="skip">Jump to navigation</a>

<div class="contents">