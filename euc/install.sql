CREATE TABLE `nucleus_actionlog` (
  `timestamp` datetime NOT NULL default '0000-00-00 00:00:00',
  `message` varchar(255) NOT NULL default ''
) TYPE=MyISAM;

CREATE TABLE `nucleus_activation` (
  `vkey` varchar(40) NOT NULL default '',
  `vtime` datetime NOT NULL default '0000-00-00 00:00:00',
  `vmember` int(11) NOT NULL default '0',
  `vtype` varchar(15) NOT NULL default '',
  `vextra` varchar(128) NOT NULL default '',
  PRIMARY KEY  (`vkey`)
) TYPE=MyISAM;

CREATE TABLE `nucleus_ban` (
  `iprange` varchar(15) NOT NULL default '',
  `reason` varchar(255) NOT NULL default '',
  `blogid` int(11) NOT NULL default '0'
) TYPE=MyISAM;

CREATE TABLE `nucleus_blog` (
  `bnumber` int(11) NOT NULL auto_increment,
  `bname` varchar(60) NOT NULL default '',
  `bshortname` varchar(15) NOT NULL default '',
  `bdesc` varchar(200) default NULL,
  `bcomments` tinyint(2) NOT NULL default '1',
  `bmaxcomments` int(11) NOT NULL default '0',
  `btimeoffset` decimal(3,1) NOT NULL default '0.0',
  `bnotify` varchar(60) default NULL,
  `burl` varchar(100) default NULL,
  `bupdate` varchar(60) default NULL,
  `bdefskin` int(11) NOT NULL default '1',
  `bpublic` tinyint(2) NOT NULL default '1',
  `bsendping` tinyint(2) NOT NULL default '0',
  `bconvertbreaks` tinyint(2) NOT NULL default '1',
  `bdefcat` int(11) default NULL,
  `bnotifytype` int(11) NOT NULL default '15',
  `ballowpast` tinyint(2) NOT NULL default '0',
  `bincludesearch` tinyint(2) NOT NULL default '0',
  `breqemail` TINYINT( 2 ) DEFAULT '0' NOT NULL,
  PRIMARY KEY  (`bnumber`),
  UNIQUE KEY `bnumber` (`bnumber`),
  UNIQUE KEY `bshortname` (`bshortname`)
) TYPE=MyISAM;

INSERT INTO `nucleus_blog` VALUES (1, 'My Nucleus CMS', 'mynucleuscms', '', 1, 0, 0.0, '', 'http://localhost:8080/nucleus/', '', 5, 1, 0, 1, 1, 1, 1, 0, 0);

CREATE TABLE `nucleus_category` (
  `catid` int(11) NOT NULL auto_increment,
  `cblog` int(11) NOT NULL default '0',
  `cname` varchar(200) default NULL,
  `cdesc` varchar(200) default NULL,
  PRIMARY KEY  (`catid`)
) TYPE=MyISAM;

INSERT INTO `nucleus_category` VALUES (1, 1, 'General', 'Items that do not fit in other categories');

CREATE TABLE `nucleus_comment` (
  `cnumber` int(11) NOT NULL auto_increment,
  `cbody` text NOT NULL,
  `cuser` varchar(40) default NULL,
  `cmail` varchar(100) default NULL,
  `cemail` VARCHAR( 100 ),
  `cmember` int(11) default NULL,
  `citem` int(11) NOT NULL default '0',
  `ctime` datetime NOT NULL default '0000-00-00 00:00:00',
  `chost` varchar(60) default NULL,
  `cip` varchar(15) NOT NULL default '',
  `cblog` int(11) NOT NULL default '0',
  PRIMARY KEY  (`cnumber`),
  UNIQUE KEY `cnumber` (`cnumber`),
  KEY `citem` (`citem`),
  FULLTEXT KEY `cbody` (`cbody`)
) TYPE=MyISAM;

CREATE TABLE `nucleus_config` (
  `name` varchar(20) NOT NULL default '',
  `value` varchar(128) default NULL,
  PRIMARY KEY  (`name`)
) TYPE=MyISAM;

