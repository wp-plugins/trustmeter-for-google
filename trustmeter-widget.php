<?php
/*
Plugin Name: TrustMeter widget
Plugin URI: http://alexf.name/2007-08-20/trustmeter-for-google/
Description: See if google loves your blog or not
Author: Alex Fedorov
Version: 0.3
Author URI: http://alexf.name/
*/

function widget_trustmeter_init()
{
	// Check to see required Widget API functions are defined...
	if ( !function_exists('register_sidebar_widget') || !function_exists('register_widget_control') )
		return; // ...and if not, exit gracefully from the script.

	// This function prints the sidebar widget--the cool stuff!
	function widget_trustmeter($args)
	{
		GLOBAL $before_widget, $before_title, $after_title, $after_widget;
		// $args is an array of strings which help your widget
		// conform to the active theme: before_widget, before_title,
		// after_widget, and after_title are the array keys.
		extract($args);
		// Collect our widget's options, or define their defaults.
		$options = get_option('widget_trustmeter');
		$publ = GetParamArr($options, 'trustmeter_publ', '');
		if (('on' != $publ) and ('' != $publ) and ('off' != $publ))
		{
			// bug !!!
			$options = array();
			$options['trustmeter_publ'] = '';
		}
		$title = GetParamArr($options, 'trustmeter_title', 'TrustMeter');
		$url = GetParamArr($_SERVER, 'SERVER_NAME', '');
		$url = GetParamArr($options, 'trustmeter_url', $url);
		if ('on' != $publ)
		{
			$adm = af_is_wp_admin();
			if (!$adm)
				return;
		}
		$options = trustmeter_calc($options);

 		// It's important to use the $before_widget, $before_title,
 		// $after_title and $after_widget variables in your output.
		echo $before_widget;
		echo $before_title . GetExtLink('http://alexf.name', $title) . $after_title;
		$msg = trustmeter_draw($options);
		echo $msg;
		echo $after_widget;
	}
	
	function trustmeter_calc($options)
	{
		$maxtime = 3600 * 24 * 5;
		$url = GetParamArr($options, 'trustmeter_url', '');
		$tUpdMain = GetParamArr($options, 'tmain', 0);
		$tNow = microtime_float();
		if (($tNow - $tUpdMain) > $maxtime)
		{
			// $publ = GetParamArr($options, 'trustmeter_publ', '');
			$req = 'http://www.google.com/search?hl=en&q=' . urlencode("site:$url") . '&num=100';
			$str = file_get_contents($req);
			$x1 = SEParseGoogle($str);
			if (!$x1)
				return $options; // false
			$x1 = Text2ArrayNoEmpty($x1);
			for ($i = 0; $i < 10; $i++)
			{
				$ln = GetParamArr($x1, $i, '');
				$title = TdbGetLineParam($ln, 2);
				// $title = substr(strip_tags(stripslashes($title)), 0, 30); // !!!
				if ('' != $title)
					$title = base64_encode($title);
				$options['title_' . $i] = $title;
			}
			$options['tmain'] = $tNow;
			update_option('widget_trustmeter', $options);
			return $options; // true
		}
		for ($i = 0; $i < 10; $i++)
		{
			$tx = GetParamArr($options, 'tupd_' . $i, 0);
			if (($tNow - $tx) > $maxtime)
			{
				$options['pos_' . $i] = '?';
				$title = GetParamArr($options, 'title_' . $i, '');
				if ('' == $title)
					return $options; // false
				$title = base64_decode($title);
				$req = 'http://www.google.com/search?hl=en&q=' . urlencode($title) . '&num=100';
				$str = file_get_contents($req);
				$x2 = SEParseGoogle($str);
				if (!$x2)
					return $options; // false
				$x2 = Text2ArrayNoEmpty($x2);
				for ($gPos = 0; $gPos < 98; $gPos++)
				{
					$line = GetParamArr($x2, $gPos, '');
					$ur2 = TdbGetLineParam($line, 1);
					$bs2 = GetBaseSubdomainName2($ur2);
					if ($url != $bs2)
						continue;
					$options['pos_' . $i] = $gPos + 1;
					break;
				}
				$options['tupd_' . $i] = $tNow;
				update_option('widget_trustmeter', $options);
				return $options; // true
			}
		}
		return $options; // false
	}
	
function GetBaseSubdomainName2($val)
{
	$val = StripSubdomain($val);
	while (1 < substr_count($val, '.'))
	{
		$val = StripAfter($val, '.');
	}
	return $val;
}

function StripSubdomain($val)
{
	$val = StripAfter($val, '://');
	$val = StripBefor($val, '/');
	$val = StripBefor($val, '\\');
	$val = StripBefor($val, '?');
	$val = StripBefor($val, '&');
	$val = StripBefor($val, ':');
	$val = StripBefor($val, '#');
	$val = StripBefor($val, '~');
	$val = StripBefor($val, ',');
	$val = StripBefor($val, ' ');
	$val = StripBefor($val, '<');
	$val = StripBefor($val, '>');
	$val = StripBefor($val, '%');
	$val = strtolower($val);
	$val = urlencode($val);
	return $val;
}

function TdbGetLineParam($line, $param)
{
	$cols = explode('|', $line);
	return GetParamArr($cols, $param, '');
}

function Text2Array($str)
{
	$str = str_replace("\r", '', $str);
	$strs = explode("\n", $str);
	return $strs;
}

function Text2ArrayNoEmpty($str)
{
	$tmp = Text2Array($str);
	$str = '';
	foreach ($tmp as $key => $value)
	{
		if('' == $value)
		{
			unset($tmp[$key]);
		}
	}
	$ret = array_values($tmp);
	return $ret;
}

function SEParseGoogle($str)
{
	$res = explode('<div class=g>', $str);
	$cnt = count($res);
	if (2 > $cnt)
	{
		// DebugMsgRed("ParserError|google", TRUE);
		return FALSE;
	}
	$ret = '';
	for ($i = 1; $i < $cnt; $i++)
	{
		$str = $res[$i];
		//
		$url = StripAfter($str, 'href');
		$url = StripAfter($url, '"');
		$url = StripBefor($url, '"');
		$url = TdbNormalize($url);
		//
		$title = StripAfter($str, ')">');
		$title = StripBefor($title, '</a>');
		$title = strip_tags($title);
		$title = TdbNormalize($title);
		//
		$snip = StripAfter($str, '<font size=-1>');
		$snip = StripAfter($snip, '');
		$snip = StripBefor($snip, '<span');
		$snip = strip_tags($snip);
		$snip = TdbNormalize($snip);
		//
		$ret .= "$i|$url|$title|$snip\r\n";
	}
	return $ret;
}

function TdbNormalize($str)
{
	$str = str_replace('|', '~', $str);
	$str = str_replace("\r", '', $str);
	$str = str_replace("\n", ' ', $str);
	return $str;
}

function StripBefor($str, $subs)
{
	if ('' == $subs)
		return $str;
	$pos = strpos($str, $subs);
	if (FALSE !== $pos)
	{
		$str = substr($str, 0, $pos);
	}
	return $str;
}

function StripAfter($str, $subs)
{
	if ('' == $subs)
		return $str;
	$pos = strpos($str, $subs);
	if (FALSE !== $pos)
	{
		$str = substr($str, $pos + strlen($subs));
	}
	return $str;
}

function microtime_float() 
{
	list($usec, $sec) = explode(' ', microtime());
	$tmp = ((float)$usec + (float)$sec);
	return sprintf('%.03f', $tmp);
}

function af_is_wp_admin()
{
	$user = wp_get_current_user();
	if (0 == $user->id)
		return FALSE;
	$ad = $user->roles[0];
	if ('administrator' == $ad)
		return TRUE;
	return FALSE;
}

	function widget_trustmeter_control()
	{
		$options = get_option('widget_trustmeter');
		// This is for handing the control form submission.
		if ( $_POST['trustmeter_submit'] )
		{
			// Clean up control form submission options
			$newoptions = array();
			$newoptions['trustmeter_title'] = strip_tags(stripslashes($_POST['trustmeter_title']));
			$newoptions['trustmeter_url'] = strip_tags(stripslashes($_POST['trustmeter_url']));
			$newoptions['trustmeter_publ'] = '';
			if (isset($_POST['trustmeter_public']))
				$newoptions['trustmeter_publ'] = 'on';
			$newoptions['tmain'] = 0;
			for ($i = 0; $i < 10; $i++)
			{
				$newoptions['tupd_' . $i] = 0;
				$newoptions['title_' . $i] = '';
				$newoptions['pos_' . $i] = '?';
			}
			// If original widget options do not match control form
			// submission options, update them.
			if ($options != $newoptions)
			{
				$options = $newoptions;
				update_option('widget_trustmeter', $options);
			}
		}
		// Format options as valid HTML. Hey, why not.
		$title = htmlspecialchars($options['trustmeter_title'], ENT_QUOTES);
		if ('' == $title)
			$title = 'trustmeter';
		$url = htmlspecialchars($options['trustmeter_url'], ENT_QUOTES);
		if ('' == $url)
			$url = $_SERVER['SERVER_NAME'];
		$publ = '';
		if ('on' == $options['trustmeter_publ'])
			$publ = 'checked="checked"';

		echo '<div>';
		echo '<label for="trustmeter_title" style="line-height:35px;display:block;">Widget title: <input type="text" id="trustmeter_title" name="trustmeter_title" value="';
		echo $title . '" /></label>';
		echo '<label for="trustmeter_url" style="line-height:35px;display:block;">Domain to check: <input type="text" id="trustmeter_url" name="trustmeter_url" value="';
		echo $url . '" /></label>';
		echo '<label for="trustmeter_public">Public: <input class="checkbox" type="checkbox"';
		echo $publ .'id="trustmeter_public" name="trustmeter_public" /></label>';
		echo '<input type="hidden" name="trustmeter_submit" id="trustmeter_submit" value="1" />';
		echo '</div>';
		$url = $publ;
	// end of widget_trustmeter_control()
	}

	register_sidebar_widget('TrustMeter Widget', 'widget_trustmeter');
	register_widget_control('TrustMeter Widget', 'widget_trustmeter_control');
}

