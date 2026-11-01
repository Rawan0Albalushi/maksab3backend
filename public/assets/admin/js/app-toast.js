"use strict";

/**
 * App Toast - modern notification system.
 *
 * Dependency free (no jQuery), RTL aware, accessible.
 * It exposes two APIs so existing call sites keep working:
 *
 *   new_tostar(type, title, description, options)
 *   toastr.success|info|warning|error(message, title, options)
 *
 * Type is one of: success | info | warning | danger | error
 * Options: {
 *   duration        ms before auto dismiss, 0 keeps it until dismissed (default 6000)
 *   closeButton     show the dismiss button (default true)
 *   progressBar     show the countdown bar (default true)
 *   tapToDismiss    dismiss when the card is clicked (default true)
 *   coalesce        merge an identical toast into the live one with a counter (default true)
 *   allowHtml       render title/description as HTML instead of plain text (default false)
 *   position        top-end | top-start | top-center | bottom-end | bottom-start | bottom-center
 *   action          { text, onClick } renders a button inside the toast
 *   icon            custom icon markup, replaces the built-in glyph
 *   onClick(toast)  fired when the card is clicked
 *   onClose(toast)  fired once the toast has been removed
 * }
 */
(function (window, document) {
    if (!window || !document) return;

    var CONTAINER_ID = 'app-toast-container';
    var MAX_VISIBLE = 5;
    var DEFAULT_DURATION = 6000;
    var LEAVE_DURATION = 300;
    var DRAG_THRESHOLD = 6;

    var TYPES = {
        success: {defaultTitle: 'Success'},
        info: {defaultTitle: 'Info'},
        warning: {defaultTitle: 'Warning'},
        danger: {defaultTitle: 'Error'}
    };

    var ALIASES = {error: 'danger', danger: 'danger', success: 'success', info: 'info', warning: 'warning'};

    var SVG_OPEN = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" ' +
        'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">';

    var ICONS = {
        success: SVG_OPEN + '<circle cx="12" cy="12" r="9"/><path d="m8.4 12.3 2.5 2.5 4.7-5"/></svg>',
        info: SVG_OPEN + '<circle cx="12" cy="12" r="9"/><path d="M12 11.2v5"/><path d="M12 7.6h.01"/></svg>',
        warning: SVG_OPEN + '<path d="M10.3 4 2 18.1A2 2 0 0 0 3.7 21h16.6a2 2 0 0 0 1.7-2.9L13.7 4a2 2 0 0 0-3.4 0Z"/>' +
            '<path d="M12 9.5v4"/><path d="M12 17.2h.01"/></svg>',
        danger: SVG_OPEN + '<circle cx="12" cy="12" r="9"/><path d="m14.9 9.1-5.8 5.8"/><path d="m9.1 9.1 5.8 5.8"/></svg>',
        close: '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" ' +
            'aria-hidden="true" focusable="false"><path d="M14.5 5.5 5.5 14.5"/><path d="m5.5 5.5 9 9"/></svg>'
    };

    var POSITIONS = {
        'top-end': '',
        'top-start': 'app-toast-container--top-start',
        'top-center': 'app-toast-container--top-center',
        'bottom-end': 'app-toast-container--bottom-end',
        'bottom-start': 'app-toast-container--bottom-start',
        'bottom-center': 'app-toast-container--bottom-center'
    };

    /* toastr positionClass -> our position keys */
    var LEGACY_POSITIONS = {
        'toast-top-right': 'top-end',
        'toast-top-left': 'top-start',
        'toast-top-center': 'top-center',
        'toast-top-full-width': 'top-center',
        'toast-bottom-right': 'bottom-end',
        'toast-bottom-left': 'bottom-start',
        'toast-bottom-center': 'bottom-center',
        'toast-bottom-full-width': 'bottom-center'
    };

    /* Overridable from Blade via window.APP_TOAST_I18N or AppToast.setLabels(),
       so the default headings follow the active locale. Read lazily so the
       script tag order does not matter. */
    var i18n = {};

    var live = [];
    var byKey = {};

    /* --------------------------------------------------------------------- */

    function now() {
        return new Date().getTime();
    }

    function isFn(value) {
        return typeof value === 'function';
    }

    function str(value) {
        if (value === null || value === undefined) return '';
        if (typeof value === 'string') return value;
        if (typeof value === 'number') return String(value);
        /* Anything else is a call shape mistake rather than copy: the legacy
           `toastr.error(msg, Error, {})` sites pass the Error constructor as
           the title, and others pass their options object there. Neither may
           surface as "function Error()" or "[object Object]". */
        return '';
    }

    function label(key, fallback) {
        var global = window.APP_TOAST_I18N || {};
        return str(i18n[key]) || str(global[key]) || fallback;
    }

    function defaultTitle(type) {
        return label(type, TYPES[type].defaultTitle);
    }

    function setContent(node, value, allowHtml) {
        if (allowHtml) {
            node.innerHTML = value;
        } else {
            node.textContent = value;
        }
    }

    /**
     * Countdown that can be paused and resumed, so hovering the stack
     * holds every toast instead of letting them expire under the cursor.
     */
    function Countdown(callback, delay) {
        var timerId = null;
        var startedAt = 0;
        var remaining = delay;

        this.resume = function () {
            if (timerId !== null) return;
            startedAt = now();
            /* remaining can land on 0 when the pointer leaves right at expiry,
               so fire on the next tick rather than stalling forever. */
            timerId = window.setTimeout(callback, remaining > 0 ? remaining : 0);
        };

        this.pause = function () {
            if (timerId === null) return;
            window.clearTimeout(timerId);
            timerId = null;
            remaining -= (now() - startedAt);
            if (remaining < 0) remaining = 0;
        };

        this.restart = function (delay2) {
            this.stop();
            remaining = delay2;
            this.resume();
        };

        this.stop = function () {
            if (timerId !== null) window.clearTimeout(timerId);
            timerId = null;
        };
    }

    /* --------------------------------------------------------------------- */

    function getContainer(position) {
        var container = document.getElementById(CONTAINER_ID);

        if (!container) {
            container = document.createElement('div');
            container.id = CONTAINER_ID;
            container.className = 'app-toast-container';
            (document.body || document.documentElement).appendChild(container);
        }

        if (!container.getAttribute('data-bound')) {
            container.setAttribute('data-bound', '1');
            container.addEventListener('mouseenter', pauseAll);
            container.addEventListener('mouseleave', resumeAll);
            container.addEventListener('focusin', pauseAll);
            container.addEventListener('focusout', resumeAll);
        }

        if (position && POSITIONS.hasOwnProperty(position)) {
            container.className = ('app-toast-container ' + POSITIONS[position]).replace(/\s+$/, '');
        }

        return container;
    }

    function pauseAll() {
        for (var i = 0; i < live.length; i++) live[i].pause();
    }

    function resumeAll() {
        for (var i = 0; i < live.length; i++) live[i].resume();
    }

    function trimStack(limit) {
        while (live.length > limit) live[0].dismiss();
    }

    /* --------------------------------------------------------------------- */

    function show(config) {
        config = config || {};

        var type = ALIASES[str(config.type).toLowerCase()] || 'success';
        var allowHtml = config.allowHtml === true;
        var message = str(config.message);
        var title = str(config.title);
        var duration = config.duration === undefined ? DEFAULT_DURATION : parseInt(config.duration, 10);

        if (isNaN(duration) || duration < 0) duration = DEFAULT_DURATION;
        /* With no title, promote the message to the heading so a bare
           AppToast.show({message}) does not render an empty first row. Callers
           that want the per type heading instead set forceDefaultTitle. */
        if (!title) title = message && !config.forceDefaultTitle ? '' : defaultTitle(type);

        var heading = title || message;
        var body = title ? message : '';

        if (!heading && !body) return null;

        var closeButton = config.closeButton !== false;
        var progressBar = config.progressBar !== false && duration > 0;
        var tapToDismiss = config.tapToDismiss !== false;
        var coalesce = config.coalesce !== false;
        var position = config.position && POSITIONS.hasOwnProperty(config.position) ? config.position : null;
        var key = type + ' ' + heading + ' ' + body;

        if (coalesce && byKey[key]) {
            byKey[key].bump();
            return byKey[key];
        }

        var container = getContainer(position);

        var el = document.createElement('div');
        el.className = 'app-toast is-entering app-toast--' + type +
            (tapToDismiss || isFn(config.onClick) ? ' app-toast--clickable' : '');
        el.setAttribute('role', type === 'danger' || type === 'warning' ? 'alert' : 'status');
        el.setAttribute('aria-live', type === 'danger' || type === 'warning' ? 'assertive' : 'polite');
        el.style.setProperty('--at-duration', duration + 'ms');

        var bodyEl = document.createElement('div');
        bodyEl.className = 'app-toast__body';

        var iconEl = document.createElement('div');
        iconEl.className = 'app-toast__icon';
        iconEl.innerHTML = config.icon ? config.icon : ICONS[type];

        var contentEl = document.createElement('div');
        contentEl.className = 'app-toast__content';

        var titleEl = document.createElement('h3');
        titleEl.className = 'app-toast__title';

        var labelEl = document.createElement('span');
        labelEl.className = 'app-toast__label';
        setContent(labelEl, heading, allowHtml);
        titleEl.appendChild(labelEl);

        var countEl = null;
        contentEl.appendChild(titleEl);

        if (body) {
            var textEl = document.createElement('p');
            textEl.className = 'app-toast__text';
            setContent(textEl, body, allowHtml);
            contentEl.appendChild(textEl);
        }

        if (config.action && str(config.action.text)) {
            var actionEl = document.createElement('button');
            actionEl.type = 'button';
            actionEl.className = 'app-toast__action';
            actionEl.textContent = str(config.action.text);
            actionEl.addEventListener('click', function (event) {
                event.stopPropagation();
                if (isFn(config.action.onClick)) config.action.onClick(api);
                if (config.action.dismiss !== false) api.dismiss();
            });
            contentEl.appendChild(actionEl);
        }

        bodyEl.appendChild(iconEl);
        bodyEl.appendChild(contentEl);

        if (closeButton) {
            var closeEl = document.createElement('button');
            closeEl.type = 'button';
            closeEl.className = 'app-toast__close';
            closeEl.setAttribute('aria-label', label('close', 'Close'));
            closeEl.innerHTML = ICONS.close;
            closeEl.addEventListener('click', function (event) {
                event.stopPropagation();
                api.dismiss();
            });
            bodyEl.appendChild(closeEl);
        }

        el.appendChild(bodyEl);

        var progressEl = null;

        if (progressBar) {
            var trackEl = document.createElement('div');
            trackEl.className = 'app-toast__track';
            progressEl = document.createElement('div');
            progressEl.className = 'app-toast__progress';
            trackEl.appendChild(progressEl);
            el.appendChild(trackEl);
        }

        container.appendChild(el);

        /* ----------------------------------------------------------------- */

        var dismissed = false;
        var count = 1;
        var countdown = duration > 0 ? new Countdown(function () { api.dismiss(); }, duration) : null;

        var api = {
            el: el,
            type: type,
            dismiss: dismiss,
            bump: bump,
            pause: function () { if (countdown) countdown.pause(); },
            resume: function () { if (countdown) countdown.resume(); }
        };

        /* A filled animation outranks inline styles, so the enter and bump
           classes are dropped as soon as they finish playing. */
        function clearAnimation(name) {
            el.classList.remove(name);
        }

        el.addEventListener('animationend', function (event) {
            if (event.target !== el) return;
            if (event.animationName === 'at-enter') clearAnimation('is-entering');
            if (event.animationName === 'at-bump') clearAnimation('is-bumping');
        });

        function bump() {
            if (dismissed) return;
            count++;

            if (!countEl) {
                countEl = document.createElement('span');
                countEl.className = 'app-toast__count';
                /* Keep "x4" from reordering to "4x" under RTL bidi. */
                countEl.setAttribute('dir', 'ltr');
                titleEl.appendChild(countEl);
            }

            countEl.textContent = '×' + count;

            if (progressEl) {
                progressEl.style.animation = 'none';
                void progressEl.offsetWidth;
                progressEl.style.animation = '';
            }

            el.classList.remove('is-bumping');
            void el.offsetWidth;
            el.classList.add('is-bumping');

            if (countdown) countdown.restart(duration);
        }

        function dismiss() {
            if (dismissed) return;
            dismissed = true;

            if (countdown) countdown.stop();
            if (byKey[key] === api) delete byKey[key];

            var index = live.indexOf(api);
            if (index > -1) live.splice(index, 1);

            /* Freeze the current height so the stack collapses smoothly. */
            el.classList.remove('is-entering', 'is-bumping', 'is-dragging');
            el.style.height = el.offsetHeight + 'px';
            el.style.transform = '';
            el.style.opacity = '';
            void el.offsetHeight;

            el.classList.add('is-leaving');
            el.style.height = '0px';

            var removed = false;

            function remove() {
                if (removed) return;
                removed = true;
                el.removeEventListener('transitionend', onTransitionEnd);
                if (el.parentNode) el.parentNode.removeChild(el);
                if (isFn(config.onClose)) config.onClose(api);
            }

            function onTransitionEnd(event) {
                if (event.target === el && event.propertyName === 'height') remove();
            }

            el.addEventListener('transitionend', onTransitionEnd);
            window.setTimeout(remove, LEAVE_DURATION + 120);
        }

        /* ---- interaction ------------------------------------------------- */

        var dragged = false;

        el.addEventListener('click', function (event) {
            if (dragged) return;
            if (event.target.closest && event.target.closest('button, a, input, textarea, select, label')) return;

            /* Do not swallow the toast while the user is selecting its text. */
            var selection = window.getSelection && window.getSelection();
            if (selection && String(selection).length && el.contains(selection.anchorNode)) return;

            if (isFn(config.onClick)) config.onClick(api);
            if (tapToDismiss) dismiss();
        });

        if (window.PointerEvent) bindDrag();

        function bindDrag() {
            var startX = 0;
            var deltaX = 0;
            var active = false;
            var width = 0;

            el.addEventListener('pointerdown', function (event) {
                if (event.pointerType === 'mouse' && event.button !== 0) return;
                if (event.target.closest && event.target.closest('button, a, input, textarea, select')) return;

                active = true;
                dragged = false;
                deltaX = 0;
                startX = event.clientX;
                width = el.offsetWidth || 1;
            });

            el.addEventListener('pointermove', function (event) {
                if (!active) return;
                deltaX = event.clientX - startX;

                if (!dragged) {
                    if (Math.abs(deltaX) < DRAG_THRESHOLD) return;
                    dragged = true;
                    el.classList.remove('is-entering', 'is-bumping');
                    el.classList.add('is-dragging');
                    if (el.setPointerCapture) {
                        try { el.setPointerCapture(event.pointerId); } catch (e) {}
                    }
                }

                el.style.transform = 'translate3d(' + deltaX + 'px, 0, 0)';
                el.style.opacity = String(Math.max(0.15, 1 - Math.abs(deltaX) / width));
            });

            function release() {
                if (!active) return;
                active = false;

                if (dragged && Math.abs(deltaX) > width * 0.3) {
                    el.style.setProperty('--at-exit-dir', deltaX < 0 ? '-1' : '1');
                    dismiss();
                    return;
                }

                el.classList.remove('is-dragging');
                el.style.transform = '';
                el.style.opacity = '';
                /* Let the pending click handler see the drag, then clear it. */
                window.setTimeout(function () { dragged = false; }, 0);
            }

            el.addEventListener('pointerup', release);
            el.addEventListener('pointercancel', release);
        }

        /* ---- register ---------------------------------------------------- */

        live.push(api);
        if (coalesce) byKey[key] = api;
        trimStack(config.maxVisible || MAX_VISIBLE);

        if (countdown) {
            var hovered = container.matches ? container.matches(':hover') : false;
            if (!hovered) countdown.resume();
        }

        return api;
    }

    function dismissAll() {
        for (var i = live.length - 1; i >= 0; i--) live[i].dismiss();
    }

    /* --------------------------------------------------------------------- */
    /* Public API                                                            */
    /* --------------------------------------------------------------------- */

    /**
     * @param {string} type          success | info | warning | danger | error
     * @param {string} [title]       heading, falls back to a per type default
     * @param {string} [description] body copy, the row is dropped when empty
     * @param {Object} [options]
     */
    function new_tostar(type, title, description, options) {
        var config = {};

        for (var prop in (options || {})) {
            if (Object.prototype.hasOwnProperty.call(options, prop)) config[prop] = options[prop];
        }

        config.type = type;
        config.title = str(title) || defaultTitle(ALIASES[str(type).toLowerCase()] || 'success');
        config.message = description;

        return show(config);
    }

    var AppToast = {
        show: show,
        dismissAll: dismissAll,
        success: function (message, title, options) { return new_tostar('success', title, message, options); },
        info: function (message, title, options) { return new_tostar('info', title, message, options); },
        warning: function (message, title, options) { return new_tostar('warning', title, message, options); },
        error: function (message, title, options) { return new_tostar('danger', title, message, options); },
        setLabels: function (labels) {
            for (var prop in (labels || {})) {
                if (Object.prototype.hasOwnProperty.call(labels, prop)) i18n[prop] = labels[prop];
            }
        }
    };

    /* --------------------------------------------------------------------- */
    /* toastr compatibility layer                                            */
    /*                                                                       */
    /* Replaces toastr 2.1.3 so the ~400 existing toastr.* call sites render  */
    /* with this design and keep honouring toastr.options.                   */
    /* --------------------------------------------------------------------- */

    function fromToastrOptions(overrides) {
        var merged = {};
        var sources = [toastrApi.options || {}, overrides || {}];

        for (var i = 0; i < sources.length; i++) {
            for (var prop in sources[i]) {
                if (Object.prototype.hasOwnProperty.call(sources[i], prop)) merged[prop] = sources[i][prop];
            }
        }

        var config = {};

        /* toastr spells some flags in camelCase and some call sites in PascalCase. */
        var timeOut = merged.timeOut !== undefined ? merged.timeOut : merged.TimeOut;
        if (timeOut !== undefined) config.duration = parseInt(timeOut, 10);

        var closeButton = merged.closeButton !== undefined ? merged.closeButton : merged.CloseButton;
        if (closeButton !== undefined) config.closeButton = closeButton !== false && closeButton !== 'false';

        var progressBar = merged.progressBar !== undefined ? merged.progressBar : merged.ProgressBar;
        if (progressBar !== undefined) config.progressBar = progressBar !== false && progressBar !== 'false';

        if (merged.tapToDismiss !== undefined) config.tapToDismiss = merged.tapToDismiss !== false;
        if (merged.preventDuplicates !== undefined) config.coalesce = merged.preventDuplicates !== false;
        /* toastr renders HTML by default. We escape by default and only opt in
           when a call site asks for it explicitly, so a translated string can
           never inject markup. */
        if (merged.allowHtml === true) config.allowHtml = true;
        if (isFn(merged.onclick)) config.onClick = merged.onclick;
        if (isFn(merged.onHidden)) config.onClose = merged.onHidden;
        if (merged.position && POSITIONS.hasOwnProperty(merged.position)) config.position = merged.position;
        else if (merged.positionClass && LEGACY_POSITIONS[merged.positionClass]) config.position = LEGACY_POSITIONS[merged.positionClass];

        return config;
    }

    function toastrMethod(type) {
        return function (message, title, overrides) {
            /* 210 call sites in this codebase pass the options object in the
               title slot, e.g. toastr.success('Saved', {CloseButton: true}).
               toastr 2.1.3 silently discarded them; honour them instead. */
            if (overrides === undefined && title !== null && typeof title === 'object' && !isFn(title)) {
                overrides = title;
                title = '';
            }

            var config = fromToastrOptions(overrides);
            config.type = type;
            config.title = str(title);
            config.message = str(message);

            if (!config.title) {
                config.title = defaultTitle(type);
                config.forceDefaultTitle = true;
            }

            return show(config);
        };
    }

    var toastrApi = {
        options: {},
        version: '2.1.3-apptoast',
        success: null,
        info: null,
        warning: null,
        error: null,
        clear: dismissAll,
        remove: dismissAll,
        subscribe: function () {},
        getContainer: function () { return getContainer(null); }
    };

    toastrApi.success = toastrMethod('success');
    toastrApi.info = toastrMethod('info');
    toastrApi.warning = toastrMethod('warning');
    toastrApi.error = toastrMethod('danger');

    window.new_tostar = new_tostar;
    window.AppToast = AppToast;
    window.toastr = toastrApi;
})(window, document);
