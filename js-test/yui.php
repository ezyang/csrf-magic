<?php require_once 'common.php'; ?>
<!DOCTYPE html
     PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN
     "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<title>Yahoo! UI library test page for csrf-magic</title>
<?php
$locs = array();
$locs[] = print_javascript('yui/build/yahoo/yahoo', 'http://yui.yahooapis.com/2.5.2/build/yahoo/yahoo.js');
$locs[] = print_javascript('yui/build/event/event', 'http://yui.yahooapis.com/2.5.2/build/event/event.js');
$locs[] = print_javascript('yui/build/connection/connection', 'http://yui.yahooapis.com/2.5.2/build/connection/connection.js');
?>
</head>
<body>
<h1>Yahoo! UI library test page for csrf-magic</h1>
<p>Using <?php echo implode(', ', $locs); ?></p>
<textarea id="js-output" cols="80" rows="4"></textarea>
<script type="text/javascript">
//<![CDATA[
    var textarea = document.getElementById('js-output');
    textarea.value = "YUI " + YAHOO.VERSION + "\n";
    var callback = {success: function (transport) {
        textarea.value += transport.responseText;
    }}
    var transaction = YAHOO.util.Connect.asyncRequest('POST', 'yui.php', callback, 'ajax=yes&foo=bar');
//]]>
</script>
</body>
</html>
