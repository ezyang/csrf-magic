<?php require_once 'common.php'; ?>
<!DOCTYPE html 
     PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
     "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<title>jQuery test page for csrf-magic</title>
<script src="jquery-1.2.6.js" type="text/javascript"></script>
</head>
<body>
<h1>jQuery test page for csrf-magic</h1>
<textarea id="js-output" cols="80" rows="10"></textarea>
<script type="text/javascript">
//<![CDATA[
    var callback = function (data) {
        document.getElementById('js-output').value = data;
    }
    jQuery.post('jquery.php', 'ajax=yes&foo=bar', callback, 'text');
//]]>
</script>
</body>
</html>