INSERT INTO `nucleus_config` VALUES ('DefaultBlog', '1');
INSERT INTO `nucleus_config` VALUES ('AdminEmail', 'example@example.org');
INSERT INTO `nucleus_config` VALUES ('IndexURL', 'http://localhost:8080/nucleus/');
INSERT INTO `nucleus_config` VALUES ('Language', 'japanese-euc');
INSERT INTO `nucleus_config` VALUES ('SessionCookie', '');
INSERT INTO `nucleus_config` VALUES ('AllowMemberCreate', '');
INSERT INTO `nucleus_config` VALUES ('AllowMemberMail', '1');
INSERT INTO `nucleus_config` VALUES ('SiteName', 'My Nucleus CMS');
INSERT INTO `nucleus_config` VALUES ('AdminURL', 'http://localhost:8080/nucleus/nucleus/');
INSERT INTO `nucleus_config` VALUES ('NewMemberCanLogon', '1');
INSERT INTO `nucleus_config` VALUES ('DisableSite', '');
INSERT INTO `nucleus_config` VALUES ('DisableSiteURL', 'http://www.this-page-intentionally-left-blank.org/');
INSERT INTO `nucleus_config` VALUES ('LastVisit', '');
INSERT INTO `nucleus_config` VALUES ('MediaURL', 'http://localhost:8080/nucleus/media/');
INSERT INTO `nucleus_config` VALUES ('AllowedTypes', 'jpg,jpeg,gif,mpg,mpeg,avi,mov,mp3,swf,png');
INSERT INTO `nucleus_config` VALUES ('AllowLoginEdit', '');
INSERT INTO `nucleus_config` VALUES ('AllowUpload', '1');
INSERT INTO `nucleus_config` VALUES ('DisableJsTools', '2');
INSERT INTO `nucleus_config` VALUES ('CookiePath', '/');
INSERT INTO `nucleus_config` VALUES ('CookieDomain', '');
INSERT INTO `nucleus_config` VALUES ('CookieSecure', '');
INSERT INTO `nucleus_config` VALUES ('CookiePrefix', '');
INSERT INTO `nucleus_config` VALUES ('MediaPrefix', '1');
INSERT INTO `nucleus_config` VALUES ('MaxUploadSize', '1048576');
INSERT INTO `nucleus_config` VALUES ('NonmemberMail', '');
INSERT INTO `nucleus_config` VALUES ('PluginURL', 'http://localhost:8080/nucleus/nucleus/plugins/');
INSERT INTO `nucleus_config` VALUES ('ProtectMemNames', '1');
INSERT INTO `nucleus_config` VALUES ('BaseSkin', '5');
INSERT INTO `nucleus_config` VALUES ('SkinsURL', 'http://localhost:8080/nucleus/skins/');
INSERT INTO `nucleus_config` VALUES ('ActionURL', 'http://localhost:8080/nucleus/action.php');
INSERT INTO `nucleus_config` VALUES ('URLMode', 'normal');
INSERT INTO `nucleus_config` VALUES ('DatabaseVersion', '330');

CREATE TABLE `nucleus_item` (
  `inumber` int(11) NOT NULL auto_increment,
  `ititle` varchar(160) default NULL,
  `ibody` text NOT NULL,
  `imore` text,
  `iblog` int(11) NOT NULL default '0',
  `iauthor` int(11) NOT NULL default '0',
  `itime` datetime NOT NULL default '0000-00-00 00:00:00',
  `iclosed` tinyint(2) NOT NULL default '0',
  `idraft` tinyint(2) NOT NULL default '0',
  `ikarmapos` int(11) NOT NULL default '0',
  `icat` int(11) default NULL,
  `ikarmaneg` int(11) NOT NULL default '0',
  PRIMARY KEY  (`inumber`),
  UNIQUE KEY `inumber` (`inumber`),
  KEY `itime` (`itime`),
  FULLTEXT KEY `ibody` (`ibody`,`ititle`,`imore`)
) TYPE=MyISAM PACK_KEYS=0;

