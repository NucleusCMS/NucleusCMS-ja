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
 *	This class contains two classes that can be used for importing and
 *	exporting Nucleus skins: SKINIMPORT and SKINEXPORT
 */

class SKINIMPORT
{

    // hardcoded value (see constructor). When 1, interesting info about the
    // parsing process is sent to the output
    var $debug;

    // parser/file pointer
    var $parser;
    var $fp;

    // which data has been read?
    var $metaDataRead;
    var $allRead;

    // extracted data
    var $skins;
    var $templates;
    var $info;

    // to maintain track of where we are inside the XML file
    var $inXml;
    var $inData;
    var $inMeta;
    var $inSkin;
    var $inTemplate;
    var $currentName;
    var $currentPartName;
    var $cdata;



    /**
     * constructor initializes data structures
     */
    function __construct()
    {
        // disable magic_quotes_runtime if it's turned on
        if (version_compare(PHP_VERSION, '5.3.0', '<')) {
            set_magic_quotes_runtime(0);
        }

        // debugging mode?
        $this->debug = 0;

        $this->reset();
    }

    function reset()
    {
        if ($this->parser) {
            xml_parser_free($this->parser);
        }

        // XML file pointer
        $this->fp = 0;

        // which data has been read?
        $this->metaDataRead = 0;
        $this->allRead = 0;

        // to maintain track of where we are inside the XML file
        $this->inXml = 0;
        $this->inData = 0;
        $this->inMeta = 0;
        $this->inSkin = 0;
        $this->inTemplate = 0;
        $this->currentName = '';
        $this->currentPartName = '';

        // character data pile
        $this->cdata = '';

        // list of skinnames and templatenames (will be array of array)
        $this->skins = array();
        $this->templates = array();

        // extra info included in the XML files (e.g. installation notes)
        $this->info = '';

        // init XML parser
        $this->parser = xml_parser_create();
        xml_set_object($this->parser, $this);
        xml_set_element_handler($this->parser, 'startElement', 'endElement');
        xml_set_character_data_handler($this->parser, 'characterData');
        xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, 0);
    }

