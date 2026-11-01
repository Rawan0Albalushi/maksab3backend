@extends('layouts.admin.app')

@section('title', translate('messages.Conversation list'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/messages.css') }}">
@endpush

@section('content')
    @php
        $jsStrings = [
            'load_failed' => translate('messages.Could not load the conversations, please try again'),
            'send_failed' => translate('messages.Could not send the message, please try again'),
            'empty_message' => translate('messages.Write a message or attach an image before sending'),
            'message_sent' => translate('messages.Message sent'),
            'images_only' => translate('Please upload a file in a supported format') . ': PNG, JPG',
            'attachment_limit' => translate('messages.Maximum images') . ': 5',
            'remove' => translate('messages.remove'),
        ];
    @endphp

    <div class="content container-fluid msg-page" id="msgPage" data-list-url="{{ route('admin.message.list') }}"
        data-view-url="{{ url('/') }}/admin/message/view" data-strings="{{ json_encode($jsStrings) }}">

        <div class="msg-page__head">
            <div>
                <h1 class="msg-page__title">
                    <i class="tio-messages"></i>
                    {{ translate('messages.Conversation list') }}
                </h1>
                <p class="msg-page__subtitle">
                    {{ translate('messages.Reply to customers and delivery men from a single inbox') }}
                </p>
            </div>

            <div class="msg-stats">
                <span class="msg-stat">
                    {{ translate('messages.Conversations') }}
                    <b>{{ $total_conversations }}</b>
                </span>
                <span class="msg-stat msg-stat--unread">
                    <span class="msg-stat__dot"></span>
                    {{ translate('messages.unread') }}
                    <b>{{ $unread_conversations }}</b>
                </span>
            </div>
        </div>

        <div class="msg-shell" id="msgShell">

            {{-- ---------------------------------------------------------------- --}}
            {{-- Rail: search, filters and the paginated conversation list.        --}}
            {{-- #conversation-list is replaced wholesale by conversationList() in --}}
            {{-- the layout, so it holds nothing but rows.                         --}}
            {{-- ---------------------------------------------------------------- --}}
            <aside class="msg-pane msg-pane--rail">
                <div class="msg-rail__head">
                    <div class="msg-search" id="msgSearch">
                        <span class="msg-search__icon"><i class="tio-search"></i></span>
                        <input type="text" class="msg-search__input" id="msgSearchInput" autocomplete="off"
                            placeholder="{{ translate('messages.Search by name or phone') }}"
                            aria-label="{{ translate('messages.Search by name or phone') }}">
                        <button type="button" class="msg-search__clear" id="msgSearchClear"
                            aria-label="{{ translate('Clear') }}"><i class="tio-clear"></i></button>
                    </div>

                    <div class="msg-filters" role="tablist">
                        <button type="button" class="msg-filter is-active" data-filter="all">
                            {{ translate('All') }}
                            <span class="msg-filter__count">{{ $total_conversations }}</span>
                        </button>
                        <button type="button" class="msg-filter" data-filter="unread">
                            {{ translate('messages.unread') }}
                            <span class="msg-filter__count">{{ $unread_conversations }}</span>
                        </button>
                    </div>
                </div>

                <div class="msg-rail__list" id="conversation-list" data-has-more="{{ $conversations->hasMorePages() ? 1 : 0 }}">
                    @include('admin-views.messages.data')
                </div>

                <div class="msg-rail__foot" id="msgListLoader" hidden>
                    <span class="msg-spinner"></span>{{ translate('messages.loading') }}
                </div>
            </aside>

            {{-- ---------------------------------------------------------------- --}}
            {{-- Thread pane. Replaced by the _conversations partial over AJAX.    --}}
            {{-- ---------------------------------------------------------------- --}}
            <section class="msg-pane msg-pane--thread" id="admin-view-conversation" aria-live="polite">
                <div class="msg-empty">
                    <i class="tio-chat-outlined"></i>
                    <h6>{{ translate('messages.View conversation') }}</h6>
                    <p>{{ translate('messages.Pick a conversation from the list to read the history and reply') }}</p>
                </div>
            </section>
        </div>
    </div>

    <div class="msg-lightbox" id="msgLightbox" hidden>
        <button type="button" class="msg-lightbox__close" aria-label="{{ translate('messages.Close') }}">
            <i class="tio-clear"></i>
        </button>
        <img src="" alt="{{ translate('Attachment') }}">
    </div>
@endsection

@push('script_2')
    <script src="{{ asset('public/assets/admin/js/view-pages/messages.js') }}"></script>
@endpush