INSERT INTO `nucleus_item` VALUES (1, 'Nucleus CMS �С������3.3�ؤ褦����',
'�����֥ڡ����κ�������������Ѥ��ڤ������ˤ���ޤ�������Ͽ�����blog�ˤʤ뤫�⤷��ޤ��󤷡��Ѥ��Τ��¤ޤ����²�Υڡ����ˤʤ뤫�⤷��ޤ��󤷡��¤�¿����̣�Υ����Ȥˤʤ뤫�⤷��ޤ��󡣤��뤤�ϸ��ߤΤ��ʤ��ˤ��������Ĥ��ʤ���Τˤʤ뤳�Ȥ��äƤ���Ǥ��礦��<br />\r\n<br />\r\n���Ӥ��פ��Ĥ��ޤ���Ǥ�������������ʤ餳�����������Ǥ����ʤ��ʤ餢�ʤ�Ʊ�ͻ䤿���ˤ�狼��ʤ��ΤǤ����顣',
'����ϥ����Ȥˤ�����ǽ�Υ���ȥ꡼�Ǥ����������Ȥ��ڤ�䤹���褦�ˡ���󥯤Ⱦ��������Ƥ����ޤ�����<br />\r\n<br />\r\n���ε����������뤳�Ȥ�Ǥ��ޤ������ɤ���ˤ��赭�����ɲä��Ƥ������Ȥˤ�äƤ䤬�ƥᥤ��ڡ�������ϸ����ʤ��ʤ�ޤ���Nucleus�򰷤����������������򥳥��ȤȤ����ɲä������襢�������Ǥ���褦�ˤ��Υڡ�����֥å��ޡ������Ƥ����Τ��Ǥ���<br />\r\n<br />\r\n<b>���</b><br />\r\n<br />\r\nNucleus CMS��<a href="http://nucleuscms.org">�ܲ�</a>��<a href="http://japan.nucleuscms.org">���ܸ����</a>�ڡ�����<br />\r\n<br />\r\nNucleus CMS��SourceForge<a href="http://sourceforge.net/projects/nucleuscms/">�ץ���������</a>��<a href="http://sourceforge.jp/projects/nucleus-jp/">������</a>�˥ڡ�����<br />\r\n<br />\r\nNucleus CMS�Υץ饰����<a href="http://wakka.xiffy.nl/Plugin/">�Ҹ�</a>��<a href="http://japan.nucleuscms.org/wiki/plugins">���ܸ�Υꥹ��</a>�ڡ�����<br />\r\n<br />\r\n<b>�ɥ������ - <a href="http://docs.nucleuscms.org/">docs.nucleuscms.org</a></b><br />\r\n<br />\r\nNucleus��<a href="http://japan.nucleuscms.org/faq.php">FAQ�ʤ褯������佸��</a>��<a href="http://nucleuscms.org/faq.php">��ʸ</a>�˥ڡ�����<br />\r\n<br />\r\n���󥹥ȡ�����ˡ����<a href="nucleus/documentation/">�桼��������</a>��<a href="nucleus/documentation/devdocs/">��ȯ�Ը���</a>ʸ�񤬥ե�����˴ޤޤ�Ƥ��ޤ���<br />\r\n<br />\r\n�ݥåץ��å�<a href="./nucleus/documentation/help.html">�إ��</a>���������ꥢ�Τ�����Ȥ����ˤ��ꡢ�����ȤΥ������ޥ�����ǥ�������������Ƥ���뤳�ȤǤ��礦��<br />\r\n<br />\r\n�����Ѱդ���Ƥ���ɥ�����Ȥ��ܤ��̤����顢<a href="http://wiki.nucleuscms.org/">Wiki</a>��<a href="http://japan.nucleuscms.org/wiki/">������</a>�ˤ�ˬ��Ƥ����������桼�����ν񤤤��ϥ��ġ��侮�����Ǻܤ���Ƥ��ޤ���<br />\r\n<br />\r\n<b>���ݡ���</b><br />\r\n<br />\r\n<a href="http://forum.nucleuscms.org/">forum.nucleuscms.org</a>���ܲȡ�<br />\r\n<a href="http://japan.nucleuscms.org/bb/">japan.nucleuscms.org/bb/</a>�������ǡ�<br />\r\n<br />\r\n<a href="http://forum.nucleuscms.org/groupcp.php?g=3">moderators</a>�ȥ��ݡ��ȥե������ǳ�ư�������ƤΥܥ��ƥ����˴��դ��ޤ���<br />\r\n<br />\r\n- <a href="http://edmondhui.homeip.net/blog/">admun</a> - Ottawa, ON, Canada 	  	<br />\r\n- <a href="http://www.tamizhan.com/">anand</a> - Bangalore, India<br />\r\n- <a href="http://hcgtv.com">hcgtv</a> - Miami, Florida, USA<br />\r\n- <a href="http://www.adrenalinsports.nl/">ikeizer</a> - Maastricht<br />\r\n- <a href="http://www.tipos.com.br/">moraes</a> - Brazil<br />\r\n- <a href="http://roelg.nl/">roel </a>- The Netherlands<br />\r\n- <a href="http://budts.be/weblog/">TeRanEX </a>- Ekeren, Antwerp, Belgium<br />\r\n- <a href="http://www.trentadams.com/">Trent </a>- Alberta, Canada<br />\r\n- <a href="http://xiffy.nl/weblog/">xiffy </a>- Deventer<br />\r\n<br />\r\n�⤷�������ɬ�פʤ顢1400��Ķ������Ͽ�桼�����Τ���䤿���Υե������˻��ä��Ƥ���������23,000��Ķ������Ƥ��줿�����򸡺��Ǥ���褦�ˤʤäƤ���ޤ��Τǡ����������˿���Υ���å��Ǥ��ɤ��失�뤫�⤷��ޤ���<br />\r\n<br />\r\n<b>Personalization - <a href="http://skins.nucleuscms.org/">skins.nucleuscms.org</a></b><br />\r\n<br />\r\n�ޥ�������֥����ȥ�����/�ƥ�ץ졼�Ȥ��Ȥ߹�碌�϶��Ϥ������̤����߽Ф��ޤ����Ŀ�Ū�ʥ����Ⱥ�����ͧ�ͤ���̤��뤤�ϥ��饤����Ȥ��Ф��륵���ȥǥ����󤤤�����Ф��Ƥ�Ǥ���<br />\r\n<br />\r\n636����Ͽ���줿<a href="http://nucleuscms.org/sites.php">Nucleus�Ǳ��Ѥ���Ƥ��륵����</a>��<a href="http://japan.nucleuscms.org/sites.php">������</a>�ˤ��椫���ÿ����륵���Ȥ򥵥�ץ�Ȥ��Ƥ��Ҳ𤷤ޤ���<br />\r\n<br />\r\nThe Zen of Nucleus<br />\r\n- <a href="http://beefcake.nl/">beefcake.nl</a> - Beefcake | Nuke the whales!<br />\r\n- <a href="http://www.leng-lui.com//">leng-lui.com</a> - Leng-Lui.com - v7.0: "Memento"<br />\r\n<br />\r\nPersonal blogs<br />\r\n- <a href="http://bloggard.com/">bloggard.com</a> - The Adventures of Bloggard<br />\r\n- <a href="http://battleangel.org/">battleangel.org</a> - Giving meaning to the meaningless<br />\r\n- <a href="http://www.yetanotherblog.de/">yetanotherblog.de</a> - Yet Another Blog<br />\r\n<br />\r\nMulti user blogs<br />\r\n- <a href="http://tipos.com.br/">tipos.com.br</a> - Blogging community<br />\r\n<br />\r\nHobby, Travel and News sites<br />\r\n- <a href="http://adrenalinsports.nl/">adrenalinsports.nl</a> - Extreme sports<br />\r\n- <a href="http://hsbluebird.com/">hsbluebird.com</a> - Hot Springs, Montana''s Online Resource <br />\r\n- <a href="http://groningen-info.de/">groningen-info.de</a> - Neues aus Groningen. Fr Leute aus Duitsland.<br />\r\n- <a href="http://www.americandaily.com/">americandaily.com</a> - American Daily - Home<br />\r\n<br />\r\n<b>Nucleus Developer Network - <a href="http://dev.nucleuscms.org/">dev.nucleuscms.org</a></b><br />\r\n<br />\r\nThe NUDN is a hub for developer sites and programming resources.<br />\r\n<br />\r\nNUDN satellite sites, handles, location and UTC offset:<br />\r\n- <a href="http://karma.nucleuscms.org/">karma</a> - Izegem +02<br />\r\n- <a href="http://hcgtv.net/">hcgtv</a> - Miami -05<br />\r\n- <a href="http://edmondhui.homeip.net/blog/nudn.php">admun</a> - Ottawa -04<br />\r\n- <a href="http://dev.budts.be/nucleus/">TeRanEX</a> - Ekeren +02<br />\r\n<br />\r\nSourceforge.net graciously hosts our <a href="http://sourceforge.net/projects/nucleuscms/">CVS repository</a>.<br />\r\n<br />\r\nWant to play around or test changes, visit our demo site at <a href="http://demo.nucleuscms.org/">demo.nucleuscms.org</a>.<br />\r\n<br />\r\nNot sure what plugins to use, visit the <a href="http://showcase.trentadams.com/">showcase site</a> where you can see plugins at play in their native habitat.<br />\r\n<br />\r\nThen visit the plugin repository at <a href="http://plugins.nucleuscms.org/">plugins.nucleuscms.org</a> for download and installation instructions.<br />\r\n<br />\r\n<b>���ռ԰���</b><br />\r\n<br />\r\n�ʲ���<a href="http://nucleuscms.org/donators.php">�����餷���͡�</a>�ˤ��<a href="http://nucleuscms.org/donate.php">���</a>���դ������ޤ���<em>���꤬�Ȥ���</em><br />\r\n<br />\r\n- <a href="http://reddustrec.net/">dkex</a><br />\r\n- <a href="http://www.uncoverthenet.com/">Uncover the Net</a><br />\r\n- <a href="http://www.webatlas.org/">Web Atlas</a><br />\r\n- <a href="http://www.ipnlighting.com/">IPN Lighting</a><br />\r\n- <a href="http://blog.datoka.jp/">Yu (blog.datoka.jp)</a><br />\r\n- <a href="http://www.thegadgetreview.com/">Sony Gadgets and Reviews</a><br />\r\n- <a href="http://sites.proliphus.com/blueZhift/blog/">Thomas McKibben</a><br />\r\n- <a href="http://cheapweb.us/">CheapWeb.us</a><br />\r\n- Robert Seyfriedsberger<br />\r\n- <a href="http://www.toxicologie.nl/">Toxicologie.nl</a><br />\r\n- Gordon Shum<br />\r\n- <a href="http://www.subsim.com/">Neal Stevens</a><br />\r\n- <a href="http://www.GamblingHelper.com/">GamblingHelper</a><br />\r\n- Oliver Kirstein<br />\r\n- <a href="http://www.dominiek.be/">Dominiek</a><br />\r\n- <a href="http://www.aardschok.net/">Aardschok</a><br />\r\n- <a href="http://www.nieuwevoordeur.be/">nieuwevoordeur.be</a><br />\r\n- <a href="http://www.scene24.net/">Scene24</a><br />\r\n- <a href="http://www.eug.be/">Eug''s Weblog</a><br />\r\n- <a href="http://www.bloggard.com/">The Adventures of Bloggard</a><br />\r\n- <a href="http://www.voltos.com/">Arthur Cronos from Voltos</a><br />\r\n- <a href="http://www.webmaster-toolkit.com/">Free Webmaster Tools and Resources</a><br />\r\n- <a href="http://www.domilog.be/">Domi''s Weblog</a><br />\r\n- Infodoma		<br />\r\n- <a href="http://carvingcode.com/">carvingCode.com</a><br />\r\n- <a href="http://www.traweb.com/">Traweb</a><br />\r\n- <a href="http://gene.mm2u.com/">Gene''s MoBlog</a><br />\r\n- <a href="http://interfacethis.com/">InterfaceThis</a><br />\r\n- <a href="http://www.thefinsters.com/flog/">The Finster Log</a><br />\r\n- <a href="http://www.mrhop.com/">Hop Nguyen</a><br />\r\n- <a href="http://www.zwavel.com/~zwavelaars" title="Zwavelaars">Zwavelaars</a><br />\r\n- <a href="http://beefcake.nl/">Joaquin Scholten</a>	<br />\r\n- <a href="http://www.roelgroeneveld.com/">Roel Groeneveld</a><br />\r\n- <a href="http://lvb.net/">LVBlog</a><br />\r\n- <a href="http://xandermol.com/">Xander Mol</a><br />\r\n- Danilo Massa<br />\r\n- <a href="http://01FTP.com/">01FTP.com</a><br />\r\n- <a href="http://www.adrenalinsports.nl/">Irmo Keizer</a><br />\r\n- <a href="http://www.jasonkrogh.com/">Jason Krogh</a><br />\r\n- <a href="http://www.higuchi.com/">Osamu Higuchi</a><br />\r\n- <a href="http://www.trentadams.com/">Trent Adams</a><br />\r\n- <a href="http://www.ppcw.net/">Arne Hess</a><br />\r\n- <a href="http://hsbluebird.com/">The Bluebird</a><br />\r\n- Rainer Bickel<br />\r\n- Fritz Elfers<br />\r\n- <a href="http://www.european-wall-tapestries.com/">European Wall Tapestries</a><br />\r\n- <a href="http://www.jamier.net/">Jamie R. Rytlewski</a><br />\r\n- Madolyn Piper<br />\r\n- <a href="http://www.batteryvalues.com/">Battery Values</a><br />\r\n- <a href="http://www.mixburnrip.de/">Janko Roettgers</a><br />\r\n- Lukas Loesche<br />\r\n- <a href="http://www.seobook.com/">SEO Book</a><br />\r\n- <a href="http://www.brandweerdematen.nl/">Brandweer de Maten</a><br />\r\n- Andy Fuchs<br />\r\n- <a href="http://www.sumoforce.com/">Sumoforce</a><br />\r\n- <a href="http://love.silverindigo.com/">Al''ky''mie</a><br />\r\n- <a href="http://www.pejo.us/">Peter Johnson</a><br />\r\n- <a href="http://www.triv.nl/">TriV Internet Solutions</a><br />\r\n- <a href="http://www.torontomusicians.org/nucleus/">Margaret Stowe</a><br />\r\n- <a href="http://www.zenkey.org/">zenkey dot org</a><br />\r\n- <a href="http://www.golb.org/">Blots of Info</a><br />\r\n- <a href="http://www.zonderpartij.be/">Rudi De Kerpel</a><br />\r\n- <a href="http://staylorx.com/">Steve Taylor</a><br />\r\n- <a href="http://lmhcave.com/">Malcolm Farnsworth</a><br />\r\n- Birgit Kellner<br />\r\n- <a href="http://www.tobiasly.com/">Toby Johnson</a><br />\r\n- <a href="http://www.kapingamarangi.be/">Kapingamarangi</a><br />\r\n- <a href="http://www.pallalink.net/">Pallalink</a><br />\r\n- <a href="http://publiustx.net/">PubliusTX Weblog</a><br />\r\n- <a href="http://www.reductioadabsurdum.net/">Reductio Ad Absurdum</a><br />\r\n- <a href="http://www.gagaweb.org/">GagaWeb</a><br />\r\n- <a href="http://www.videokid.be/">Videokid</a><br />\r\n- Jon Marr<br />\r\n- <a href="http://www.docblog.org/">Luigi Cristiano</a><br />\r\n- J Keith Lehman<br />\r\n- Bohemian Cachet<br />\r\n- Jesus Mourazos<br />\r\n- <a href="http://ltp-design.com/">Stephen Jones</a><br />\r\n- <a href="http://oha.nu/">One-Handed Apps</a><br />\r\n- Alwin Hawkins<br />\r\n- <a href="http://jstigall.bloomington.in.us">Justin Stigall</a><br />\r\n- <a href="http://www.itismylife.com/">It is my life</a><br />\r\n- Greg Morrill<br />\r\n- <a href="http://www.dutchsubmarines.com/">Dutch Submarines</a><br />\r\n- <a href="http://www.7thwatch.com/">Seventh Watch Design Studios</a>		<br />\r\n- <a href="http://www.macnet2.com/">MacNetv2</a>	<br />\r\n- Richard Noordhof<br />\r\n- <a href="http://www.jamier.net/">Jamie Rytlewski</a><br />\r\n<br />\r\nNucleus����������ޤ���������<a href="http://www.hotscripts.com/Detailed/13368.html?RID=nucleus@demuynck.org">HotScripts</a>��<a href="http://www.opensourcecms.com/index.php?option=content&task=view&id=145">opensourceCMS</a>�Ǥ���ɼ�򤪴ꤤ���ޤ���<br />\r\n<br />\r\n<b>�饤����</b><br />\r\n<br />\r\n�䤿�����ե꡼�����եȥ������ˤĤ��Ƹ��ˤ�����ϼ�ͳ�Τ��Ȥ˸��ڤ��Ƥ���ΤǤ��äơ����ʤΤ��ȤǤϤ���ޤ��󡣻䤿����<a href="http://www.gnu.org/licenses/gpl.html">���̸�ͭ���ѵ�����</a>��<a href="http://www.gnu.org/licenses/gpl.ja.html">���ܸ���</a>��<a href="http://www.atmarkit.co.jp/aig/03linux/gpl.html">����</a>�ˤϡ��ե꡼�����եȥ�������ʣ��ʪ��ͳ�����ۤǤ��뤳��(�����ơ�˾��ʤ餳�Υ����ӥ����Ф����в�������Ǥ��뤳��)���������������ɤ�ºݤ˼�����뤫��˾��������������ꤹ�뤳�Ȥ���ǽ�Ǥ��뤳�ȡ����ꤷ�����եȥ��������ѹ������꿷�����ե꡼���ץ������ΰ����Ȥ��ƻ��ѤǤ��뤳�ȡ��ʾ�γ����Ƥ�Ԥʤ����Ȥ��Ǥ���Ȥ������Ȥ�桼�����Ȥ��ΤäƤ��뤳�Ȥ�¸��Ǥ���褦�˥ǥ����󤵤�Ƥ��ޤ���', 1, 1, '2005-02-16 22:57:54', 0, 0, 0, 1, 0);

