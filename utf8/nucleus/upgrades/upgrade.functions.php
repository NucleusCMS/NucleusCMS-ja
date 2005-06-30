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
	  * Some functions common to all upgrade scripts
	  *
  	  * $Id: upgrade.functions.php,v 1.5 2005-03-19 07:20:50 kimitake Exp $
	  * $NucleusJP: upgrade.functions.php,v 1.4 2005/03/18 06:07:10 kimitake Exp $
	  */

	include('../../config.php');
	
	// sql_table function did not exists in nucleus <= 2.0
	if (!function_exists('sql_table'))
	{
		function sql_table($name) {
			return 'nucleus_' . $name;
		}
	}	

	function upgrade_checkinstall($version) {
		$installed = 0;

		switch($version) {
			case '95':
				$query = 'SELECT bconvertbreaks FROM '.sql_table('blog').' LIMIT 1';
				$minrows = -1;
				break;
			case '96':
				$query = 'SELECT cip FROM '.sql_table('comment').' LIMIT 1';
				$minrows = -1;			
				break;
			case '10':
				$query = 'SELECT mcookiekey FROM '.sql_table('member').' LIMIT 1';
				$minrows = -1;			
				break;			
			case '11':
				$query = 'SELECT bnotifytype FROM '.sql_table('blog').' LIMIT 1';
				$minrows = -1;			
				break;
			case '15':
				$query = 'SELECT * FROM '.sql_table('plugin_option').' LIMIT 1';
				$minrows = -1;			
				break;			
			case '20':
				$query = 'SELECT sdincpref FROM '.sql_table('skin_desc').' LIMIT 1';
				$minrows = -1;			
				break;				
			// dev only (v2.2)
			case '22':
				$query = 'SELECT oid FROM '.sql_table('plugin_option_desc').' LIMIT 1';
				$minrows = -1;			
				break;
			// v2.5 beta
			case '24':
				$query = 'SELECT bincludesearch FROM ' . sql_table('blog') . ' LIMIT 1';
				$minrows = -1;			
				break;				
			case '25':
				$query = 'SELECT * FROM '.sql_table('config').' WHERE name=\'DatabaseVersion\' and value >= 250 LIMIT 1';
				$minrows = 1;
				break;
			case '30':
				$query = 'SELECT * FROM '.sql_table('config').' WHERE name=\'DatabaseVersion\' and value >= 300 LIMIT 1';
				$minrows = 1;
				break;
			case '31':
				$query = 'SELECT * FROM '.sql_table('config').' WHERE name=\'DatabaseVersion\' and value >= 310 LIMIT 1';
				$minrows = 1;
				break;
			case '32':
				$query = 'SELECT * FROM '.sql_table('config').' WHERE name=\'DatabaseVersion\' and value >= 320 LIMIT 1';
				$minrows = 1;
				break;
		}

		$res = mysql_query($query);
		$installed = ($res != 0) && (mysql_num_rows($res) >= $minrows);

		return $installed;
	}
	
	
	/** this function gets the nucleus version, even if the getNucleusVersion
	 * function does not exist yet
	 * return 96 for all versions < 100
	 */
	function upgrade_getNucleusVersion() {
		if (!function_exists('getNucleusVersion')) return 96;
		return getNucleusVersion();
	}
	
	function upgrade_showLogin($type) {
		upgrade_head();
	?>
		<h1>まずはログインして下さい</h1>
		<p>下記の情報を入力して下さい:</p>
		
		<form method="post" action="<?php echo $type?>">

			<ul>
				<li>名前: <input name="login" /></li>
				<li>パスワード <input name="password" type="password" /></li>
			</ul>

			<p>
				<input name="action" value="login" type="hidden" />
				<input type="submit" value="ログイン" />
			</p>
		
		</form>
	<?php		upgrade_foot();
		exit;
	}
	
	function upgrade_head() {
	?>
			<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
			<html xmlns="http://www.w3.org/1999/xhtml">
			<head>
				<meta http-equiv="content-type" content="application/xhtml+xml; charset=UTF-8" />
				<title>Nucleus アップグレード</title>
				<style><!--
					@import url('../styles/manual.css');
					.warning {
						color: red;
					}
					.ok {
						color: green;
					}
				--></style>
			</head>
			<body>		
	<?php	}

	function upgrade_foot() {
	?>
			</body>
			</html>	
	<?php	}	
	
	function upgrade_error($msg) {
		upgrade_head();
		?>
		<h1>エラー!</h1>

		<p>メッセージは以下の通り:</p>
		
		<blockquote><div>
		<?php echo $msg?>
		</div></blockquote>

		<p><a href="index.php" onclick="history.back();">戻る</a></p>
		<?php
		upgrade_foot();
		exit;
	}
	
	
	function upgrade_start() {
		global $upgrade_failures;
		$upgrade_failures = 0;
		
		upgrade_head();
		?>
		<h1>アップグレードの実行</h1>
		<ul>
		<?php	}
	
	function upgrade_end($msg = "") {
		global $upgrade_failures;
		if ($upgrade_failures > 0)
			$msg = "いくつかのデータベース操作に失敗しました。もし以前にこのアップグレードスクリプトを実行していたのであれば、問題ないと思われます。";
	
		?>
		</ul>
		
		<h1>アップグレード完了!</h1>

		<p><?php echo $msg?></p>
		
		<p><a href="index.php">アップグレード最初のページ</a>にもどる</p>

		<?php
		upgrade_foot();
		exit;
	}	
	
	/**
	  * Tries to execute a query, gives a message when failed
	  *
	  * @param friendly name
	  * @param query		
	  */
	function upgrade_query($friendly, $query) {
		global $upgrade_failures;
		
		echo "<li>$friendly ... ";
		$res = mysql_query($query);
		if (!$res) {
			echo "<span style='color:red'>失敗</span>\n";
			echo "<blockquote>失敗の理由: " . mysql_error() . " </blockquote>";
			$upgrade_failures++;
		} else {
			echo "<span style='color:green'>成功!</span><br />\n";
		}
		echo "</li>";
		return $res;
	}
	
	/**
	 * @param $table 
	 *		table to check (without prefix)
	 * @param $aColumns
	 *		array of column names included
	 */
	function upgrade_checkIfIndexExists($table, $aColumns) {
		// get info for indices from database
		
		$aIndices = array();
		$query = 'show index from ' . sql_table($table);
		$res = mysql_query($query);
		while ($o = mysql_fetch_object($res)) {
			if (!$aIndices[$o->Key_name]) {
				$aIndices[$o->Key_name] = array();
			}
			array_push($aIndices[$o->Key_name], $o->Column_name);
		}

		// compare each index with parameter
		foreach ($aIndices as $keyName => $aIndexColumns) {
			$aDiff = array_diff($aIndexColumns, $aColumns);
			if (count($aDiff) == 0) return 1;
		}
		
		return 0;

	}



?>