(function ($) {
    'use strict';
    if (!$) return;
    var pending = {};
    $.ajaxPrefilter(function (options) {
        if (!options.crossDomain && /transactions\/(nso_|payment)$/.test(options.url)) {
            var identity = options.url + '|' + options.data;
            pending[identity] = pending[identity] || (Date.now().toString(36) + '_' + Array.from(crypto.getRandomValues(new Uint32Array(4))).join('_'));
            options.nyotaIdentity = identity;
            options.headers = options.headers || {};
            options.headers['Idempotency-Key'] = pending[identity];
        }
    });
    $(document).ajaxComplete(function (_event, xhr, options) {
        var result = xhr.responseJSON;
        if (result && result.csrfToken) document.querySelector('meta[name="csrf-token"]').content = result.csrfToken;
        if (result && options.nyotaIdentity && (result.status === 0 || result.status === 1)) delete pending[options.nyotaIdentity];
    });
    $.ajaxSetup({beforeSend: function (xhr, options) {
        if (!options.crossDomain) {
            xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]').content);
            xhr.setRequestHeader('Accept', 'application/json');
        }
    }});
    $(document).on('click', 'a[href$="/logout"]', function (event) {
        event.preventDefault();
        var form = document.createElement('form');
        form.method = 'POST'; form.action = this.href;
        var token = document.createElement('input');
        token.type = 'hidden'; token.name = '_token'; token.value = document.querySelector('meta[name="csrf-token"]').content;
        form.appendChild(token); document.body.appendChild(form); form.submit();
    });
    // The old script calls this plugin on the login page, where the plugin is not loaded.
    if (!$.fn.selectpicker) $.fn.selectpicker = function () { return this; };
})(window.jQuery);