CREATE TABLE `nucleus_karma` (
  `itemid` int(11) NOT NULL default '0',
  `ip` char(15) NOT NULL default ''
) TYPE=MyISAM;

CREATE TABLE `nucleus_member` (
  `mnumber` int(11) NOT NULL auto_increment,
  `mname` varchar(16) NOT NULL default '',
  `mrealname` varchar(60) default NULL,
  `mpassword` varchar(40) NOT NULL default '',
  `memail` varchar(60) default NULL,
  `murl` varchar(100) default NULL,
  `mnotes` varchar(100) default NULL,
  `madmin` tinyint(2) NOT NULL default '0',
  `mcanlogin` tinyint(2) NOT NULL default '1',
  `mcookiekey` varchar(40) default NULL,
  `deflang` varchar(20) NOT NULL default '',
  PRIMARY KEY  (`mnumber`),
  UNIQUE KEY `mname` (`mname`),
  UNIQUE KEY `mnumber` (`mnumber`)
) TYPE=MyISAM;

INSERT INTO `nucleus_member` VALUES (1, 'example', 'example', '1a79a4d60de6718e8e5b326e338ae533', 'example@example.org', 'http://localhost:8080/nucleus/', '', 1, 1, 'd767aefc60415859570d64c649257f19', '');

CREATE TABLE `nucleus_plugin` (
  `pid` int(11) NOT NULL auto_increment,
  `pfile` varchar(40) NOT NULL default '',
  `porder` int(11) NOT NULL default '0',
  PRIMARY KEY  (`pid`),
  KEY `pid` (`pid`),
  KEY `porder` (`porder`)
) TYPE=MyISAM;

