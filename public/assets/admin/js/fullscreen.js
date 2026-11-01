'use strict';

(function () {
    var FRAME_NAME = 'v2-fs-frame';

    var host = findHost();
    var frame = null;
    var revealed = false;
    var leaving = false;
    var pendingUrl = null;
    var enterTitle = null;

    function findHost() {
        try {
            var owner = window.frameElement;
            if (owner && owner.name === FRAME_NAME && window.parent.v2Fullscreen) return window.parent;
        } catch (error) {
            return null;
        }
        return null;
    }

    function isActive() {
        var doc = (host || window).document;
        return !!(doc.fullscreenElement || doc.webkitFullscreenElement);
    }

    function toggle() {
        if (host) return host.v2Fullscreen.toggle();
        if (isActive()) {
            var exit = document.exitFullscreen || document.webkitExitFullscreen;
            if (exit) exit.call(document);
            return;
        }
        var root = document.documentElement;
        var request = root.requestFullscreen || root.webkitRequestFullscreen;
        if (request) request.call(root);
    }

    function syncButton() {
        var button = document.getElementById('v2-fs-btn');
        var icon = document.getElementById('v2-fs-icon');
        if (!button || !icon) return;
        if (enterTitle === null) enterTitle = button.getAttribute('title') || '';
        var active = isActive();
        icon.outerHTML = '<i data-lucide="' + (active ? 'minimize-2' : 'maximize-2') + '" id="v2-fs-icon"></i>';
        button.setAttribute('title', active ? (button.getAttribute('data-exit-title') || enterTitle) : enterTitle);
        if (window.lucide && typeof window.lucide.createIcons === 'function') window.lucide.createIcons();
    }

    function isPlainNavigation(event) {
        return event.cancelable
            && !event.hashChange
            && !event.destination.sameDocument
            && event.navigationType !== 'traverse'
            && typeof event.downloadRequest !== 'string';
    }

    function submitTo(target, url, formData) {
        var form = document.createElement('form');
        form.method = 'post';
        form.action = url;
        form.enctype = 'multipart/form-data';
        form.target = target;
        form.hidden = true;
        formData.forEach(function (value, name) {
            var input = document.createElement('input');
            input.name = name;
            if (typeof value === 'string') {
                input.type = 'hidden';
                input.value = value;
            } else {
                var transfer = new DataTransfer();
                transfer.items.add(value);
                input.type = 'file';
                input.files = transfer.files;
            }
            form.appendChild(input);
        });
        document.body.appendChild(form);
        form.submit();
        form.remove();
    }

    function leave(url, formData) {
        leaving = true;
        if (formData) {
            submitTo('_self', url, formData);
        } else {
            window.location.assign(url);
        }
    }

    function createFrame() {
        var element = document.createElement('iframe');
        element.name = FRAME_NAME;
        element.className = 'v2-fs-frame is-loading';
        element.title = document.title;
        element.addEventListener('load', onFrameReady);
        document.body.appendChild(element);
        return element;
    }

    function openInFrame(url, formData) {
        if (!frame) frame = createFrame();
        pendingUrl = formData ? null : url;
        if (!revealed && window.pageProgress) window.pageProgress.start();
        if (formData) {
            submitTo(FRAME_NAME, url, formData);
        } else {
            frame.contentWindow.location.replace(url);
        }
    }

    function abort() {
        var url = pendingUrl;
        frame.remove();
        frame = null;
        if (url) {
            leave(url);
        } else if (window.pageProgress) {
            window.pageProgress.finish();
        }
    }

    function silence() {
        for (var id = window.setTimeout(function () {}, 0); id > 0; id--) {
            window.clearTimeout(id);
            window.clearInterval(id);
        }
        document.querySelectorAll('audio, video').forEach(function (media) {
            media.pause();
            media.muted = true;
        });
        Array.prototype.forEach.call(document.body.children, function (element) {
            if (element !== frame) element.inert = true;
        });
    }

    function reveal() {
        revealed = true;
        frame.classList.remove('is-loading');
        document.documentElement.classList.add('v2-fs-framed');
        if (window.pageProgress) window.pageProgress.finish();
        silence();
        frame.contentWindow.focus();
    }

    function onFrameReady() {
        if (!frame) return;
        var href;
        try {
            href = frame.contentWindow.location.href;
        } catch (error) {
            if (!revealed) abort();
            return;
        }
        if (href === 'about:blank') return;
        history.replaceState(history.state, '', href);
        document.title = frame.contentDocument.title || document.title;
        frame.title = document.title;
        if (!revealed) reveal();
    }

    function onChange() {
        syncButton();
        if (!revealed) return;
        try {
            frame.contentWindow.v2Fullscreen.sync();
        } catch (error) {
            return;
        }
    }

    function onNavigate(event) {
        if (leaving || !isActive() || !isPlainNavigation(event)) return;
        var url = new URL(event.destination.url);
        if (url.origin !== window.location.origin) return;
        event.preventDefault();
        openInFrame(url.href, event.formData);
    }

    function onFrameNavigate(event) {
        if (!isPlainNavigation(event)) return;
        var url = new URL(event.destination.url);
        if (isActive() && url.origin === window.location.origin) return;
        event.preventDefault();
        host.v2Fullscreen.leave(url.href, event.formData);
    }

    function onReloadKey(event) {
        var key = (event.key || '').toLowerCase();
        if (key !== 'f5' && !((event.ctrlKey || event.metaKey) && key === 'r')) return;
        if (!isActive()) return;
        event.preventDefault();
        if (host) {
            window.location.reload();
        } else {
            openInFrame(window.location.href);
        }
    }

    window.v2Fullscreen = {
        isActive: isActive,
        toggle: toggle,
        sync: syncButton,
        leave: leave,
        ready: onFrameReady
    };

    var button = document.getElementById('v2-fs-btn');
    if (button) button.addEventListener('click', toggle);
    ['fullscreenchange', 'webkitfullscreenchange'].forEach(function (type) {
        document.addEventListener(type, onChange);
    });

    if (!window.navigation) return;

    document.addEventListener('keydown', onReloadKey);

    if (!host) {
        window.navigation.addEventListener('navigate', onNavigate);
        return;
    }

    window.navigation.addEventListener('navigate', onFrameNavigate);
    if (isActive()) syncButton();
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', host.v2Fullscreen.ready);
    } else {
        host.v2Fullscreen.ready();
    }
})();
