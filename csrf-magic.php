<?php

/**
 * @file
 *
 * csrf-magic is a PHP library that makes adding CSRF-protection to your
 * web applications a snap. No need to modify every form or create a database
 * of valid nonces; just include this file at the top of every 
 * web-accessible page (or even better, your common include file included
 * in every page), and forget about it! (There are, of course, configuration
 * options for advanced users).
 * 
 * This library is PHP4 and PHP5 compatible.
 */

// CONFIGURATION:

/**
 * The name of the magic CSRF token that will be placed in all forms, i.e.
 * the contents of <input type="hidden" name="$name" value="CSRF-TOKEN" />
 */
$GLOBALS['csrf']['input-name'] = '__csrf';

/**
 * Whether or not CSRF Magic should be allowed to start a new session in order
 * to determine the key.
 */
$GLOBALS['csrf']['auto-session'] = true;

/**
 * A secret key used when hashing items. Please generate a random string and
 * place it here. If you change this value, all previously generated tokens
 * will become invalid.
 */
$GLOBALS['csrf']['secret'] = '';

/**
 * Whether or not to use IP addresses when binding a user to a token. This is
 * less reliable and less secure than sessions, but is useful when you need
 * to give facilities to anonymous users and do not wish to maintain a database
 * of valid keys.
 * @warning Not implemented yet
 */
$GLOBALS['csrf']['allow-ip'] = true;

/**
 * Whether or not to include our JavaScript library which also rewrites
 * AJAX requests on this domain. Set this to the path.
 */
$GLOBALS['csrf']['rewrite-js'] = false;

// FUNCTIONS:

/**
 * Rewrites <form> on the fly to add CSRF tokens to them. This can also
 * inject our JavaScript library.
 */
function csrf_ob_handler($buffer, $flags) {
    $token = csrf_get_token();
    $name = $GLOBALS['csrf']['input-name'];
    $input = "<input type='hidden' name='$name' value=\"$token\" />";
    $buffer = preg_replace('#(<form[^>]*method\s*=\s*["\']post["\'][^>]*>)#i', '$1' . $input, $buffer);
    if ($js = $GLOBALS['csrf']['rewrite-js']) {
        $buffer = preg_replace(
            '#(</head>)#i',
            '<script type="text/javascript">'.
                'var csrfMagicToken = "'.$token.'";'.
                'var csrfMagicName = "'.$name.'";</script>'.
            '<script src="'.$js.'" type="text/javascript"></script>$1',
            $buffer
        );
    }
    return $buffer;
}

/**
 * Checks if this is a post request, and if it is, checks if the nonce is valid.
 * @param bool $fatal Whether or not to fatally error out if there is a problem.
 */
function csrf_check($fatal = true) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    csrf_start();
    $name = $GLOBALS['csrf']['input-name'];
    $ok = false;
    do {
        if (!isset($_POST[$name])) break;
        if (!csrf_check_token($_POST[$name])) break;
        $ok = true;
    } while (false);
    if ($fatal && !$ok) {
        echo '<html><body>You failed CSRF protection</body></html>';
        exit;
    }
}

/**
 * Retrieves a valid token for a particular context.
 */
function csrf_get_token() {
    $secret = $GLOBALS['csrf']['secret'];
    csrf_start();
    if (session_id()) return 'sid:' . sha1($secret . session_id());
    // Ok, session failed, let's see if we've got an IP address
    
    // Uh-oh, you need some configuration!
    return 'invalid';
}

/**
 * Checks if a token is valid.
 */
function csrf_check_token($token) {
    if (strpos($token, ':') === false) return false;
    list($type, $value) = explode(':', $token, 2);
    $secret = $GLOBALS['csrf']['secret'];
    switch ($type) {
        case 'sid':
            return $value === sha1($secret . session_id());
        default:
            return false;
    }
    return false;
}

/**
 * Sets a configuration value.
 */
function csrf_conf($key, $val) {
    if (!isset($GLOBALS['csrf'][$key])) {
        trigger_error('No such configuration ' . $key, E_USER_WARNING);
        return;
    }
    $GLOBALS['csrf'][$key] = $val;
}

/**
 * Starts a session if we're allowed to.
 */
function csrf_start() {
    if ($GLOBALS['csrf']['auto-session'] && !session_id()) {
        session_start();
    }
}

// Initialize our handler
ob_start('csrf_ob_handler');
// Load user configuration
if (function_exists('csrf_startup')) csrf_startup();
// Perform check
csrf_check();