CREATE TABLE `nucleus_plugin_event` (
  `pid` int(11) NOT NULL default '0',
  `event` varchar(40) default NULL,
  KEY `pid` (`pid`)
) TYPE=MyISAM;

CREATE TABLE `nucleus_plugin_option` (
  `ovalue` text NOT NULL,
  `oid` int(11) NOT NULL auto_increment,
  `ocontextid` int(11) NOT NULL default '0',
  PRIMARY KEY  (`oid`,`ocontextid`)
) TYPE=MyISAM;

CREATE TABLE `nucleus_plugin_option_desc` (
  `oid` int(11) NOT NULL auto_increment,
  `opid` int(11) NOT NULL default '0',
  `oname` varchar(20) NOT NULL default '',
  `ocontext` varchar(20) NOT NULL default '',
  `odesc` varchar(255) default NULL,
  `otype` varchar(20) default NULL,
  `odef` text,
  `oextra` text,
  PRIMARY KEY  (`opid`,`oname`,`ocontext`),
  UNIQUE KEY `oid` (`oid`)
) TYPE=MyISAM;

CREATE TABLE `nucleus_skin` (
  `sdesc` int(11) NOT NULL default '0',
  `stype` varchar(20) NOT NULL default '',
  `scontent` text NOT NULL,
  PRIMARY KEY  (`sdesc`,`stype`)
) TYPE=MyISAM;

