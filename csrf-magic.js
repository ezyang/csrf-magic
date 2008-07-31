/**
 * @file
 *
 * Rewrites XMLHttpRequest to automatically send CSRF token with it. In theory
 * plays nice with other JavaScript libraries, needs testing though.
 */
// Here are the basic overloaded method definitions
// The wrapper must be set BEFORE onreadystatechange is written to, since
// a bug in ActiveXObject prevents us from properly testing for it.
var CsrfMagic = function (real) {
    // try to make it ourselves, if you didn't pass it
    if (!real) try { real = new XMLHttpRequest; } catch (e) {;}
    if (!real) try { real = new ActiveXObject('Msxml2.XMLHTTP'); } catch (e) {;}
    if (!real) try { real = new ActiveXObject('Microsoft.XMLHTTP'); } catch (e) {;}
    if (!real) try { real = new ActiveXObject('Msxml2.XMLHTTP.4.0'); } catch (e) {;}
    this.csrf = real;
    // properties
    var csrfMagic = this;
    real.onreadystatechange = function() {
        csrfMagic._updateProps();
        return csrfMagic.onreadystatechange ? csrfMagic.onreadystatechange() : null;
    };
    csrfMagic._updateProps();
}

CsrfMagic.prototype.open = function(method, url, async, username, password) {
    if (method == 'POST') this.csrf_isPost = true;
    // deal with Opera bug, thanks jQuery
    if (username) return this.csrf_open(method, url, async, username, password);
    else return this.csrf_open(method, url, async);
}
CsrfMagic.prototype.csrf_open = function(method, url, async, username, password) {
    if (username) return this.csrf.open(method, url, async, username, password);
    else return this.csrf.open(method, url, async);
}

CsrfMagic.prototype.send = function(data) {
    if (!this.csrf_isPost) this.csrf_send(data);
    prepend = csrfMagicName + '=' + csrfMagicToken + '&';
    if (this.csrf_purportedLength === undefined) {
        this.csrf_setRequestHeader("Content-length", this.csrf_purportedLength + prepend.length);
        delete this.csrf_purportedLength;
    }
    delete this.csrf_isPost;
    return this.csrf_send(prepend + data);
}
CsrfMagic.prototype.csrf_send = function(data) {
    return this.csrf.send(data);
}

CsrfMagic.prototype.setRequestHeader = function(header, value) {
    // We have to auto-set this at the end, since we don't know how long the
    // nonce is when added to the data.
    if (this.csrf_isPost && header == "Content-length") {
        this.csrf_purportedLength = value;
        return;
    }
    return this.csrf_setRequestHeader(header, value);
}
CsrfMagic.prototype.csrf_setRequestHeader = function(header, value) {
    return this.csrf.setRequestHeader(header, value);
}

CsrfMagic.prototype.abort = function () {
    return this.csrf.abort();
}
CsrfMagic.prototype.getAllResponseHeaders = function() {
    return this.csrf.getAllResponseHeaders();
}
CsrfMagic.prototype.getResponseHeader = function(header) {
    return this.csrf.getResponseHeader(header);
}

// proprietary
CsrfMagic.prototype._updateProps = function() {
    this.readyState = this.csrf.readyState;
    if (this.readyState == 4) {
        this.responseText = this.csrf.responseText;
        this.responseXML  = this.csrf.responseXML;
        this.status       = this.csrf.status;
        this.statusText   = this.csrf.statusText;
    }
}
CsrfMagic.process = function(base) {
    var prepend = csrfMagicName + '=' + csrfMagicToken;
    if (base) return prepend + '&' + base;
    return prepend;
}

// Sets things up for Mozilla/Opera/nice browsers
if (window.XMLHttpRequest && window.XMLHttpRequest.prototype) {
    XMLHttpRequest.prototype.csrf_open = XMLHttpRequest.prototype.open;
    XMLHttpRequest.prototype.csrf_send = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.csrf_setRequestHeader = XMLHttpRequest.prototype.setRequestHeader;
    
    // Notice that CsrfMagic is itself an instantiatable object, but only
    // open, send and setRequestHeader are necessary as decorators.
    XMLHttpRequest.prototype.open = CsrfMagic.prototype.open;
    XMLHttpRequest.prototype.send = CsrfMagic.prototype.send;
    XMLHttpRequest.prototype.setRequestHeader = CsrfMagic.prototype.setRequestHeader;
} else {
    // The only way we can do this is by modifying a library you have been
    // using. We plan to support YUI, script.aculo.us, prototype, MooTools, 
    // jQuery, Ext and Dojo.
    if (window.jQuery) {
        // jQuery didn't implement a new XMLHttpRequest function, so we have
        // to do this the hard way.
        jQuery.csrf_ajax = jQuery.ajax;
        jQuery.ajax = function( s ) {
            if (s.type && s.type.toUpperCase() == 'POST') {
                s = jQuery.extend(true, s, jQuery.extend(true, {}, jQuery.ajaxSettings, s));
                if ( s.data && s.processData && typeof s.data != "string" ) {
                    s.data = jQuery.param(s.data);
                }
                s.data = CsrfMagic.process(s.data);
            }
            return jQuery.csrf_ajax( s );
        }
    } else if (window.Prototype) {
        // This works for script.aculo.us too
        Ajax.csrf_getTransport = Ajax.getTransport;
        Ajax.getTransport = function() {
            return new CsrfMagic(Ajax.csrf_getTransport());
        }
    } else if (window.MooTools) {
        Browser.csrf_Request = Browser.Request;
        Browser.Request = function () {
            return new CsrfMagic(Browser.csrf_Request());
        }
    } else if (window.YAHOO) {
        YAHOO.util.Connect.csrf_createXhrObject = YAHOO.util.Connect.createXhrObject;
        YAHOO.util.Connect.createXhrObject = function (transaction) {
            obj = YAHOO.util.Connect.csrf_createXhrObject(transaction);
            var old = obj.conn;
            obj.conn = new CsrfMagic(old);
            return obj;
        }
    } else if (window.Ext) {
        // Ext can use other js libraries as loaders, so it has to come last
        // Ext's implementation is pretty identical to Yahoo's, but we duplicate
        // it for comprehensiveness's sake.
        Ext.lib.Ajax.csrf_createXhrObject = Ext.lib.Ajax.createXhrObject;
        Ext.lib.Ajax.createXhrObject = function (transaction) {
            obj = Ext.lib.Ajax.csrf_createXhrObject(transaction);
            var old = obj.conn;
            obj.conn = new CsrfMagic(old);
            return obj;
        }
    } else if (window.dojo) {
        dojo.csrf__xhrObj = dojo._xhrObj;
        dojo._xhrObj = function () {
            return new CsrfMagic(dojo.csrf__xhrObj());
        }
    }
}
