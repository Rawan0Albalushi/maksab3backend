"use strict";

(function () {
    var page = document.getElementById('lang-tr-page');
    if (!page) return;

    var $modal = window.jQuery;
    var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var strings = JSON.parse(document.getElementById('lang-tr-strings').textContent);
    var urls = {
        save: page.dataset.saveUrl,
        bulk: page.dataset.bulkUrl,
        auto: page.dataset.autoUrl,
        batch: page.dataset.batchUrl,
        remove: page.dataset.removeUrl
    };

    var overview = document.getElementById('lang-tr-overview');
    var savebar = document.getElementById('lang-tr-savebar');
    var dirtyCounter = document.getElementById('lang-tr-dirty-count');
    var saveAllBtn = document.getElementById('lang-tr-save-all');
    var stats = {
        total: overview ? parseInt(overview.dataset.total, 10) || 0 : 0,
        translated: overview ? parseInt(overview.dataset.translated, 10) || 0 : 0
    };

    /* ------------------------------------------------------------ net */

    function handle(response) {
        return response.json().catch(function () {
            return {};
        }).then(function (body) {
            if (!response.ok) {
                throw new Error(body.message || strings.request_failed);
            }
            return body;
        });
    }

    function send(url, data, asJson) {
        var headers = {
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        };
        var body;

        if (asJson) {
            headers['Content-Type'] = 'application/json';
            body = JSON.stringify(data);
        } else {
            body = new URLSearchParams(data);
        }

        return fetch(url, {
            method: 'POST',
            headers: headers,
            body: body,
            credentials: 'same-origin'
        }).then(handle);
    }

    function read(url, params) {
        var query = new URLSearchParams(params).toString();

        return fetch(url + (query ? '?' + query : ''), {
            headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
            credentials: 'same-origin'
        }).then(handle);
    }

    function notify(type, message) {
        if (window.toastr && typeof window.toastr[type] === 'function') {
            window.toastr[type](message);
        }
    }

    function busy(button, state) {
        if (!button) return;
        button.classList.toggle('is-busy', state);
        button.disabled = state;
    }

    /* ----------------------------------------------------------- rows */

    function rows() {
        return Array.prototype.slice.call(document.querySelectorAll('.lang-tr-row'));
    }

    function field(row) {
        return row.querySelector('.lang-tr-input');
    }

    function isDirty(row) {
        var input = field(row);
        return !!input && input.value !== input.dataset.original;
    }

    function dirtyRows() {
        return rows().filter(isDirty);
    }

    function autogrow(input) {
        input.style.height = 'auto';
        input.style.height = (input.scrollHeight + 2) + 'px';
    }

    function placeholders(row) {
        try {
            return JSON.parse(row.dataset.placeholders || '[]');
        } catch (error) {
            return [];
        }
    }

    function missingPlaceholders(row) {
        var value = field(row).value;

        return placeholders(row).filter(function (token) {
            return value.indexOf(token) === -1;
        });
    }

    function paintRow(row) {
        var meta = row.querySelector('[data-role="meta"]');
        var dirty = isDirty(row);
        var done = row.dataset.translated === '1';
        var missing = missingPlaceholders(row);
        var label = dirty ? strings.badge_dirty : (done ? strings.badge_done : strings.badge_pending);
        var variant = dirty ? 'dirty' : (done ? 'done' : 'pending');

        if (missing.length) {
            label = strings.placeholder_missing + ': ' + missing.join(', ');
            variant = 'invalid';
        }

        row.classList.toggle('is-dirty', dirty);
        row.classList.toggle('is-invalid', missing.length > 0);

        if (meta) {
            meta.innerHTML = '<span class="lang-tr-badge lang-tr-badge--' + variant + '"></span>';
            meta.firstChild.appendChild(document.createTextNode(label));
        }
    }

    /* Keeps the header numbers honest as rows are saved or removed. */
    function paintStats() {
        if (!overview) return;

        var pending = Math.max(stats.total - stats.translated, 0);
        var percentage = stats.total > 0 ? Math.round((stats.translated / stats.total) * 1000) / 10 : 0;

        overview.querySelector('[data-stat="total"]').textContent = stats.total;
        overview.querySelector('[data-stat="translated"]').textContent = stats.translated;
        overview.querySelector('[data-stat="pending"]').textContent = pending;
        overview.querySelector('[data-stat="percentage"]').textContent = percentage;
        overview.querySelector('[data-stat="bar"]').style.width = percentage + '%';
    }

    function applyState(row, translated) {
        var was = row.dataset.translated === '1';
        var now = !!translated;

        if (was !== now) {
            stats.translated += now ? 1 : -1;
            row.dataset.translated = now ? '1' : '0';
            paintStats();
        }
    }

    function markSaved(row) {
        row.classList.remove('is-saved');
        void row.offsetWidth;
        row.classList.add('is-saved');
        window.setTimeout(function () {
            row.classList.remove('is-saved');
        }, 1300);
    }

    function paintSavebar() {
        var count = dirtyRows().length;
        dirtyCounter.textContent = count;
        savebar.classList.toggle('is-visible', count > 0);
    }

    function refresh(row) {
        paintRow(row);
        paintSavebar();
    }

    /* ---------------------------------------------------------- saving */

    function saveRow(row, button) {
        var input = field(row);
        var value = input.value;
        var missing = missingPlaceholders(row);

        if (missing.length) {
            notify('error', strings.placeholder_missing + ': ' + missing.join(', '));
            refresh(row);
            return Promise.resolve();
        }

        busy(button, true);

        return send(urls.save, {key: row.dataset.key, value: value}).then(function (response) {
            input.dataset.original = value;
            applyState(row, response.translated);
            markSaved(row);
            refresh(row);
            notify('success', strings.saved);
        }).catch(function (error) {
            notify('error', error.message);
        }).then(function () {
            busy(button, false);
        });
    }

    function saveAll() {
        var pending = dirtyRows();
        var broken = pending.filter(function (row) {
            return missingPlaceholders(row).length > 0;
        });

        if (broken.length) {
            broken.forEach(refresh);
            pending = pending.filter(function (row) {
                return broken.indexOf(row) === -1;
            });
        }

        if (!pending.length) {
            notify(broken.length ? 'error' : 'info', broken.length ? strings.placeholder_blocked : strings.nothing_to_save);
            return;
        }

        var payload = {};
        pending.forEach(function (row) {
            payload[row.dataset.key] = field(row).value;
        });

        busy(saveAllBtn, true);

        send(urls.bulk, {translations: payload}, true).then(function (response) {
            var states = response.states || {};

            pending.forEach(function (row) {
                var key = row.dataset.key;
                if (!Object.prototype.hasOwnProperty.call(states, key)) return;

                field(row).dataset.original = payload[key];
                applyState(row, states[key]);
                markSaved(row);
                refresh(row);
            });

            notify('success', response.updated + ' ' + strings.saved_many);

            if (response.placeholder_message) {
                notify('error', response.placeholder_message);
            } else if (response.skipped) {
                notify('warning', strings.skipped + ' (' + response.skipped + ')');
            }

            if (broken.length) {
                notify('error', strings.placeholder_blocked);
            }
        }).catch(function (error) {
            notify('error', error.message);
        }).then(function () {
            busy(saveAllBtn, false);
        });
    }

    function autoTranslate(row, button) {
        var input = field(row);

        busy(button, true);

        return send(urls.auto, {key: row.dataset.key}).then(function (response) {
            input.value = response.translated_data;
            input.dataset.original = response.translated_data;
            autogrow(input);
            applyState(row, response.translated);
            markSaved(row);
            refresh(row);
            notify('success', strings.translated);
        }).catch(function (error) {
            notify('error', error.message);
        }).then(function () {
            busy(button, false);
        });
    }

    /* --------------------------------------------------------- removal */

    var removeTarget = null;

    function removeRow(row) {
        return send(urls.remove, {key: row.dataset.key}).then(function () {
            if (row.dataset.translated === '1') {
                stats.translated = Math.max(stats.translated - 1, 0);
            }
            stats.total = Math.max(stats.total - 1, 0);
            paintStats();
            row.parentNode.removeChild(row);
            paintSavebar();
            notify('success', strings.removed);
        }).catch(function (error) {
            notify('error', error.message);
        });
    }

    /* ---------------------------------------------------------- events */

    rows().forEach(function (row) {
        autogrow(field(row));
        paintRow(row);
    });

    function target(event) {
        return event.target instanceof Element ? event.target : null;
    }

    document.addEventListener('input', function (event) {
        var node = target(event);
        var input = node && node.closest('.lang-tr-input');
        if (!input) return;

        autogrow(input);
        refresh(input.closest('.lang-tr-row'));
    });

    document.addEventListener('keydown', function (event) {
        var node = target(event);
        var input = node && node.closest('.lang-tr-input');

        // Checked before the per-row keys: Ctrl/Cmd+S is most often pressed
        // while the caret is still sitting in the field just edited.
        if ((event.metaKey || event.ctrlKey) && event.key && event.key.toLowerCase() === 's') {
            if (dirtyRows().length) {
                event.preventDefault();
                saveAll();
            }
            return;
        }

        if (input) {
            var row = input.closest('.lang-tr-row');

            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                if (isDirty(row)) {
                    saveRow(row, row.querySelector('[data-action="save"]'));
                }
                return;
            }

            if (event.key === 'Escape' && isDirty(row)) {
                event.preventDefault();
                input.value = input.dataset.original;
                autogrow(input);
                refresh(row);
            }

            return;
        }
    });

    document.addEventListener('click', function (event) {
        var node = target(event);
        var button = node && node.closest('.lang-tr-btn');
        if (!button) return;

        var row = button.closest('.lang-tr-row');
        var action = button.dataset.action;

        if (!row) return;

        if (action === 'save') {
            saveRow(row, button);
        } else if (action === 'auto') {
            autoTranslate(row, button);
        } else if (action === 'remove') {
            removeTarget = row;
            document.getElementById('lang-tr-remove-key').textContent = row.dataset.key;
            $modal('#lang-tr-remove-modal').modal('show');
        }
    });

    document.getElementById('lang-tr-remove-confirm').addEventListener('click', function () {
        $modal('#lang-tr-remove-modal').modal('hide');
        if (removeTarget) {
            removeRow(removeTarget);
            removeTarget = null;
        }
    });

    saveAllBtn.addEventListener('click', saveAll);

    document.getElementById('lang-tr-discard').addEventListener('click', function () {
        dirtyRows().forEach(function (row) {
            var input = field(row);
            input.value = input.dataset.original;
            autogrow(input);
            paintRow(row);
        });
        paintSavebar();
    });

    var form = document.getElementById('lang-tr-filter-form');
    var clear = document.getElementById('lang-tr-search-clear');

    document.getElementById('lang-tr-limit').addEventListener('change', function () {
        form.submit();
    });

    if (clear) {
        clear.addEventListener('click', function () {
            document.getElementById('lang-tr-search').value = '';
            form.submit();
        });
    }

    /* ------------------------------------------------- batch translate */

    var batch = {running: false, stopping: false, total: 0};

    function batchUI(percentage, done, total) {
        document.querySelector('.translating-modal-success-rate').textContent = percentage + '%';
        document.querySelector('.translating-modal-success-bar').style.width = percentage + '%';
        document.getElementById('batch-done').textContent = done;
        document.getElementById('batch-total').textContent = total;
    }

    function batchEta(response) {
        var label = '';

        if (response.hours > 0) {
            label = response.hours + ' ' + strings.hours + ' ' + response.minutes + ' ' + strings.min;
        } else if (response.minutes > 0) {
            label = response.minutes + ' ' + strings.min;
        } else if (response.seconds > 0) {
            label = response.seconds + ' ' + strings.seconds;
        }

        if (label) {
            document.getElementById('time-data').textContent = label;
        }
    }

    function endBatch(reload) {
        batch.running = false;
        batch.stopping = false;
        $modal('#translating-modal').modal('hide');

        if (reload) {
            window.location.reload();
        }
    }

    function runBatch() {
        if (batch.stopping) {
            endBatch(true);
            return;
        }

        read(urls.batch, {translating_count: batch.total}).then(function (response) {
            if (response.data === 'data_prepared') {
                batch.total = response.total || 0;
                batchUI(0, 0, batch.total);
                runBatch();
                return;
            }

            if (response.data === 'translating' && response.status === 'pending') {
                // The server owns the denominator: resuming a queue left over
                // from an earlier run means the total this page started with
                // is wrong, and it corrects it on the first poll.
                batch.total = response.total || batch.total;
                batchUI(response.percentage, response.done || 0, batch.total);
                batchEta(response);
                runBatch();
                return;
            }

            if (response.data === 'error') {
                endBatch(false);
                notify('error', response.message || strings.request_failed);
                return;
            }

            // 'success', or 'translating' with nothing left to do.
            batch.running = false;
            batch.stopping = false;
            $modal('#translating-modal').modal('hide');
            $modal('#complete-modal').modal('show');
        }).catch(function (error) {
            endBatch(false);
            notify('error', error.message);
        });
    }

    var translateAllBtn = document.getElementById('translate-confirm-btn');

    if (translateAllBtn) {
        translateAllBtn.addEventListener('click', function () {
            $modal('#translate-confirm-modal').modal('show');
        });
    }

    document.addEventListener('click', function (event) {
        var node = target(event);
        if (!node) return;

        if (node.closest('.auto_translate_all')) {
            batch.running = true;
            batch.stopping = false;
            batch.total = 0;
            batchUI(0, 0, 0);
            $modal('#translating-modal').modal('show');
            runBatch();
        }

        if (node.closest('.lang-tr-reload')) {
            window.location.reload();
        }

        var stop = node.closest('#lang-tr-batch-stop');
        if (stop) {
            batch.stopping = true;
            stop.disabled = true;
            stop.textContent = strings.stopping;
        }
    });

    window.addEventListener('beforeunload', function (event) {
        if (batch.running || dirtyRows().length) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    paintSavebar();
})();