INSERT INTO `nucleus_skin` VALUES (2, 'index', '<?xml version="1.0" encoding="UTF-8"?>\n\n<feed xml:lang="ja" xmlns="http://www.w3.org/2005/Atom\">\n    <title><%blogsetting(name)%></title>\n    <subtitle><%blogsetting(desc)%></subtitle>\n    <id><%blogsetting(url)%>:<%blogsetting(id)%></id>\n\n    <link rel="alternate" type="text/html" href="<%blogsetting(url)%>" />\n    <link rel="self" type="application/atom+xml" href="<%blogsetting(url)%><%self%>" />\n    <generator uri="http://nucleuscms.org/"><%version%></generator>\n    <updated><%blog(feeds/atom/modified,1)%></updated>\n\n    <%blog(feeds/atom/entries,10)%>\n</feed>');
INSERT INTO `nucleus_skin` VALUES (4, 'index', '<?xml version="1.0"?>\r\n<rsd version="1.0">\r\n <service>\r\n  <engineName><%version%></engineName>\r\n  <engineLink>http://nucleuscms.org/</engineLink>\r\n  <homepageLink><%sitevar(url)%></homepageLink>\r\n  <apis>\r\n   <api name="MetaWeblog" preferred="true" apiLink="<%adminurl%>xmlrpc/server.php" blogID="<%blogsetting(id)%>">\r\n    <docs>http://nucleuscms.org/documentation/devdocs/xmlrpc.html</docs>\r\n   </api>\r\n   <api name="Blogger" preferred="false" apiLink="<%adminurl%>xmlrpc/server.php" blogID="<%blogsetting(id)%>">\r\n    <docs>http://nucleuscms.org/documentation/devdocs/xmlrpc.html</docs>\r\n   </api>\r\n  </apis>\r\n </service>\r\n</rsd>');
INSERT INTO `nucleus_skin` VALUES (3, 'index', '<?xml version="1.0" encoding="UTF-8"?>\r\n<rss version="2.0">\r\n  <channel>\r\n    <title><%blogsetting(name)%></title>\r\n    <link><%blogsetting(url)%></link>\r\n    <description><%blogsetting(desc)%></description>\r\n    <language>ja</language>\r\n    <generator><%version%></generator>\r\n    <copyright>&#169;</copyright>\r\n    <category>Weblog</category>\r\n    <docs>http://backend.userland.com/rss</docs>\r\n    <image>\r\n      <url><%adminurl%>nucleus2.gif</url>\r\n      <title><%blogsetting(name)%></title>\r\n      <link><%blogsetting(url)%></link>\r\n    </image>\r\n    <%blog(feeds/rss20,10)%>\r\n  </channel>\r\n</rss>');