function trustmeter_draw($options)
{
	$msg = '<div align="center">';
	for ($i = 0; $i < 10; $i++)
	{
		$ttl = GetParamArr($options, 'title_' . $i, '?');
		if ('?' != $ttl)
			$ttl = base64_decode($ttl);
		$pos = GetParamArr($options, 'pos_' . $i, '?');
		$req = 'http://www.google.com/search?hl=en&q=' . urlencode($ttl) . '&num=100';
		$lnk = GetExtLink($req, $pos, $ttl, TRUE);
		$msg .= "$lnk ";
	}
	$msg .= '</div>';
	$url = GetParamArr($_SERVER, 'SERVER_NAME', '');
	$uri = GetParamArr($_SERVER, 'REQUEST_URI', '');
	$adr = GetParamArr($_SERVER, 'REMOTE_ADDR', '');
	return $msg;
}

function trustmeter_footer()
{
	$admin = dirname($_SERVER['SCRIPT_FILENAME']);
	$admin = substr($admin, strrpos($admin, '/')+1);
	if ($admin == 'wp-admin' && basename($_SERVER['SCRIPT_FILENAME']) == 'index.php')
	{
		$options = get_option('widget_trustmeter');
		$title = GetParamArr($options, 'trustmeter_title', 'TrustMeter');
		$content = "<h3>$title</h3>";
		$content .= trustmeter_draw($options);
		$content = str_replace('"', "'", $content);
		print '
<script language="javascript" type="text/javascript"> var ele = document.getElementById("zeitgeist");
if (ele)
{
var div = document.createElement("DIV");
div.innerHTML = "'.$content.'";
ele.appendChild(div);
} </script> ';
	}
}

// Delays plugin execution until Dynamic Sidebar has loaded first.
add_action('plugins_loaded', 'widget_trustmeter_init');
add_action('admin_footer', 'trustmeter_footer');

// ALEXF LIB //

function GetParamArr($arr, $strName, $strDefVal)
{
	if (isset($arr[$strName]))
	{
		return $arr[$strName];
	}
	return $strDefVal;
}

function GetExtLink($url, $txt, $title = '', $bNofollow = FALSE)
{
	$foll = '';
	if (FALSE != $bNofollow)
		$foll = ' rel="nofollow"';
	$str = "<a href=\"$url\" title=\"$title\"$foll target=\"_blank\">$txt</a>";
	return $str;
}

?>