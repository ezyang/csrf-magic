/**
 * @file
 *
 * Rewrites XMLHttpRequest to automatically send CSRF token with it. In theory
 * plays nice with other JavaScript libraries, needs testing though.
 */

// Here are the basic overloaded method definitions
CsrfMagic = new Object;
CsrfMagic.open = function(method, url, async, username, password) {
    if (method == 'POST') this.csrf_isPost = true;
    // deal with Opera bug, thanks jQuery
    if (username) return this.csrf_open(method, url, async, username, password);
    else return this.csrf_open(method, url, async);
}
CsrfMagic.send = function(data) {
    if (!this.csrf_isPost) this.csrf_send(data);
    prepend = csrfMagicName + '=' + csrfMagicToken + '&';
    if (this.csrf_purportedLength === undefined) {
        this.csrf_setRequestHeader("Content-length", this.csrf_purportedLength + prepend.length);
        delete this.csrf_purportedLength;
    }
    delete this.csrf_isPost;
    return this.csrf_send(prepend + data);
}
CsrfMagic.setRequestHeader = function(header, value) {
    // We have to auto-set this at the end, since we don't know how long the
    // nonce is when added to the data.
    if (this.csrf_isPost && header == "Content-length") {
        this.csrf_purportedLength = value;
        return;
    }
    return this.csrf_setRequestHeader(header, value);
}

// Sets things up for Mozilla/Opera/nice browsers
if (window.XMLHttpRequest && window.XMLHttpRequest.prototype) {
    XMLHttpRequest.prototype.csrf_open = XMLHttpRequest.prototype.open;
    XMLHttpRequest.prototype.csrf_send = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.csrf_setRequestHeader = XMLHttpRequest.prototype.setRequestHeader;
    
    XMLHttpRequest.prototype.open = CsrfMagic.open;
    XMLHttpRequest.prototype.send = CsrfMagic.send;
    XMLHttpRequest.prototype.setRequestHeader = CsrfMagic.setRequestHeader
} else {
    // The only way we can do this is by modifying a library you have been
    // using. We plan to support YUI, script.aculo.us, prototype, MooTools, 
    // jQuery, Ext and Dojo.
    if (window.jQuery) {
        jQuery.csrf_ajax = jQuery.ajax;
        jQuery.ajax = function( s ) {
            if (s.type && s.type.toUpperCase() == 'POST') {
                if ( s.data && s.processData && typeof s.data != "string" ) {
                    s.data = jQuery.param(s.data);
                }
                var append = csrfMagicName + '=' + csrfMagicToken;
                if (!s.data) s.data = append;
                else s.data = append + '&' + s.data;
            }
            return jQuery.csrf_ajax( s );
        }
    } else if (window.Prototype) {
        // This works for script.aculo.us too
        Ajax.Request.prototype.csrf_initialize = Ajax.Request.prototype.initialize;
        Ajax.Request.prototype.initialize = function (url, options) {
            // Prototype has somewhat strange behavior in that it
            // simulates all other request types with post
            if (options.method.toLowerCase() != 'get') {
                // Do not edit the options hash
                var params = Object.clone(options.parameters);
                if (typeof params == 'string') {
                    params = csrfMagicName + '=' + csrfMagicToken + '&' + options.parameters;
                } else {
                    params[csrfMagicName] = csrfMagicToken;
                }
                options.parameters = params;
            }
            this.csrf_initialize(url, options);
        }
    }
}
