'use strict';

(function () {
    var bar = document.getElementById('page-progress-bar');
    if (!bar) return;

    var safetyTimer = null;

    function startProgress() {
        clearTimeout(safetyTimer);
        bar.classList.remove('done');
        void bar.offsetWidth;
        bar.classList.add('active');
        safetyTimer = setTimeout(finishProgress, 15000);
    }

    function finishProgress() {
        clearTimeout(safetyTimer);
        if (!bar.classList.contains('active')) return;
        bar.classList.remove('active');
        bar.classList.add('done');
    }

    window.pageProgress = { start: startProgress, finish: finishProgress };

    document.addEventListener('click', function (event) {
        if (event.defaultPrevented || event.button !== 0) return;
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        var link = event.target.closest ? event.target.closest('a[href]') : null;
        if (!link) return;
        if (link.dataset.bsToggle || link.hasAttribute('data-no-progress')) return;
        if (link.hasAttribute('download')) return;
        if (link.target && link.target !== '' && link.target !== '_self') return;

        var href = link.getAttribute('href');
        if (!href || href.charAt(0) === '#' || /^(javascript:|mailto:|tel:)/i.test(href)) return;
        if (link.origin && link.origin !== window.location.origin) return;

        try {
            var url = new URL(link.href, window.location.href);
            if (url.pathname === window.location.pathname && url.search === window.location.search) return;
        } catch (error) {
            return;
        }

        startProgress();
    });

    document.addEventListener('submit', function (event) {
        if (event.defaultPrevented) return;
        var form = event.target;
        if (!form || form.tagName !== 'FORM') return;
        if (form.getAttribute('target') === '_blank') return;
        startProgress();
    });

    window.addEventListener('pageshow', finishProgress);
})();