/**
 * Reads an XML file into memory
 *
 * @param $filename
 *  Which file to read
 * @param $metaOnly
 *  Set to 1 when only the metadata needs to be read (optional, default 0)
 *
 * [2004/08/04] Modified by Japanese Package Release Team according to the URL below,
 *                  http://japan.nucleuscms.org/bb/viewtopic.php?t=4416
 * [2004/08/04] Modified by dakarma
 *                  Took this out since it messes up good XML if it has skins/templates
 *                  with CDATA sections. need to investigate consequences.
 *                  see bug [ 999914 ] Import fails (multiple skins in XML/one of them with CDATA)
 * [2016/05/11] Modified by piyoyo
 *                  xml_parse : parce error occured from PHP 7.0.3(to 7.0.6) and later
 *                  add readFileWithSimpleXML function
 */
    function readFile($filename, $metaOnly = 0)
    {
        // php bug (windows) : php 7.0.3 and later : xml_parse will be parce error
        if (version_compare(PHP_VERSION, '7.0.3', '>=')) {
            return $this->readFileWithSimpleXML($filename, $metaOnly);
        }

        // open file
        $this->fp = @fopen($filename, 'r');
        if (!$this->fp) {
            return _SKINIE_ERROR_FAILEDOPEN_FILEURL;
        }
        
        $this->inXml = 1;
        $tempbuffer = fread($this->fp, filesize($filename));
/*
        dakarma wrote
        // backwards compatibility with the non-wellformed skinbackup.xml files
        // generated by v2/v3 (when CDATA sections were present in skins)
        // split up those CDATA sections into multiple ones
        $tempbuffer = preg_replace_callback(
            "/(<!\[CDATA\[[^]]*?<!\[CDATA\[[^]]*)((?:\]\].*?<!\[CDATA.*?)*)(\]\])(.*\]\])/ms",
            create_function(
                '$matches',
                'return $matches[1] . preg_replace("/(\]\])(.*?<!\[CDATA)/ms","]]]]><![CDATA[$2",$matches[2])."]]]]><![CDATA[".$matches[4];'
            ),
            $tempbuffer
        );
*/
        // JP Team wrote
        if (function_exists('mb_convert_encoding') && (strtoupper(_CHARSET) != 'ISO-8859-1')) {
            mb_detect_order("ASCII, EUC-JP, UTF-8, JIS, SJIS, EUC-CN, ISO-8859-1");
            $temp_encode = mb_detect_encoding($tempbuffer);
        } else {
            $temp_encode = null;
        }
        rewind($this->fp);
        
        while (($buffer = fgets($this->fp, 4096) ) && (!$metaOnly || ($metaOnly && !$this->metaDataRead))) {
            if ($temp_encode) {
                $buffer = mb_convert_encoding($buffer, 'UTF-8', $temp_encode);
            }
            $err = xml_parse($this->parser, $buffer, feof($this->fp));
            if (!$err && $this->debug) {
                echo _ERROR . ': ' . xml_error_string(xml_get_error_code($this->parser)) . '<br />';
            }
        }
        
        $this->inXml = 0;
        fclose($this->fp);
    }

    /**
     * Returns the list of skin names
     */
    function getSkinNames()
    {
        return array_keys($this->skins);
    }

    /**
     * Returns the list of template names
     */
    function getTemplateNames()
    {
        return array_keys($this->templates);
    }

    /**
     * Returns the extra information included in the XML file
     */
    function getInfo()
    {
        return $this->info;
    }

    /**
     * Writes the skins and templates to the database
     *
     * @param $allowOverwrite
     *      set to 1 when allowed to overwrite existing skins with the same name
     *      (default = 0)
     */
    function writeToDatabase($allowOverwrite = 0)
    {
        $existingSkins = $this->checkSkinNameClashes();
        $existingTemplates = $this->checkTemplateNameClashes();

        // if not allowed to overwrite, check if any nameclashes exists
        if (!$allowOverwrite) {
            if ((sizeof($existingSkins) > 0) || (sizeof($existingTemplates) > 0)) {
                return _SKINIE_NAME_CLASHES_DETECTED;
            }
        }

        foreach ($this->skins as $skinName => $data) {
            // 1. if exists: delete all part data, update desc data
            //    if not exists: create desc
            if (in_array($skinName, $existingSkins)) {
                $skinObj = SKIN::createFromName($skinName);

                // delete all parts of the skin
                $skinObj->deleteAllParts();

                // update general info
                $skinObj->updateGeneralInfo(
                    $skinName,
                    $data['description'],
                    $data['type'],
                    $data['includeMode'],
                    $data['includePrefix']
                );
            } else {
                $skinid = SKIN::createNew(
                    $skinName,
                    $data['description'],
                    $data['type'],
                    $data['includeMode'],
                    $data['includePrefix']
                );
                $skinObj = new SKIN($skinid);
            }

            // 2. add parts
            foreach ($data['parts'] as $partName => $partContent) {
                $skinObj->update($partName, $partContent);
            }
        }

        foreach ($this->templates as $templateName => $data) {
            // 1. if exists: delete all part data, update desc data
            //    if not exists: create desc
            if (in_array($templateName, $existingTemplates)) {
                $templateObj = TEMPLATE::createFromName($templateName);

                // delete all parts of the template
                $templateObj->deleteAllParts();

                // update general info
                $templateObj->updateGeneralInfo($templateName, $data['description']);
            } else {
                $templateid = TEMPLATE::createNew($templateName, $data['description']);
                $templateObj = new TEMPLATE($templateid);
            }

            // 2. add parts
            foreach ($data['parts'] as $partName => $partContent) {
                $templateObj->update($partName, $partContent);
            }
        }
    }

    /**
      * returns an array of all the skin nameclashes (empty array when no name clashes)
      */
    function checkSkinNameClashes()
    {
        $clashes = array();

        foreach ($this->skins as $skinName => $data) {
            if (SKIN::exists($skinName)) {
                array_push($clashes, $skinName);
            }
        }

        return $clashes;
    }

    /**
      * returns an array of all the template nameclashes
      * (empty array when no name clashes)
      */
    function checkTemplateNameClashes()
    {
        $clashes = array();

        foreach ($this->templates as $templateName => $data) {
            if (TEMPLATE::exists($templateName)) {
                array_push($clashes, $templateName);
            }
        }

        return $clashes;
    }

    /**
     * Called by XML parser for each new start element encountered
     */
    function startElement($parser, $name, $attrs)
    {
        foreach ($attrs as $key => $value) {
            $attrs[$key] = hsc($value, ENT_QUOTES);
        }

        if ($this->debug) {
            echo 'START: ' . hsc($name, ENT_QUOTES) . '<br />';
        }

        switch ($name) {
            case 'nucleusskin':
                $this->inData = 1;
                break;
            case 'meta':
                $this->inMeta = 1;
                break;
            case 'info':
                // no action needed
                break;
            case 'skin':
                if (!$this->inMeta) {
                    $this->inSkin = 1;
                    $this->currentName = $attrs['name'];
                    $this->skins[$this->currentName]['type'] = $attrs['type'];
                    $this->skins[$this->currentName]['includeMode'] = $attrs['includeMode'];
                    $this->skins[$this->currentName]['includePrefix'] = $attrs['includePrefix'];
                    $this->skins[$this->currentName]['parts'] = array();
                } else {
                    $this->skins[$attrs['name']] = array();
                    $this->skins[$attrs['name']]['parts'] = array();
                }
                break;
            case 'template':
                if (!$this->inMeta) {
                    $this->inTemplate = 1;
                    $this->currentName = $attrs['name'];
                    $this->templates[$this->currentName]['parts'] = array();
                } else {
                    $this->templates[$attrs['name']] = array();
                    $this->templates[$attrs['name']]['parts'] = array();
                }
                break;
            case 'description':
                // no action needed
                break;
            case 'part':
                $this->currentPartName = $attrs['name'];
                break;
            default:
                echo _SKINIE_SEELEMENT_UNEXPECTEDTAG . hsc($name, ENT_QUOTES) . '<br />';
                break;
        }

        // character data never contains other tags
        $this->clearCharacterData();
    }

    /**
      * Called by the XML parser for each closing tag encountered
      */
    function endElement($parser, $name)
    {
        if ($this->debug) {
            echo 'END: ' . hsc($name, ENT_QUOTES) . '<br />';
        }

        switch ($name) {
            case 'nucleusskin':
                $this->inData = 0;
                $this->allRead = 1;
                break;
            case 'meta':
                $this->inMeta = 0;
                $this->metaDataRead = 1;
                break;
            case 'info':
                $this->info = $this->getCharacterData();
            case 'skin':
                if (!$this->inMeta) {
                    $this->inSkin = 0;
                }
                break;
            case 'template':
                if (!$this->inMeta) {
                    $this->inTemplate = 0;
                }
                break;
            case 'description':
                if ($this->inSkin) {
                    $this->skins[$this->currentName]['description'] = $this->getCharacterData();
                } else {
                    $this->templates[$this->currentName]['description'] = $this->getCharacterData();
                }
                break;
            case 'part':
                if ($this->inSkin) {
                    $this->skins[$this->currentName]['parts'][$this->currentPartName] = $this->getCharacterData();
                } else {
                    $this->templates[$this->currentName]['parts'][$this->currentPartName] = $this->getCharacterData();
                }
                break;
            default:
                echo _SKINIE_SEELEMENT_UNEXPECTEDTAG . hsc($name, ENT_QUOTES) . '<br />';
                break;
        }
        $this->clearCharacterData();
    }

    /**
     * Called by XML parser for data inside elements
     */
    function characterData($parser, $data)
    {
        if ($this->debug) {
            echo 'NEW DATA: ' . hsc($data, ENT_QUOTES) . '<br />';
        }
        $this->cdata .= $data;
    }

    /**
     * Returns the data collected so far
     */
    function getCharacterData()
    {
//      echo $this->cdata;
        if ((strtoupper(_CHARSET) == 'UTF-8')
            or (strtoupper(_CHARSET) == 'ISO-8859-1')
            or (!function_exists('mb_convert_encoding'))) {
            return $this->cdata;
        } else {
            return mb_convert_encoding($this->cdata, _CHARSET, 'UTF-8');
        }
    }

    /**
     * Clears the data buffer
     */
    function clearCharacterData()
    {
        $this->cdata = '';
    }

    /**
     * Static method that looks for importable XML files in subdirs of the given dir
     */
    public static function searchForCandidates($dir)
    {
        $candidates = array();

        $dirhandle = opendir($dir);
        while ($filename = readdir($dirhandle)) {
            if (@is_dir($dir . $filename) && ($filename != '.') && ($filename != '..')) {
                $xml_file = $dir . $filename . '/skinbackup.xml';
                if (file_exists($xml_file) && is_readable($xml_file)) {
                    $candidates[$filename] = $filename; //$xml_file;
                }

                // backwards compatibility
                $xml_file = $dir . $filename . '/skindata.xml';
                if (file_exists($xml_file) && is_readable($xml_file)) {
                    $candidates[$filename] = $filename; //$xml_file;
                }
            }
        }
        closedir($dirhandle);

        return $candidates;
    }

    function convValue($text)
    {
        static $flag = -1;
        if ($flag == 0) {
            return (string) $text;
        }
        if ($flag == -1) {
            if ((strtoupper(_CHARSET) == 'UTF-8')
                or (strtoupper(_CHARSET) == 'ISO-8859-1')
                or (!function_exists('mb_convert_encoding'))) {
                $flag = 0;
            } else {
                $flag = 1;
            }
        }
        if ($flag == 1) {
            return mb_convert_encoding((string) $text, _CHARSET, 'UTF-8');
        }
        return (string) $text;
    }

    function readFileWithSimpleXML($filename, $metaOnly = 0)
    {
        unset($this->skins, $this->templates);
        $this->skins = array();
        $this->templates = array();

        $src_text = @file_get_contents($filename);
        if ($src_text === false) {
            return _SKINIE_ERROR_FAILEDOPEN_FILEURL;
        }

        if (function_exists('mb_convert_encoding') && (strtoupper(_CHARSET) != 'ISO-8859-1')) {
            mb_detect_order("ASCII, EUC-JP, UTF-8, JIS, SJIS, EUC-CN, ISO-8859-1");
            $temp_encode = mb_detect_encoding($src_text);
        } else {
            $temp_encode = null;
        }

        if ((strtoupper($temp_encode) == 'UTF-8')
            or (strtoupper($temp_encode) == 'ISO-8859-1')
            or (!function_exists('mb_convert_encoding'))) {
            $xml = simplexml_load_string($src_text);
        } else {
            $xml = simplexml_load_string(mb_convert_encoding($src_text, 'UTF-8', $temp_encode));
        }
        unset($src_text);

        if ($xml === false) {
            return _SKINIE_ERROR_FAILEDLOAD_XML;
        }

        if ($metaOnly) {
            $parents = array('meta');
        } else {
            $parents = array('meta', 'skin', 'template');
        }

        $data = array();
        foreach ($parents as $parent) {
            if ('meta' == $parent) {
                if (isset($data[$parent])) {
                    continue;
                }
                $data[$parent] = array();
                $meta = $xml->xpath('/nucleusskin/meta');

                if ($meta) {
                    foreach ($meta[0] as $child) {
                        $name = $child->getName();
                        if ('info' == $name) {
                            $data[$parent][$name] = $this->convValue((string ) $child);
                            $this->info =& $data[$parent][$name];
                        } else { // skin template
                            foreach ($child->attributes() as $k => $v) {
                                if ('name' == $k) {
                                    $data[$parent][$name][] = (string ) $v;
                                }
                            }
                        }
                        if ($metaOnly) {
                            if (isset($data[$parent]['skin'])) {
                                foreach ($data[$parent]['skin'] as $v) {
                                    $this->skins[$v] = '';
                                }
                            }
                            if (isset($data[$parent]['template'])) {
                                foreach ($data[$parent]['template'] as $v) {
                                    $this->templates[$v] = '';
                                }
                            }
                        }
                    }
                }
                continue;
            }
          // skin template
            $xml_first = $xml->xpath('/nucleusskin/'.$parent);
            if (!$xml_first) {
                continue;
            }
            foreach ($xml_first as $child) {
                $item = array();
                $name = $child->getName(); // skin template
                $attributes = array();
                foreach ($child->attributes() as $k => $v) {
                    $attributes[$k] = (string ) $v;
                }
                $current_name = @$attributes['name'];

                $description = $child->xpath('description');
                $item['description'] = ($description ? $this->convValue((string ) $description[0]) : '');

                $parts = $child->xpath('part');
                foreach ($parts as $part) {
                    $attr = array();
                    foreach ($part->attributes() as $k => $v) {
                        $attr[$k] = (string ) $v;
                    }
                    $part_name = @$attr['name'];
                    $item['parts'][$part_name] = $this->convValue((string ) $part);
                }
                foreach (array('type','includeMode','includePrefix') as $a) {
                    $item[$a] = isset($attributes[$a]) ? $attributes[$a] : '';
                }

                $data[$parent][$current_name] = $item;
            }
        }

        if (!$metaOnly) {
            $this->skins =& $data['skin'];
            $this->templates =& $data['template'];
        }
        ksort($this->skins);
        ksort($this->templates);
    }
}


