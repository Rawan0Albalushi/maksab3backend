'use strict';

(function ($) {
    const page = document.getElementById('msgPage');

    if (!page) {
        return;
    }

    const listUrl = page.dataset.listUrl;
    const shell = page.querySelector('#msgShell');
    const listEl = page.querySelector('#conversation-list');
    const loaderEl = page.querySelector('#msgListLoader');
    const threadEl = page.querySelector('.msg-pane--thread');
    const searchWrap = page.querySelector('#msgSearch');
    const searchInput = page.querySelector('#msgSearchInput');
    const strings = JSON.parse(page.dataset.strings || '{}');

    // The empty placeholder is thrown away the moment a thread is opened, so
    // keep a copy to put back when the last conversation disappears.
    const threadPlaceholder = threadEl.innerHTML;

    const EMOJIS = [
        '😀', '😃', '😄', '😁', '😅', '😂', '🙂', '😉',
        '😊', '😍', '😘', '😎', '🤩', '🤔', '🤗', '😌',
        '😔', '😕', '😢', '😭', '😡', '😱', '😴', '🤝',
        '👍', '👎', '👌', '🙏', '👏', '💪', '🖐️', '✌️',
        '❤️', '🔥', '⭐', '✅', '❌', '⚠️', '❓', '❗',
        '🎉', '🎁', '💰', '💳', '🧾', '📦', '🚚', '🛵',
        '🏠', '📍', '📞', '📧', '🕒', '📅', '📝', '🔔'
    ];

    const state = {
        page: 1,
        key: '',
        unread: false,
        loading: false,
        hasMore: listEl.dataset.hasMore === '1',
        activeUser: getParam('user'),
        activeConversation: getParam('conversation')
    };

    function getParam(name) {
        return new URLSearchParams(window.location.search).get(name) || '';
    }

    function toast(type, message) {
        if (window.toastr && message) {
            toastr[type](message, { CloseButton: true, ProgressBar: true });
        }
    }

    /* ----------------------------------------------------------------------
       Conversation list
       ---------------------------------------------------------------------- */

    function requestUrl(pageNumber) {
        const params = new URLSearchParams({ page: pageNumber });

        if (state.key) {
            params.set('key', state.key);
        }

        if (state.unread) {
            params.set('unread', '1');
        }

        return listUrl + '?' + params.toString();
    }

    function markActiveRow() {
        $(listEl).find('.customer-list').removeClass('conv-active');

        if (!state.activeUser) {
            return;
        }

        const row = document.getElementById('customer-' + state.activeUser);

        if (row) {
            row.classList.add('conv-active');
            row.classList.remove('is-unread');
            $(row).find('.msg-item__badge').remove();
        }
    }

    function setLoading(isLoading) {
        state.loading = isLoading;
        loaderEl.hidden = !isLoading;
    }

    // Reloads from page one. Used by search, by the filter tabs and -- via the
    // window.conversationList override -- after a reply is sent or a push
    // notification arrives, so the current search is never silently dropped.
    function reloadList() {
        state.page = 1;
        setLoading(true);

        return $.get(requestUrl(1))
            .done(function (data) {
                listEl.innerHTML = data.html;
                state.hasMore = !!data.has_more;
                listEl.scrollTop = 0;
                markActiveRow();
            })
            .fail(function () {
                toast('error', strings.load_failed);
            })
            .always(function () {
                setLoading(false);
            });
    }

    function loadNextPage() {
        if (state.loading || !state.hasMore) {
            return;
        }

        setLoading(true);

        $.get(requestUrl(state.page + 1))
            .done(function (data) {
                if (!$.trim(data.html)) {
                    state.hasMore = false;
                    return;
                }

                state.page += 1;
                state.hasMore = !!data.has_more;
                $(listEl).append(data.html);
                markActiveRow();
            })
            .fail(function () {
                toast('error', strings.load_failed);
            })
            .always(function () {
                setLoading(false);
            });
    }

    // The layout calls conversationList() after a reply and on every incoming
    // push. Replace it so those refreshes keep the active search, the unread
    // filter and the highlighted row instead of resetting the rail to an
    // unfiltered page one. The layout declares the function in a <script> that
    // is parsed after this file, so the swap has to wait for ready.
    $(function () {
        window.conversationList = reloadList;
    });

    let searchTimer = null;

    $(searchInput).on('input', function () {
        const value = this.value.trim();

        searchWrap.classList.toggle('has-value', value.length > 0);
        clearTimeout(searchTimer);

        searchTimer = setTimeout(function () {
            if (value === state.key) {
                return;
            }

            state.key = value;
            reloadList();
        }, 350);
    });

    $(page).on('click', '#msgSearchClear', function () {
        searchInput.value = '';
        searchWrap.classList.remove('has-value');

        if (state.key) {
            state.key = '';
            reloadList();
        }

        searchInput.focus();
    });

    $(page).on('click', '.msg-filter', function () {
        const unread = this.dataset.filter === 'unread';

        if (unread === state.unread) {
            return;
        }

        state.unread = unread;
        $(page).find('.msg-filter').removeClass('is-active');
        this.classList.add('is-active');
        reloadList();
    });

    $(listEl).on('scroll', function () {
        if (this.scrollTop + this.clientHeight >= this.scrollHeight - 120) {
            loadNextPage();
        }
    });

    /* ----------------------------------------------------------------------
       Opening a conversation
       ---------------------------------------------------------------------- */

    function openConversation(url, conversationId, userId) {
        state.activeUser = String(userId);
        state.activeConversation = String(conversationId);
        markActiveRow();

        if (shell) {
            shell.classList.add('is-thread-open');
        }

        $.get(url)
            .done(function (data) {
                const params = new URLSearchParams(window.location.search);

                params.set('conversation', conversationId);
                params.set('user', userId);
                window.history.replaceState({}, '', window.location.pathname + '?' + params.toString());

                threadEl.innerHTML = data.view;
            })
            .fail(function () {
                toast('error', strings.load_failed);
            });
    }

    // Old markup called this from an inline onclick; keep the name exported in
    // case a cached page or another view still reaches for it.
    window.viewAdminConvs = function (url, idToActive, conversationId, senderId) {
        openConversation(url, conversationId, senderId);
    };

    $(page).on('click', '.view-conv', function () {
        openConversation(this.dataset.url, this.dataset.convId, this.dataset.senderId);
    });

    $(page).on('keydown', '.view-conv', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            this.click();
        }
    });

    $(page).on('click', '.msg-thread__back', function () {
        if (shell) {
            shell.classList.remove('is-thread-open');
        }
    });

    /* ----------------------------------------------------------------------
       Thread: runs on every injected copy, whoever injected it
       ---------------------------------------------------------------------- */

    function scrollThreadToEnd() {
        const body = threadEl.querySelector('#msgThreadBody');

        if (body) {
            body.scrollTop = body.scrollHeight;
        }
    }

    function buildEmojiPanel() {
        const panel = threadEl.querySelector('#msgEmojiPanel');

        if (!panel || panel.childElementCount) {
            return;
        }

        panel.innerHTML = EMOJIS.map(function (emoji) {
            return '<button type="button" class="msg-emoji__btn" data-emoji="' + emoji + '">' + emoji + '</button>';
        }).join('');
    }

    function onThreadRendered() {
        if (!threadEl.querySelector('.msg-thread')) {
            return;
        }

        buildEmojiPanel();
        scrollThreadToEnd();
        resetAttachments();

        if (window.matchMedia('(min-width: 992px)').matches) {
            const input = threadEl.querySelector('#msgComposerInput');

            if (input) {
                input.focus();
            }
        }
    }

    new MutationObserver(onThreadRendered).observe(threadEl, { childList: true });
    onThreadRendered();

    /* ----------------------------------------------------------------------
       Composer
       ---------------------------------------------------------------------- */

    const MAX_ATTACHMENTS = 5;
    let attachments = [];
    let previewUrls = [];

    function resetAttachments() {
        attachments = [];
        renderAttachments();
    }

    function renderAttachments() {
        const wrap = threadEl.querySelector('#msgComposerPreviews');
        const input = threadEl.querySelector('#msgComposerFiles');

        if (!wrap || !input) {
            return;
        }

        previewUrls.forEach(function (url) {
            URL.revokeObjectURL(url);
        });
        previewUrls = attachments.map(function (file) {
            return URL.createObjectURL(file);
        });

        wrap.innerHTML = attachments.map(function (file, index) {
            return '<div class="msg-preview">' +
                '<img src="' + previewUrls[index] + '" alt="">' +
                '<button type="button" class="msg-preview__remove" data-remove-attachment="' + index + '" ' +
                'aria-label="' + (strings.remove || 'Remove') + '"><i class="tio-clear"></i></button>' +
                '</div>';
        }).join('');

        // The form is submitted as FormData straight off this input, so the
        // input has to hold exactly what the previews show -- otherwise a
        // removed thumbnail still gets uploaded.
        const transfer = new DataTransfer();
        attachments.forEach(function (file) {
            transfer.items.add(file);
        });
        input.files = transfer.files;
    }

    function autoGrow(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 140) + 'px';
    }

    $(page).on('input', '#msgComposerInput', function () {
        autoGrow(this);
    });

    $(page).on('keydown', '#msgComposerInput', function (event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            $(this).closest('form').trigger('submit');
        }
    });

    $(page).on('change', '#msgComposerFiles', function () {
        const picked = Array.prototype.slice.call(this.files).filter(function (file) {
            return file.type.indexOf('image/') === 0;
        });

        if (picked.length !== this.files.length) {
            toast('error', strings.images_only);
        }

        const room = MAX_ATTACHMENTS - attachments.length;

        if (picked.length > room) {
            toast('warning', strings.attachment_limit);
        }

        attachments = attachments.concat(picked.slice(0, Math.max(room, 0)));
        renderAttachments();
    });

    $(page).on('click', '[data-remove-attachment]', function () {
        attachments.splice(Number(this.dataset.removeAttachment), 1);
        renderAttachments();
    });

    $(page).on('click', '#msgEmojiToggle', function (event) {
        event.stopPropagation();
        const panel = threadEl.querySelector('#msgEmojiPanel');

        if (panel) {
            panel.hidden = !panel.hidden;
            this.classList.toggle('is-active', !panel.hidden);
        }
    });

    $(page).on('click', '[data-emoji]', function () {
        const input = threadEl.querySelector('#msgComposerInput');

        if (!input) {
            return;
        }

        const start = input.selectionStart;
        const end = input.selectionEnd;
        const emoji = this.dataset.emoji;

        input.value = input.value.slice(0, start) + emoji + input.value.slice(end);
        input.selectionStart = input.selectionEnd = start + emoji.length;
        input.focus();
        autoGrow(input);
    });

    $(document).on('click', function (event) {
        const panel = threadEl.querySelector('#msgEmojiPanel');

        if (panel && !panel.hidden && !event.target.closest('.msg-emoji')) {
            panel.hidden = true;
            $(threadEl).find('#msgEmojiToggle').removeClass('is-active');
        }
    });

    $(page).on('submit', '#msgComposer', function (event) {
        event.preventDefault();

        const form = this;
        const input = form.querySelector('#msgComposerInput');
        const sendBtn = form.querySelector('.msg-send');

        if (!$.trim(input.value) && attachments.length === 0) {
            toast('error', strings.empty_message);
            input.focus();
            return;
        }

        sendBtn.disabled = true;

        $.ajax({
            url: form.dataset.storeUrl,
            type: 'POST',
            data: new FormData(form),
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            cache: false,
            contentType: false,
            processData: false
        })
            .done(function (data) {
                if (data.errors && data.errors.length > 0) {
                    sendBtn.disabled = false;
                    toast('error', data.errors[0].message || strings.empty_message);
                    return;
                }

                resetAttachments();
                threadEl.innerHTML = data.view;
                reloadList();
                toast('success', strings.message_sent);
            })
            .fail(function () {
                sendBtn.disabled = false;
                toast('error', strings.send_failed);
            });
    });

    /* ----------------------------------------------------------------------
       Attachment lightbox
       ---------------------------------------------------------------------- */

    const lightbox = document.getElementById('msgLightbox');
    const lightboxImg = lightbox ? lightbox.querySelector('img') : null;

    $(page).on('click', '.msg-attachment', function (event) {
        event.preventDefault();

        if (lightbox && lightboxImg) {
            lightboxImg.src = this.dataset.fullImage || this.querySelector('img').src;
            lightbox.hidden = false;
        }
    });

    $(lightbox).on('click', function (event) {
        if (event.target === lightbox || event.target.closest('.msg-lightbox__close')) {
            lightbox.hidden = true;
            lightboxImg.src = '';
        }
    });

    $(document).on('keydown', function (event) {
        if (event.key === 'Escape' && lightbox && !lightbox.hidden) {
            lightbox.hidden = true;
            lightboxImg.src = '';
        }
    });

    /* ----------------------------------------------------------------------
       Deep link: /message/list?conversation=..&user=..
       ---------------------------------------------------------------------- */

    if (state.activeConversation && state.activeUser) {
        if (shell) {
            shell.classList.add('is-thread-open');
        }

        $.get(page.dataset.viewUrl + '/' + state.activeConversation + '/' + state.activeUser)
            .done(function (data) {
                threadEl.innerHTML = data.view;
                markActiveRow();
            });
    }
})(jQuery);
