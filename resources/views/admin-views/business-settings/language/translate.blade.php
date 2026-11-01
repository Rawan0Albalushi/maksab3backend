@extends('layouts.admin.app')

@section('title', translate('messages.Translations'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/language-translate.css') }}">
@endpush

@php
    $language_name = \App\CentralLogics\Helpers::get_language_name($lang);
    $is_source = $lang === 'en';
    $has_filter = $searchTerm !== '' || $status !== 'all';
    $index_url = route('admin.business-settings.language.index');
    /* Inlined rather than <img src>, so the glyph can pick up currentColor on hover/focus. */
    $translate_icon = '<svg width="15" height="15" viewBox="0 0 14 14" fill="currentColor" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M14 4.083v1.167a.583.583 0 0 1-1.167 0V4.083c0-.643-.523-1.166-1.166-1.166h-1.184l.729.762a.583.583 0 0 1-.837.808L9.081 3.145a1.167 1.167 0 0 1 .007-1.632L10.371.179a.583.583 0 1 1 .841.808l-.733.763h1.188A2.333 2.333 0 0 1 14 4.083ZM3.629 9.512a.583.583 0 0 0-.841.81l.729.762H2.333a1.167 1.167 0 0 1-1.166-1.167V8.751a.583.583 0 0 0-1.167 0v1.166a2.333 2.333 0 0 0 2.333 2.334h1.187l-.732.762a.583.583 0 1 0 .841.808l1.283-1.335a1.167 1.167 0 0 0 .007-1.632L3.629 9.512ZM7 4.667A2.333 2.333 0 0 1 4.667 7H2.333A2.333 2.333 0 0 1 0 4.667V2.333A2.333 2.333 0 0 1 2.333 0h2.334A2.333 2.333 0 0 1 7 2.333v2.334Zm-1.458-2.558a.359.359 0 0 0-.36-.359H3.866v-.224a.359.359 0 0 0-.359-.359h-.012a.359.359 0 0 0-.36.359v.224H1.818a.359.359 0 0 0-.36.36v.012c0 .199.161.36.36.36h2.445c-.065.562-.282 1.255-.76 1.792a3.2 3.2 0 0 1-.404-.583.36.36 0 0 0-.68.19c.131.251.292.492.484.712a2.62 2.62 0 0 1-1.153.37.363.363 0 0 0-.331.362v.012c0 .213.184.378.396.358a3.6 3.6 0 0 0 1.652-.596 3.66 3.66 0 0 0 1.638.596.363.363 0 0 0 .397-.358v-.012a.363.363 0 0 0-.324-.358 2.63 2.63 0 0 1-1.154-.372c.578-.662.866-1.511.937-2.255h.184c.199 0 .36-.161.36-.36v-.012ZM14 9.333v2.334A2.333 2.333 0 0 1 11.667 14H9.333A2.333 2.333 0 0 1 7 11.667V9.333A2.333 2.333 0 0 1 9.333 7h2.334A2.333 2.333 0 0 1 14 9.333Zm-1.864 3.001-.795-3.47a.9.9 0 0 0-.491-.595.9.9 0 0 0-1.199.594l-.824 3.496a.36.36 0 0 0 .397.499.4.4 0 0 0 .397-.315l.16-.677h1.405l.155.675a.4.4 0 0 0 .398.292h.001a.36.36 0 0 0 .396-.499Zm-1.644-3.351c-.022 0-.041.015-.046.037l-.473 2.005h1.025l-.459-2.005c-.005-.022-.025-.037-.047-.037Z"/></svg>';
@endphp

@section('content')
    <div class="content container-fluid lang-tr"
         id="lang-tr-page"
         data-lang="{{ $lang }}"
         data-source-language="{{ $is_source ? 1 : 0 }}"
         data-save-url="{{ route('admin.business-settings.language.translate-submit', [$lang]) }}"
         data-bulk-url="{{ route('admin.business-settings.language.translate-bulk-submit', [$lang]) }}"
         data-auto-url="{{ route('admin.business-settings.language.auto-translate', [$lang]) }}"
         data-batch-url="{{ route('admin.business-settings.language.auto_translate_all', [$lang]) }}"
         data-remove-url="{{ route('admin.business-settings.language.remove-key', [$lang]) }}">

        <div class="lang-tr-head">
            <a href="{{ $index_url }}" class="lang-tr-back" aria-label="{{ translate('messages.Back to Language Setup') }}"
               title="{{ translate('messages.Back to Language Setup') }}">
                <i class="tio-chevron-left"></i>
            </a>
            <div class="lang-tr-head-text">
                <h1 class="lang-tr-title">
                    {{ $language_name }}
                    <span class="lang-tr-chip">{{ $lang }}</span>
                    @if($direction === 'rtl')
                        <span class="lang-tr-chip lang-tr-chip--muted">RTL</span>
                    @endif
                    @if($is_source)
                        <span class="lang-tr-chip lang-tr-chip--muted">{{ translate('messages.Source language') }}</span>
                    @endif
                </h1>
                <p class="lang-tr-subtitle">
                    {{ translate('messages.Edit the wording your admin panel, website and apps use for this language.') }}
                </p>
            </div>
        </div>

        <div class="lang-tr-overview"
             id="lang-tr-overview"
             data-total="{{ $stats['total'] }}"
             data-translated="{{ $stats['translated'] }}">
            <div>
                <div class="lang-tr-progress-label">
                    <span class="lang-tr-progress-value">
                        <span data-stat="percentage">{{ $stats['percentage'] }}</span>%
                        <small>{{ translate('Complete') }}</small>
                    </span>
                </div>
                <div class="lang-tr-progress-track">
                    <span class="lang-tr-progress-fill" data-stat="bar" style="width: {{ $stats['percentage'] }}%"></span>
                </div>
            </div>
            <div class="lang-tr-stats">
                <a href="{{ request()->fullUrlWithQuery(['status' => 'all', 'page' => null]) }}" class="lang-tr-stat lang-tr-stat--total">
                    <span class="lang-tr-stat-label"><span class="lang-tr-stat-dot"></span>{{ translate('messages.Total keys') }}</span>
                    <span class="lang-tr-stat-value" data-stat="total">{{ $stats['total'] }}</span>
                </a>
                <a href="{{ request()->fullUrlWithQuery(['status' => 'translated', 'page' => null]) }}" class="lang-tr-stat lang-tr-stat--done">
                    <span class="lang-tr-stat-label"><span class="lang-tr-stat-dot"></span>{{ translate('messages.Translated') }}</span>
                    <span class="lang-tr-stat-value" data-stat="translated">{{ $stats['translated'] }}</span>
                </a>
                <a href="{{ request()->fullUrlWithQuery(['status' => 'pending', 'page' => null]) }}" class="lang-tr-stat lang-tr-stat--pending">
                    <span class="lang-tr-stat-label"><span class="lang-tr-stat-dot"></span>{{ translate('Pending') }}</span>
                    <span class="lang-tr-stat-value" data-stat="pending">{{ $stats['pending'] }}</span>
                </a>
            </div>
        </div>

        @if($is_source)
            <div class="lang-tr-note lang-tr-note--info">
                <i class="tio-info-outined"></i>
                <span>
                    {{ translate('messages.English is the source language. Editing a value here changes the wording wherever it is not overridden.') }}
                </span>
            </div>
        @else
            <div class="lang-tr-note">
                <i class="tio-warning"></i>
                <span>
                    {{ translate('messages.Saved changes go live immediately for every user on this language.') }}
                    {{ translate('messages.Machine translations are only a starting point, review them before relying on them.') }}
                </span>
            </div>
        @endif

        <div class="card card-body">
            <form method="GET" class="lang-tr-toolbar" id="lang-tr-filter-form" role="search">
                <h2 class="lang-tr-toolbar-title">
                    {{ translate('messages.Translation keys') }}
                    <span class="lang-tr-count">{{ number_format($result_count) }}</span>
                </h2>

                <div class="lang-tr-filters" role="group" aria-label="{{ translate('messages.Filter by status') }}">
                    @foreach(['all' => translate('All'), 'translated' => translate('messages.Translated'), 'pending' => translate('Pending')] as $value => $label)
                        <a class="lang-tr-filter {{ $status === $value ? 'is-active' : '' }}"
                           href="{{ request()->fullUrlWithQuery(['status' => $value, 'page' => null]) }}"
                           @if($status === $value) aria-current="true" @endif>
                            {{ $label }}
                            @if($value !== 'all')
                                <span class="lang-tr-filter-num">{{ $value === 'translated' ? $stats['translated'] : $stats['pending'] }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>

                <div class="lang-tr-search">
                    <i class="tio-search lang-tr-search-icon"></i>
                    <label class="sr-only" for="lang-tr-search">{{ translate('messages.Search Language') }}</label>
                    <input id="lang-tr-search" type="search" name="search" class="form-control"
                           value="{{ $searchTerm }}"
                           placeholder="{{ translate('messages.Search key or translation') }}">
                    @if($searchTerm !== '')
                        <button type="button" class="lang-tr-search-clear" id="lang-tr-search-clear"
                                aria-label="{{ translate('messages.Clear search') }}">&times;</button>
                    @endif
                    <button type="submit" class="sr-only">{{ translate('messages.Search') }}</button>
                </div>

                <div class="lang-tr-perpage">
                    <label class="mb-0" for="lang-tr-limit">{{ translate('messages.Show') }}</label>
                    <select name="limit" id="lang-tr-limit" class="form-control custom-select">
                        @foreach([25, 50, 100, 200] as $option)
                            <option value="{{ $option }}" {{ $limit === $option ? 'selected' : '' }}>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>

                <input type="hidden" name="status" value="{{ $status }}">

                @if(!$is_source)
                    <button type="button" class="btn btn--primary lang-tr-translate-all" id="translate-confirm-btn">
                        <i class="tio-globe"></i> {!! $translate_icon !!}
                        {{ translate('messages.Translate All') }}
                    </button>
                @endif
            </form>

            @if($result_count > 0)
                <div class="table-responsive">
                    <table class="table lang-tr-table" id="lang-tr-table">
                        <thead>
                        <tr>
                            <th class="lang-tr-col-sl">{{ translate('messages.SL') }}</th>
                            <th class="lang-tr-col-source">{{ translate('messages.Source text') }}</th>
                            <th>{{ $is_source ? translate('messages.value') : translate('messages.Translation') }}</th>
                            <th class="lang-tr-col-action text-right">{{ translate('messages.Action') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($full_data as $key => $row)
                            @php
                                $placeholders = preg_match_all('/:[a-zA-Z_][a-zA-Z0-9_]*/', (string) $key, $found)
                                    ? array_values(array_unique($found[0]))
                                    : [];
                                $source_html = preg_replace(
                                    '/:[a-zA-Z_][a-zA-Z0-9_]*/',
                                    '<code class="lang-tr-ph">$0</code>',
                                    e($row['source'])
                                );
                            @endphp
                            <tr class="lang-tr-row"
                                data-key="{{ $key }}"
                                data-placeholders="{{ json_encode($placeholders) }}"
                                data-translated="{{ $row['translated'] ? 1 : 0 }}">
                                <td class="lang-tr-sl">{{ $loop->index + $full_data->firstItem() }}</td>
                                <td class="lang-tr-source">
                                    {!! $source_html !!}
                                    @if($placeholders)
                                        <span class="lang-tr-phnote">{{ translate('messages.Keep the highlighted parts exactly as they are.') }}</span>
                                    @endif
                                    {{-- Show the raw key only when it carries information the
                                         source line does not. Comparing them verbatim meant every
                                         snake_case key ("zip") printed beside its own label
                                         ("Zip zip"), which reads as a duplicated row. --}}
                                    @if(mb_strtolower(trim(preg_replace('/\s+/u', ' ', str_replace('_', ' ', $key)))) !== mb_strtolower(trim($row['source'])))
                                        <code class="lang-tr-key">{{ $key }}</code>
                                    @endif
                                </td>
                                <td class="lang-tr-value">
                                    <label class="sr-only" for="lang-tr-input-{{ $loop->index }}">
                                        {{ translate('messages.Translation for') }} {{ $row['source'] }}
                                    </label>
                                    <textarea id="lang-tr-input-{{ $loop->index }}"
                                              class="lang-tr-input"
                                              rows="1"
                                              dir="{{ $direction }}"
                                              spellcheck="false"
                                              placeholder="{{ $row['source'] }}"
                                              data-original="{{ $row['value'] }}">{{ $row['value'] }}</textarea>
                                    <div class="lang-tr-meta" data-role="meta"></div>
                                </td>
                                <td class="lang-tr-col-action">
                                    <div class="lang-tr-actions">
                                        {{-- Offered on English too: there it round-trips the key
                                             through the translator, which restores the field to the
                                             source phrase. --}}
                                        <button type="button" class="lang-tr-btn lang-tr-btn-auto" data-action="auto"
                                                title="{{ $is_source ? translate('messages.Reset to the source phrase') : translate('messages.Auto translate this key') }}"
                                                aria-label="{{ $is_source ? translate('messages.Reset to the source phrase') : translate('messages.Auto translate this key') }}">
                                            <span class="lang-tr-btn-icon">{!! $translate_icon !!}</span>
                                            <span class="lang-tr-spinner"></span>
                                        </button>
                                        <button type="button" class="lang-tr-btn lang-tr-btn-save" data-action="save"
                                                title="{{ translate('messages.Save') }}"
                                                aria-label="{{ translate('messages.Save') }}">
                                            <span class="lang-tr-btn-icon"><i class="tio-save"></i></span>
                                            <span class="lang-tr-spinner"></span>
                                        </button>
                                        <button type="button" class="lang-tr-btn lang-tr-btn-danger" data-action="remove"
                                                title="{{ translate('messages.Remove this key') }}"
                                                aria-label="{{ translate('messages.Remove this key') }}">
                                            <span class="lang-tr-btn-icon"><i class="tio-delete-outlined"></i></span>
                                            <span class="lang-tr-spinner"></span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="page-area mt-3">
                    {!! $full_data->appends(request()->query())->links() !!}
                </div>
            @else
                <div class="lang-tr-empty">
                    <img src="{{ asset('public/assets/admin/svg/illustrations/sorry.svg') }}" alt="">
                    <h5>{{ translate('No data found') }}</h5>
                    @if($has_filter)
                        <p>{{ translate('messages.No key matches the current search or filter. Try a different term or clear the filters.') }}</p>
                        <a href="{{ route('admin.business-settings.language.translate', [$lang]) }}" class="btn btn--primary">
                            <i class="tio-clear-circle-outlined"></i> {{ translate('messages.Clear filters') }}
                        </a>
                    @else
                        <p>{{ translate('messages.This language file has no keys yet.') }}</p>
                    @endif
                </div>
            @endif
        </div>

        <div class="lang-tr-savebar" id="lang-tr-savebar" role="status" aria-live="polite">
            <span class="lang-tr-savebar-text">
                <strong><span id="lang-tr-dirty-count">0</span> {{ translate('messages.Unsaved changes') }}</strong>
                <span class="lang-tr-savebar-hint">{{ translate('messages.Enter saves a row, Esc reverts it') }}</span>
            </span>
            <button type="button" class="btn lang-tr-savebar-discard" id="lang-tr-discard">
                <i class="tio-clear-circle-outlined"></i> {{ translate('messages.Discard') }}
            </button>
            <button type="button" class="btn btn--primary" id="lang-tr-save-all">
                <i class="tio-save"></i> <span class="lang-tr-spinner"></span>
                {{ translate('messages.Save all') }}
            </button>
        </div>

        <script type="application/json" id="lang-tr-strings">
            {{-- json_encode(), not @json(): the directive explodes its
                 expression on "," to find the flags argument, so an inline
                 array literal with several entries compiles to broken PHP. --}}
            {!! json_encode([
                'saved' => translate('Updated successfully'),
                'saved_many' => translate('messages.Translations updated'),
                'translated' => translate('messages.Key translated successfully'),
                'removed' => translate('Deleted successfully'),
                'nothing_to_save' => translate('messages.There is nothing to save'),
                'request_failed' => translate('messages.Something went wrong. Please try again.'),
                'skipped' => translate('messages.Some keys could not be saved'),
                'placeholder_missing' => translate('messages.Missing placeholder'),
                'placeholder_blocked' => translate('messages.Rows with a missing placeholder were not saved'),
                'badge_pending' => translate('Pending'),
                'badge_done' => translate('messages.Translated'),
                'badge_dirty' => translate('messages.Unsaved'),
                'stopping' => translate('messages.Stopping'),
                'hours' => translate('messages.hours'),
                'min' => translate('messages.Min'),
                'seconds' => translate('messages.seconds'),
            ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}
        </script>
    </div>

    <div class="modal fade language-complete-modal" id="translate-confirm-modal" tabindex="-1" role="dialog"
         aria-labelledby="translate-confirm-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered max-w-450px">
            <div class="modal-content">
                <div class="modal-body text-center">
                    <div class="py-5">
                        <div class="mb-4">
                            <img src="{{ asset('public/assets/admin/img/language-complete.png') }}" alt="">
                        </div>
                        <h4 class="mb-3" id="translate-confirm-title">{{ translate('messages.Are you sure?') }}</h4>
                        <p class="mb-4 text-9EADC1 max-w-362px mx-auto">
                            {{ translate('messages.You want to auto translate all. It may take a while to complete the translation') }}
                        </p>
                        <div class="d-flex justify-content-center gap-3 pt-1">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal"><i class="tio-clear-circle-outlined"></i> {{ translate('messages.Cancel') }}</button>
                            <button type="button" class="btn btn--primary auto_translate_all" data-dismiss="modal"><i class="tio-globe"></i> {{ translate('messages.Yes, Translate All') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade language-complete-modal" id="complete-modal" tabindex="-1" role="dialog"
         aria-labelledby="complete-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered max-w-450px">
            <div class="modal-content">
                <div class="modal-body text-center">
                    <div class="py-5">
                        <div class="mb-4">
                            <img src="{{ asset('public/assets/admin/img/language-complete.png') }}" alt="">
                        </div>
                        <h4 class="mb-3" id="complete-modal-title">{{ translate('messages.Your file has been successfully translated') }}</h4>
                        <p class="mb-4 text-9EADC1 max-w-362px mx-auto">
                            {{ translate('messages.All your items have been translated.') }}
                        </p>
                        <div class="d-flex justify-content-center gap-3 pt-1">
                            <button type="button" class="btn btn--primary lang-tr-reload"><i class="tio-checkmark-circle-outlined"></i> {{ translate('messages.Okay') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade language-complete-modal lang-tr-progress-modal" id="translating-modal" tabindex="-1"
         role="dialog" aria-labelledby="translating-modal-title" aria-hidden="true"
         data-keyboard="false" data-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center">
                    <div class="py-5 px-sm-2">
                        <div class="progress-circle-container mb-4">
                            <img width="80px" src="{{ asset('public/assets/admin/img/loader-icon.gif') }}" alt="">
                        </div>
                        <h4 class="mb-2" id="translating-modal-title">
                            {{ translate('messages.Translating may take up to') }} <span id="time-data">{{ translate('messages.A few minutes') }}</span>
                        </h4>
                        <p class="mb-4">
                            {{ translate('messages.Please wait & don\'t close/terminate your tab or browser') }}
                        </p>
                        <div class="max-w-215px mx-auto">
                            <div class="d-flex flex-wrap mb-1 justify-content-between font-semibold text--title">
                                <span>{{ translate('messages.In Progress') }}</span>
                                <span class="translating-modal-success-rate">0%</span>
                            </div>
                            <div class="progress mb-2 h-5px">
                                <div class="progress-bar bg-success rounded-pill translating-modal-success-bar"></div>
                            </div>
                            <div class="lang-tr-batch-counts mb-3">
                                <span id="batch-done">0</span> / <span id="batch-total">0</span>
                            </div>
                        </div>
                        <p class="mb-4 text-9EADC1">
                            <span class="text-dark">{{ translate('messages.Note') }}:</span>
                            {{ translate('messages.All the translations may not be fully accurate.') }}
                        </p>
                        <div class="d-flex justify-content-center gap-3 pt-1">
                            <button type="button" class="btn btn-secondary" id="lang-tr-batch-stop">
                                <i class="tio-pause-circle-outlined"></i> {{ translate('messages.Stop & keep what is done') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="lang-tr-remove-modal" tabindex="-1" role="dialog"
         aria-labelledby="lang-tr-remove-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-body text-center p-6">
                    <button type="button" class="close position-absolute top-0 right-0 m-3" data-dismiss="modal"
                            aria-label="{{ translate('messages.Cancel') }}">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <div class="mb-3">
                        <img src="{{ asset('public/assets/admin/img/modal/delete.png') }}" alt="" class="w-12">
                    </div>
                    <h4 class="modal-title mb-2" id="lang-tr-remove-title">{{ translate('messages.Remove this key') }}?</h4>
                    <p class="mb-2">{{ translate('messages.The key is removed from this language file only. It comes back automatically if the system still uses it.') }}</p>
                    <p class="mb-4 font-semibold" id="lang-tr-remove-key"></p>
                    <div class="d-flex justify-content-center gap-3">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><i class="tio-clear-circle-outlined"></i> {{ translate('messages.No, Cancel') }}</button>
                        <button type="button" class="btn btn-danger lang-tr-btn-remove" id="lang-tr-remove-confirm">
                            <i class="tio-delete-outlined"></i> {{ translate('Yes, delete') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
<script src="{{ asset('public/assets/admin/js/view-pages/language-translate.js') }}"></script>
@endpush
