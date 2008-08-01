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
 * By default, when you include this file csrf-magic will automatically check
 * and exit if the CSRF token is invalid. This will defer executing
 * csrf_check() until you're ready. You can also pass false as a parameter to
 * that function, in which case the function will not exit but instead return
 * a boolean false if the CSRF check failed. This allows for tighter integration
 * with your system.
 */
$GLOBALS['csrf']['defer'] = false;

/**
 * Callback function to execute when there's the CSRF check fails and
 * $fatal == true (see csrf_check). This will usually output an error message
 * about the failure.
 */
$GLOBALS['csrf']['callback'] = 'csrf_callback';

/**
 * Whether or not to include our JavaScript library which also rewrites
 * AJAX requests on this domain. Set this to the web path. This setting only works
 * with supported JavaScript libraries in Internet Explorer; see README.txt for
 * a list of supported libraries.
 */
$GLOBALS['csrf']['rewrite-js'] = false;

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
 */
$GLOBALS['csrf']['allow-ip'] = true;

/**
 * If this information is available, set this to a unique identifier (it
 * can be an integer or a unique username) for the current "user" of this
 * application. The token will then be globally valid for all of that user's
 * operations, but no one else. This requires that 'secret' be set.
 */
$GLOBALS['csrf']['user'] = false;

/**
 * This is an arbitrary secret value associated with the user's session. This
 * will most probably be the contents of a cookie, as an attacker cannot easily
 * determine this information. Warning: If the attacker knows this value, they
 * can easily spoof a token. This is a generic implementation; sessions should
 * work in most cases.
 *
 * Why would you want to use this? Lets suppose you have a squid cache for your
 * website, and the presence of a session cookie bypasses it. Let's also say
 * you allow anonymous users to interact with the website; submitting forms
 * and AJAX. Previously, you didn't have any CSRF protection for anonymous users
 * and so they never got sessions; you don't want to start using sessions either,
 * otherwise you'll bypass the Squid cache. Setup a different cookie for CSRF
 * tokens, and have Squid ignore that cookie for get requests, for anonymous
 * users. (If you haven't guessed, this scheme was(?) used for MediaWiki).
 */
$GLOBALS['csrf']['key'] = false;

/**
 * The name of the magic CSRF token that will be placed in all forms, i.e.
 * the contents of <input type="hidden" name="$name" value="CSRF-TOKEN" />
 */
$GLOBALS['csrf']['input-name'] = '__csrf_magic';

/**
 * Whether or not CSRF Magic should be allowed to start a new session in order
 * to determine the key.
 */
$GLOBALS['csrf']['auto-session'] = true;

/**
 * Whether or not csrf-magic should produce XHTML style tags.
 */
$GLOBALS['csrf']['xhtml'] = true;

// FUNCTIONS:

/**
 * Rewrites <form> on the fly to add CSRF tokens to them. This can also
 * inject our JavaScript library.
 */
function csrf_ob_handler($buffer, $flags) {
    $token = csrf_get_token();
    $name = $GLOBALS['csrf']['input-name'];
    $endslash = $GLOBALS['csrf']['xhtml'] ? ' /' : '';
    $input = "<input type='hidden' name='$name' value=\"$token\"$endslash>";
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
 * @return True if check passes or is not necessary, false if failure.
 */
function csrf_check($fatal = true) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return true;
    csrf_start();
    $name = $GLOBALS['csrf']['input-name'];
    $ok = false;
    do {
        if (!isset($_POST[$name])) break;
        // we don't regenerate a token and check it because some token creation
        // schemes are volatile.
        if (!csrf_check_token($_POST[$name])) break;
        $ok = true;
    } while (false);
    if ($fatal && !$ok) {
        $callback = $GLOBALS['csrf']['callback'];
        $callback();
        exit;
    }
    return $ok;
}

/**
 * Retrieves a valid token for a particular context.
 */
function csrf_get_token() {
    $secret = csrf_get_secret();
    csrf_start();
    // These are "strong" algorithms that don't require per se a secret
    if (session_id()) return 'sid:' . sha1($secret . session_id());
    if ($GLOBALS['csrf']['key']) return 'key:' . sha1($secret . $GLOBALS['csrf']['key']);
    // These further algorithms require a server-side secret
    if ($secret === '') return 'invalid';
    if ($GLOBALS['csrf']['user'] !== false) {
        return 'user:' . sha1($secret . $GLOBALS['csrf']['user']);
    }
    if ($GLOBALS['csrf']['allow-ip']) {
        // :TODO: Harden this against proxy-spoofing attacks
        return 'ip:' . sha1($secret . $_SERVER['IP_ADDRESS']);
    }
    return 'invalid';
}

function csrf_callback() {
    echo "<html><body>CSRF check failed. Please enable cookies.</body></html>
";
}

/**
 * Checks if a token is valid.
 */
function csrf_check_token($token) {
    if (strpos($token, ':') === false) return false;
    list($type, $value) = explode(':', $token, 2);
    $secret = csrf_get_secret();
    switch ($type) {
        case 'sid':
            return $value === sha1($secret . session_id());
        case 'key':
            if (!$GLOBALS['csrf']['key']) return false;
            return $value === sha1($secret . $GLOBALS['csrf']['key']);
        // We could disable these 'weaker' checks if 'key' was set, but
        // that doesn't make me feel good then about the cookie-based
        // implementation.
        case 'user':
            if ($GLOBALS['csrf']['secret'] === '') return false;
            if ($GLOBALS['csrf']['user'] === false) return false;
            return $value === sha1($secret . $GLOBALS['csrf']['user']);
        case 'ip':
            if ($GLOBALS['csrf']['secret'] === '') return false;
            // do not allow IP-based checks if the username is set
            if ($GLOBALS['csrf']['user'] !== false) return false;
            if (!$GLOBALS['csrf']['allow-ip']) return false;
            return $value === sha1($secret . $_SERVER['IP_ADDRESS']);
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

/**
 * Retrieves the secret, and generates one if necessary.
 */
function csrf_get_secret() {
    if ($GLOBALS['csrf']['secret']) return $GLOBALS['csrf']['secret'];
    $dir = dirname(__FILE__);
    $file = $dir . '/csrf-secret.php';
    $secret = '';
    if (file_exists($file)) {
        include $file;
        return $secret;
    }
    if (is_writable($dir)) {
        for ($i = 0; $i < 32; $i++) $secret .= '\\x' . dechex(mt_rand(32, 126));
        $fh = fopen($file, 'w');
        fwrite($fh, '<?php $secret = "'.$secret.'";' . PHP_EOL);
        fclose($fh);
        return $secret;
    }
    return '';
}

// Initialize our handler
ob_start('csrf_ob_handler');
// Load user configuration
if (function_exists('csrf_startup')) csrf_startup();
// Perform check
if (!$GLOBALS['csrf']['defer']) csrf_check();
