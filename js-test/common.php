<?php

function csrf_startup() {
    csrf_conf('rewrite-js', '../csrf-magic.js');
}
require_once '../csrf-magic.php';

// Handle an AJAX request
if (isset($_POST['ajax'])) {
    header('Content-type: text/xml;charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8" ?><response>Good!</response>';
    exit;
}
