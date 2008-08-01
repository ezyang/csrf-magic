
[[  csrf-magic  ]]

Add the following line to the top of all web-accessible PHP pages. If you have
a common file included by everything, put it there.

    include_once '/path/to/csrf-magic.php';

Do it, test it, then forget about it. csrf-magic is protecting you if nothing
bad happens. Read on if you run into problems.


1.  WHAT DOES IT DO?

CSRF, or cross-site request forgery, is a relatively new attack vector on
websites today.  It involves an attacker tricking a browser into performing
an action on another website.  For example, Bob is the human resources manager
for a large and important company.  He has the ability to hire and fire with
a click of a button... specifically, a web form button.  Mallory, as a practical
joke, decides to setup a CSRF attack against Bob; she crafts a webpage which
submits a form onto the internal website that performs hirings and firings; then
she sends Bob an email to this webpage.  The next day, every employee wakes up
to find a rather nasty pink slip in their inbox.

The current standard for preventing CSRF is creating a nonce that every user
submits with any form he/she submits.  This is reasonably effective [1], but
incredibly tedious work; if you were hand-writing your forms or have multiple
avenues for POST data to enter your application, adding CSRF protection may not
seem worth the trouble (trust me, it certainly is).

This is where csrf-magic comes into play.  csrf-magic uses PHP's output
buffering capabilities to dynamically rewrite forms and scripts in your document.
It will also intercept POST requests and check their token (various algorithms
are used, some generate nonces, some generate user-specific tokens).  This means
with a traditional website with forms, you can drop it into your application,
and forget about it!


2.  AJAX

csrf-magic has the ability to dynamically rewrite AJAX requests which use
XMLHttpRequest.  However, due to the invasiveness of this procedure, it is
not enabled by default.  You can enable it by adding this code before you
include csrf-magic.php.

    function csrf_startup() {
        csrf_conf('rewrite-js', '/web/path/to/csrf-magic.js');
    }
    // include_once '/path/to/csrf-magic.php';

(Be sure to place csrf-magic.js somewhere web accessible).  csrf-magic.js will
automatically detect and play nice with the following JavaScript frameworks:

    * jQuery
    * Prototype
    * script.aculo.us (via Prototype)
    * MooTools
    * Yahoo UI Library
    * Ext
    * Dojo

If you are not using any of these JavaScript libraries, AJAX requests will
only work for browsers with support for XmlHttpRequest.prototype (this excludes
all versions of Internet Explorer).

To rewrite your own JavaScript library to use csrf-magic.js, you should modify
your function that generates XMLHttpRequest to have this at the end:

    return new CsrfMagic(xhrObject);

With whatever xhrObject may be. If you have literal instances of XMLHttpRequest
in your code, find and replace ''new XMLHttpRequest'' with ''new CsrfMagic''
(CsrfMagic will automatically instantiate an XMLHttpRequest object in a
cross-platform manner as necessary).

If you don't want csrf-magic monkeying around with your XMLHttpRequest object,
you can manually rewrite your AJAX code to include the variable. The important
information is stored in the global variables csrfMagicName and csrfMagicToken.
CsrfMagic.process may also be of interest, as it takes one parameter, a
querystring, and prepends the CSRF token to the value.


3.  CONFIGURE

csrf-magic has some configuration options that you can set inside the
csrf_startup() function. They are described in csrf-magic.php, and you can
set them using the convenience function csrf_conf($name, $value).

For example, this is a recommended configuration:

    /**
     * This is a function that gets called if a csrf check fails. csrf-magic will
     * then exit afterwards.
     */
    function my_csrf_callback() {
        echo "You're doing bad things young man!";
    }

    function csrf_startup() {

        // This is a secret value that must be set in order to enable username
        // and IP based checks. Don't show this to anyone. A secret id will
        // automatically be generated for you if the directory csrf-magic.php
        // is placed in is writable.
        csrf_conf('secret', 'ABCDEFG123456');

        // This enables JavaScript rewriting and will ensure your AJAX calls
        // don't stop working.
        csrf_conf('rewrite-js', '/csrf-magic.js');

        // This makes csrf-magic call my_csrf_callback() before exiting when
        // there is a bad csrf token. This lets me customize the error page.
        csrf_conf('callback', 'my_csrf_callback');

        // While this is enabled by default to boost backwards compatibility,
        // for security purposes it should ideally be off. Some users can be
        // NATted or have dialup addresses which rotate frequently. Cookies
        // are much more reliable.
        csrf_conf('allow-ip', false);

    }

    // Finally, include the library
    include_once '/path/to/csrf-magic.php';

Configuration gets stored in the $GLOBALS['csrf'] array.


4.  THANKS

My thanks to Chris Shiflett, for unintentionally inspiring the idea, as well
as telling me the original variant of the Bob and Mallory story,
and the Django CSRF Middleware authors, who thought up of this before me.

http://www.thespanner.co.uk/2007/08/20/protection-against-csrf/ is interesting
esp the frame breaker which we can automatically write in.


5.  FOOTNOTES

[1] There is an experimental attack in which a user makes an invisible iframe
    of the website being attacked and overlays this on-top of an element on
    their page that a user would normally click.  This iframe has a different
    button from the other website which activates the action.  Nonces will
    not protect against this type of attack, and csrf-magic doesn't deal with
    this type of attack.

    See also:
        http://own-the.net/cat_CSRF-(XSRF)_news.html


6.   KNOWN BUGS

When an AJAX call is performed within an iframe, our rewriting of XMLHttpRequest
my fail for the first page load.  Subsequent page loads will work properly.


7.   TODO

    * Minify csrf-magic.js for performance.

    * Auto-generate secret.

    * (?) Make "first time" session more robust by double-submitting.

    * Account for JavaScript generated-forms with some JavaScript that loads into
      some global onsubmit handler and checks form submissions accordingly.