CREATE TABLE `nucleus_skin_desc` (
  `sdnumber` int(11) NOT NULL auto_increment,
  `sdname` varchar(20) NOT NULL default '',
  `sddesc` varchar(200) default NULL,
  `sdtype` varchar(40) NOT NULL default 'text/html',
  `sdincmode` varchar(10) NOT NULL default 'normal',
  `sdincpref` varchar(50) NOT NULL default '',
  PRIMARY KEY  (`sdnumber`),
  UNIQUE KEY `sdname` (`sdname`),
  UNIQUE KEY `sdnumber` (`sdnumber`)
) TYPE=MyISAM;

INSERT INTO `nucleus_skin_desc` VALUES (2, 'feeds/atom', 'Atom 1.0 weblog syndication', 'application/atom+xml', 'normal', '');
INSERT INTO `nucleus_skin_desc` VALUES (3, 'feeds/rss20', 'RSS 2.0 syndication of weblogs', 'text/xml', 'normal', '');
INSERT INTO `nucleus_skin_desc` VALUES (4, 'xml/rsd', 'RSD (Really Simple Discovery) information for weblog clients', 'text/xml', 'normal', '');
INSERT INTO `nucleus_skin_desc` VALUES (5, 'default', 'Nucleus CMS default skin', 'text/html', 'skindir', 'default/');