class SKINEXPORT
{

    var $templates;
    var $skins;
    var $info;

    /**
     * Constructor initializes data structures
     */
    function __construct()
    {
        // list of templateIDs to export
        $this->templates = array();

        // list of skinIDs to export
        $this->skins = array();

        // extra info to be in XML file
        $this->info = '';
    }

    /**
     * Adds a template to be exported
     *
     * @param id
     *      template ID
     * @result false when no such ID exists
     */
    function addTemplate($id)
    {
        if (!TEMPLATE::existsID($id)) {
            return 0;
        }


        $this->templates[$id] = TEMPLATE::getNameFromId($id);

        return 1;
    }

    /**
     * Adds a skin to be exported
     *
     * @param id
     *      skin ID
     * @result false when no such ID exists
     */
    function addSkin($id)
    {
        if (!SKIN::existsID($id)) {
            return 0;
        }

        $this->skins[$id] = SKIN::getNameFromId($id);

        return 1;
    }

    /**
     * Sets the extra info to be included in the exported file
     */
    function setInfo($info)
    {
        $this->info = $info;
    }


    /**
     * Outputs the XML contents of the export file
     *
     * @param $setHeaders
     *      set to 0 if you don't want to send out headers
     *      (optional, default 1)
     */
    function export($setHeaders = 1)
    {
        if ($setHeaders) {
            // make sure the mimetype is correct, and that the data does not show up
            // in the browser, but gets saved into and XML file (popup download window)
            header('Content-Type: text/xml');
            header('Content-Disposition: attachment; filename="skinbackup.xml"');
            header('Expires: 0');
            header('Pragma: no-cache');
        }


        // sort by skinname , templatename
        asort($this->skins);
        asort($this->templates);

        echo "<nucleusskin>\n";

        // meta
        echo "\t<meta>\n";
            // skins
        foreach ($this->skins as $skinId => $skinName) {
            $skinName = hsc($skinName, ENT_QUOTES);
            if (strtoupper(_CHARSET) != 'UTF-8') {
                $skinName = mb_convert_encoding($skinName, 'UTF-8', _CHARSET);
            }
            echo "\t\t" . '<skin name="' . hsc($skinName, ENT_QUOTES) . '" />' . "\n";
        }
            // templates
        foreach ($this->templates as $templateId => $templateName) {
            $templateName = hsc($templateName, ENT_QUOTES);
            if (strtoupper(_CHARSET) != 'UTF-8') {
                $templateName = mb_convert_encoding($templateName, 'UTF-8', _CHARSET);
            }
            echo "\t\t" . '<template name="' . hsc($templateName, ENT_QUOTES) . '" />' . "\n";
        }
            // extra info
        if ($this->info) {
            if (strtoupper(_CHARSET) != 'UTF-8') {
                $skin_info = mb_convert_encoding($this->info, 'UTF-8', _CHARSET);
            } else {
                $skin_info = $this->info;
            }
            echo "\t\t<info><![CDATA[" . $skin_info . "]]></info>\n";
        }
        echo "\t</meta>\n\n\n";

        // contents skins
        foreach ($this->skins as $skinId => $skinName) {
            $skinId   = intval($skinId);
            $skinObj  = new SKIN($skinId);
            $skinName = hsc($skinName, ENT_QUOTES);
            $contentT = hsc($skinObj->getContentType(), ENT_QUOTES);
            $incMode  = hsc($skinObj->getIncludeMode(), ENT_QUOTES);
            $incPrefx = hsc($skinObj->getIncludePrefix(), ENT_QUOTES);
            $skinDesc = hsc($skinObj->getDescription(), ENT_QUOTES);
            if (strtoupper(_CHARSET) != 'UTF-8') {
                $skinName = mb_convert_encoding($skinName, 'UTF-8', _CHARSET);
                $contentT = mb_convert_encoding($contentT, 'UTF-8', _CHARSET);
                $incMode  = mb_convert_encoding($incMode, 'UTF-8', _CHARSET);
                $incPrefx = mb_convert_encoding($incPrefx, 'UTF-8', _CHARSET);
                $skinDesc = mb_convert_encoding($skinDesc, 'UTF-8', _CHARSET);
            }

            echo "\t" . '<skin name="' . $skinName . '" type="' . $contentT . '" includeMode="' . $incMode . '" includePrefix="' . $incPrefx . '">' . "\n";

            echo "\t\t" . '<description>' . $skinDesc . '</description>' . "\n";

            $que = 'SELECT'
                 . '    stype,'
                 . '    scontent '
                 . 'FROM '
                 .      sql_table('skin')
                 . ' WHERE'
                 . '    sdesc = ' . $skinId;
            $res = sql_query($que);
            while ($partObj = sql_fetch_object($res)) {
                $type  = hsc($partObj->stype, ENT_QUOTES);
                $cdata = $this->escapeCDATA($partObj->scontent);
                if (strtoupper(_CHARSET) != 'UTF-8') {
                    $type  = mb_convert_encoding($type, 'UTF-8', _CHARSET);
                    $cdata = mb_convert_encoding($cdata, 'UTF-8', _CHARSET);
                }
                echo "\t\t" . '<part name="' . $type . '">';
                echo '<![CDATA[' . $cdata . ']]>';
                echo "</part>\n\n";
            }

            echo "\t</skin>\n\n\n";
        }

        // contents templates
        foreach ($this->templates as $templateId => $templateName) {
            $templateId   = intval($templateId);
            $templateName = hsc($templateName, ENT_QUOTES);
            $templateDesc = hsc(TEMPLATE::getDesc($templateId), ENT_QUOTES);
            if (strtoupper(_CHARSET) != 'UTF-8') {
                $templateName = mb_convert_encoding($templateName, 'UTF-8', _CHARSET);
                $templateDesc = mb_convert_encoding($templateDesc, 'UTF-8', _CHARSET);
            }

            echo "\t" . '<template name="' . $templateName . '">' . "\n";

            echo "\t\t" . '<description>' . $templateDesc . "</description>\n";

            $que =  'SELECT'
                 .     ' tpartname,'
                 .     ' tcontent'
                 . ' FROM '
                 .     sql_table('template')
                 . ' WHERE'
                 .     ' tdesc = ' . $templateId;
            $res = sql_query($que);
            while ($partObj = sql_fetch_object($res)) {
                $type  = hsc($partObj->tpartname, ENT_QUOTES);
                $cdata = $this->escapeCDATA($partObj->tcontent);
                if (strtoupper(_CHARSET) != 'UTF-8') {
                    $type  = mb_convert_encoding($type, 'UTF-8', _CHARSET);
                    $cdata = mb_convert_encoding($cdata, 'UTF-8', _CHARSET);
                }
                echo "\t\t" . '<part name="' . $type . '">';
                echo '<![CDATA[' .  $cdata . ']]>';
                echo '</part>' . "\n\n";
            }

            echo "\t</template>\n\n\n";
        }

        echo '</nucleusskin>';
    }

    /**
     * Escapes CDATA content so it can be included in another CDATA section
     */
    function escapeCDATA($cdata)
    {
        return preg_replace('/]]>/', ']]]]><![CDATA[>', $cdata);
    }
}
