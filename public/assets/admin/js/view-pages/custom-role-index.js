"use strict";

(function () {
    const form = document.querySelector('.role-form');

    if (!form) {
        return;
    }

    const groupsWrap = form.querySelector('[data-rp-groups]');
    const groups = Array.from(form.querySelectorAll('[data-rp-group]'));
    const inputs = Array.from(form.querySelectorAll('[data-rp-input]'));
    const summaryCount = form.querySelector('[data-rp-summary-count]');
    const searchBox = form.querySelector('[data-rp-search-box]');
    const searchInput = form.querySelector('[data-rp-search-input]');
    const searchClear = form.querySelector('[data-rp-search-clear]');
    const selectAll = form.querySelector('[data-rp-select-all]');

    let collapsedBeforeSearch = null;

    function setTriState(box, checked, total) {
        box.checked = total > 0 && checked === total;
        box.indeterminate = checked > 0 && checked < total;
    }

    function paintCount(node, checked, total) {
        node.textContent = checked + '/' + total;
        node.classList.toggle('is-active', checked > 0);
    }

    function refresh() {
        let pageChecked = 0;

        groups.forEach(function (group) {
            let groupChecked = 0;
            let groupTotal = 0;

            group.querySelectorAll('[data-rp-perm]').forEach(function (card) {
                const boxes = Array.from(card.querySelectorAll('[data-rp-input]'));
                const checked = boxes.filter(function (box) { return box.checked; }).length;

                groupChecked += checked;
                groupTotal += boxes.length;

                paintCount(card.querySelector('[data-rp-perm-count]'), checked, boxes.length);
                setTriState(card.querySelector('[data-rp-perm-all]'), checked, boxes.length);
            });

            pageChecked += groupChecked;

            paintCount(group.querySelector('[data-rp-group-count]'), groupChecked, groupTotal);
            setTriState(group.querySelector('[data-rp-group-all]'), groupChecked, groupTotal);
        });

        summaryCount.textContent = pageChecked;
        setTriState(selectAll, pageChecked, inputs.length);
    }

    function setCollapsed(group, collapsed) {
        group.classList.toggle('is-collapsed', collapsed);
        group.querySelector('[data-rp-group-toggle]').setAttribute('aria-expanded', String(!collapsed));
    }

    function highlight(label, term) {
        const text = label.dataset.rpText;
        const at = term ? text.toLowerCase().indexOf(term) : -1;

        if (at === -1) {
            label.textContent = text;
            return;
        }

        const hit = document.createElement('mark');
        hit.className = 'rp-hit';
        hit.textContent = text.slice(at, at + term.length);

        label.textContent = '';
        label.appendChild(document.createTextNode(text.slice(0, at)));
        label.appendChild(hit);
        label.appendChild(document.createTextNode(text.slice(at + term.length)));
    }

    function filter(term) {
        let matches = 0;

        if (term && collapsedBeforeSearch === null) {
            collapsedBeforeSearch = groups.map(function (group) {
                return group.classList.contains('is-collapsed');
            });
        }

        groups.forEach(function (group, index) {
            let groupHits = 0;

            group.querySelectorAll('[data-rp-perm]').forEach(function (card) {
                let cardHits = 0;

                card.querySelectorAll('[data-rp-item]').forEach(function (item) {
                    const hit = !term || item.dataset.rpSearch.indexOf(term) !== -1;

                    item.classList.toggle('is-hidden', !hit);
                    highlight(item.querySelector('[data-rp-label]'), hit ? term : '');

                    if (hit) {
                        cardHits += 1;
                    }
                });

                card.classList.toggle('is-hidden', cardHits === 0);
                groupHits += cardHits;
            });

            group.classList.toggle('is-hidden', groupHits === 0);
            matches += groupHits;

            if (term) {
                setCollapsed(group, false);
            } else if (collapsedBeforeSearch) {
                setCollapsed(group, collapsedBeforeSearch[index]);
            }
        });

        if (!term) {
            collapsedBeforeSearch = null;
        }

        searchBox.classList.toggle('has-value', Boolean(term));
        form.classList.toggle('is-empty', matches === 0);
    }

    inputs.forEach(function (input) {
        input.dataset.rpDefault = input.checked ? '1' : '';
        input.addEventListener('change', refresh);
    });

    form.querySelectorAll('[data-rp-label]').forEach(function (label) {
        label.dataset.rpText = label.textContent.trim();
    });

    form.querySelectorAll('[data-rp-perm-all]').forEach(function (box) {
        box.addEventListener('change', function () {
            box.closest('[data-rp-perm]')
                .querySelectorAll('[data-rp-item]:not(.is-hidden) [data-rp-input]')
                .forEach(function (input) { input.checked = box.checked; });
            refresh();
        });
    });

    form.querySelectorAll('[data-rp-group-all]').forEach(function (box) {
        box.addEventListener('change', function () {
            box.closest('[data-rp-group]')
                .querySelectorAll('[data-rp-perm]:not(.is-hidden) [data-rp-item]:not(.is-hidden) [data-rp-input]')
                .forEach(function (input) { input.checked = box.checked; });
            refresh();
        });
    });

    selectAll.addEventListener('change', function () {
        groupsWrap
            .querySelectorAll('[data-rp-group]:not(.is-hidden) [data-rp-item]:not(.is-hidden) [data-rp-input]')
            .forEach(function (input) { input.checked = selectAll.checked; });
        refresh();
    });

    form.querySelectorAll('[data-rp-group-toggle]').forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            const group = toggle.closest('[data-rp-group]');
            setCollapsed(group, !group.classList.contains('is-collapsed'));
        });
    });

    form.querySelector('[data-rp-expand-all]').addEventListener('click', function () {
        groups.forEach(function (group) { setCollapsed(group, false); });
    });

    form.querySelector('[data-rp-collapse-all]').addEventListener('click', function () {
        groups.forEach(function (group) { setCollapsed(group, true); });
    });

    let searchTimer = null;

    searchInput.addEventListener('input', function () {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(function () {
            filter(searchInput.value.trim().toLowerCase());
        }, 120);
    });

    searchInput.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            searchInput.value = '';
            filter('');
        }
    });

    searchClear.addEventListener('click', function () {
        searchInput.value = '';
        searchInput.focus();
        filter('');
    });

    form.addEventListener('reset', function () {
        window.setTimeout(function () {
            inputs.forEach(function (input) { input.checked = Boolean(input.dataset.rpDefault); });
            searchInput.value = '';
            filter('');
            refresh();
        }, 0);
    });

    refresh();
})();