CREATE TABLE `nucleus_team` (
  `tmember` int(11) NOT NULL default '0',
  `tblog` int(11) NOT NULL default '0',
  `tadmin` tinyint(2) NOT NULL default '0',
  PRIMARY KEY  (`tmember`,`tblog`)
) TYPE=MyISAM;

INSERT INTO `nucleus_team` VALUES (1, 1, 1);

CREATE TABLE `nucleus_template` (
  `tdesc` int(11) NOT NULL default '0',
  `tpartname` varchar(20) NOT NULL default '',
  `tcontent` text NOT NULL,
  PRIMARY KEY  (`tdesc`,`tpartname`)
) TYPE=MyISAM;

INSERT INTO `nucleus_template` VALUES (3, 'ITEM', '<item>\r\n <title><%title(xml)%></title>\r\n <link><%blogurl%>?itemid=<%itemid%></link>\r\n<description><![CDATA[<%body%><%more%>]]></description>\r\n <category><%category%></category>\r\n<comments><%blogurl%>?itemid=<%itemid%></comments>\r\n <pubDate><%date(rfc822)%></pubDate>\r\n</item>');
INSERT INTO `nucleus_template` VALUES (3, 'EDITLINK', '<a href="<%editlink%>" onclick="<%editpopupcode%>">edit</a>');
INSERT INTO `nucleus_template` VALUES (3, 'FORMAT_DATE', '%Y-%m-%d');
INSERT INTO `nucleus_template` VALUES (3, 'FORMAT_TIME', '%H:%M:%S');
INSERT INTO `nucleus_template` VALUES (4, 'ITEM', '<%date(utc)%>');
INSERT INTO `nucleus_template` VALUES (5, 'ITEM', '<entry>\r\n <title type="html"><![CDATA[<%title%>]]></title>\r\n <link rel="alternate" type="text/html" href="<%blogurl%>?itemid=<%itemid%>" />\r\n <author>\r\n  <name><%author%></name>\r\n </author>\r\n <updated><%date(utc)%></updated>\r\n <published><%date(iso8601)%></published>\r\n <content type="html"><![CDATA[<%body%><%more%>]]></content>\r\n <id><%blogurl%>:<%blogid%>:<%itemid%></id>\r\n</entry>');

INSERT INTO `nucleus_template` VALUES (5, 'POPUP_CODE', '<%media%>');
INSERT INTO `nucleus_template` VALUES (5, 'IMAGE_CODE', '<%image%>');
INSERT INTO `nucleus_template` VALUES (5, 'MEDIA_CODE', '<%media%>');
INSERT INTO `nucleus_template` VALUES (3, 'POPUP_CODE', '<%image%>');
INSERT INTO `nucleus_template` VALUES (3, 'MEDIA_CODE', '<%media%>');
INSERT INTO `nucleus_template` VALUES (3, 'IMAGE_CODE', '<%media%>');

CREATE TABLE `nucleus_template_desc` (
  `tdnumber` int(11) NOT NULL auto_increment,
  `tdname` varchar(20) NOT NULL default '',
  `tddesc` varchar(200) default NULL,
  PRIMARY KEY  (`tdnumber`),
  UNIQUE KEY `tdnumber` (`tdnumber`),
  UNIQUE KEY `tdname` (`tdname`)
) TYPE=MyISAM;

INSERT INTO `nucleus_template_desc` VALUES (4, 'feeds/atom/modified', 'Atom feeds: Inserts last modification date');
INSERT INTO `nucleus_template_desc` VALUES (5, 'feeds/atom/entries', 'Atom feeds: Feed items');
INSERT INTO `nucleus_template_desc` VALUES (3, 'feeds/rss20', 'Used for RSS 2.0 syndication of your blog');
INSERT INTO `nucleus_template_desc` VALUES (8, 'default/index', 'Nucleus CMS default index template');
INSERT INTO `nucleus_template_desc` VALUES (9, 'default/item', 'Nucleus CMS default item template');

CREATE TABLE `nucleus_tickets` (
  `ticket` varchar(40) NOT NULL default '',
  `ctime` datetime NOT NULL default '0000-00-00 00:00:00',
  `member` int(11) NOT NULL default '0',
  PRIMARY KEY  (`ticket`,`member`)
) TYPE=MyISAM;