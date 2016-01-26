<?php
require_once('include/load.php');
header("X-Powered-By: Freakazoid!");
function html_obfuscate($text) {
    $return = '';
    $text = str_split($text);
    foreach($text as $chr) {
        $return.= '&#x'.dechex(ord($chr)).';';
    }
    return $return;
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head><title><?php echo SITE_NAME;?> - make it short</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<link rel="stylesheet" type="text/css" href="style.css"/>
<script type="text/javascript" src="include/script.js"></script>
</head><body><table>
<tr><td colspan="3" id="header"><div align="center">
<a href="<?php echo SITE_URL; ?>"><img src="<?php echo SITE_IMAGE; ?>" alt="<?php echo SITE_NAME; ?>" /></a><h1><a href="<?php echo SITE_URL; ?>">Buuuh!</a></h1><br/>
</div>
</td></tr>
<tr><td class="corner-topleft"></td><td class="border-top"></td><td class="corner-topright"></td></tr>
<tr><td class="border-left"></td><td class="content"><form method="get" enctype="multipart/form-data" action="api.php" onsubmit="return submitURL()">
<strong>URL:</strong> <input type="text" id="input-url" name="url"<?php if (!empty($_GET['url'])) echo ' value="'.$_GET['url'].'"'; ?>/><input type="hidden" name="out" value="noscript"/><input type="submit" id="input-submit" value="Buuuh! it" />
<div id="alias-switch"><script type="text/javascript">
//<![CDATA[
document.write('<img id="switch-img" src="img/alias-show.png" alt="" onclick="toggleAlias()"/>');
//]]>
</script></div><br />
<div id="alias">Custom alias (optional): <?php echo SITE_URL; ?> <input type="text" id="input-alias" name="alias"<?php if (!empty($_GET['alias'])) echo ' value="'.$_GET['alias'].'"'; ?>/>
<br/><span class="small">Alias may contain both uppercase and lowercase letters, numbers, dashes and underscores.</span><script type="text/javascript">
//<![CDATA[
document.getElementById('alias').style.display = "none";
//]]>
</script></div>
<div id="result">
<?php if (isset($_GET['rc'])) {
	if ($_GET['rc'] === '0' && isset($_GET['code'])) {
		echo '<div id="success"><div>Your URL was successfully shortened.</div><br/><input type="text" value="'.SITE_URL.'/'.$_GET['code'].'" readonly="readonly"/> <a href="'.SITE_URL.'/'.$_GET['code'].'" target="_blank"><img src="img/external.png" alt=""/></a> <a href="qr.php?alias='.$_GET['code'].'" target="_blank">QR code</a>';
	} else if ($_GET['rc'] < 9) {
		echo '<div id="failure"><div>'.$errors[$_GET['rc']].'</div></div>';
    }
} ?>
</div></form></td><td class="border-right"></td></tr>
<tr><td class="corner-bottomleft"></td><td class="border-bottom"></td><td class="corner-bottomright"></td></tr></table>
<div id="stats"><br /><strong>Quick stats:</strong>
<p>&nbsp;&nbsp;shortened <strong><?php echo code_count(); ?></strong> URLs with a total of <strong><?php echo prettify_numbers(clicks_count()); ?></strong> clicks until now... keep shorting and clicking!</p>
Top 5 visited links:<ol>
<?php
$top5 = top_clicks();
foreach ((array)$top5 as $res) {
    echo '<li><a href="'.SITE_URL.'/'.$res->code.'" target="_blank">'.SITE_URL.'/'.$res->code.'</a> got '.$res->clicks.' clicks.</li>';
}
?>
</ol></div><br /><br />
<div id="footer">&copy; 2009-2014, 2015-<?php date_default_timezone_set('UTC'); echo date('Y') ?> <?php echo SITE_NAME; ?> (written and maintained by <a href="<?php echo html_obfuscate('mailto:robert@linux-source.org'); ?>">Robert Milasan</a>)</div>
</body></html>
