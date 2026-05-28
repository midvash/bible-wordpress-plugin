/**
 * Bible by Midvash — Admin (settings page)
 *
 * Keeps the Bible Version <select> in sync with the chosen language by
 * fetching /versions from the Midvash API (cached server-side via transients).
 *
 * Data is passed in via window.bbmAdmin (wp_localize_script):
 *   { ajaxUrl, nonce, defaults, i18n }
 */
(function () {
    'use strict';

    if (!window.bbmAdmin) {
        return;
    }

    var cfg            = window.bbmAdmin;
    var localeSelect   = document.getElementById('bbm_locale');
    var versionSelect  = document.getElementById('bbm_versao');

    if (!localeSelect || !versionSelect) {
        return;
    }

    function setLoading() {
        versionSelect.disabled  = true;
        versionSelect.innerHTML = '';
        var opt = document.createElement('option');
        opt.value       = '';
        opt.textContent = cfg.i18n.loading;
        versionSelect.appendChild(opt);
    }

    function setError(text) {
        versionSelect.innerHTML = '';
        var opt = document.createElement('option');
        opt.value       = '';
        opt.textContent = text;
        versionSelect.appendChild(opt);
        versionSelect.disabled = false;
    }

    function fillVersions(versions, locale) {
        versionSelect.innerHTML = '';
        versions.forEach(function (v) {
            var slug      = (v.slug || v.code || '').toLowerCase();
            var name      = v.name || '';
            var shortName = v.shortName || v.code || '';
            var label     = name ? (name + ' (' + shortName + ')') : shortName;
            var opt       = document.createElement('option');
            opt.value       = slug;
            opt.textContent = label;
            versionSelect.appendChild(opt);
        });

        var preferred = (cfg.defaults && cfg.defaults[locale]) || versions[0].slug.toLowerCase();
        var match     = versionSelect.querySelector('option[value="' + preferred + '"]');
        versionSelect.value = match ? preferred : versionSelect.options[0].value;
        versionSelect.disabled = false;
    }

    function updateVersions(locale) {
        setLoading();

        var body = new FormData();
        body.append('action', 'bbm_get_versions');
        body.append('nonce', cfg.nonce);
        body.append('locale', locale);

        fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('HTTP ' + r.status);
                }
                return r.json();
            })
            .then(function (data) {
                if (data && data.success && Array.isArray(data.data) && data.data.length > 0) {
                    fillVersions(data.data, locale);
                } else {
                    setError(cfg.i18n.empty);
                }
            })
            .catch(function () {
                // Silent failure — the version select stays usable with whatever
                // PHP already rendered server-side.
                setError(cfg.i18n.error);
            });
    }

    // Only refetch if the server-rendered select is empty (e.g. /versions failed
    // server-side); otherwise leave the SSR list alone.
    if (localeSelect.value && versionSelect.options.length <= 1) {
        updateVersions(localeSelect.value);
    }

    localeSelect.addEventListener('change', function () {
        updateVersions(this.value);
    });
})();
