@extends('layouts.admin.app')

@section('title', translate('messages.gallery'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/file-manager.css') }}">
@endpush

@section('content')
    @php
        $is_demo = getEnvMode() == 'demo';
        $has_s3 = \App\CentralLogics\Helpers::getDisk() == 's3';

        /* The s3 root is browsed with an empty path, so every self link has to
           fall back to the root token or the route parameter comes out blank. */
        $current_token = $folder_path !== '' ? $folder_path : 'cHVibGlj';
        $current_url = route('admin.business-settings.file-manager.index', [$current_token, $storage]);
        $upload_path = base64_decode($folder_path);

        /* s3 refuses an upload straight into the bucket root — the controller
           bounces it — so the button says why before it is pressed. */
        $can_upload = $storage !== 's3' || $upload_path !== '';

        $folders = $data->where('type', 'folder');
        $files = $data->where('type', 'file');
    @endphp

    <div class="content container-fluid tps fmg">
        <div class="tps-head">
            <div class="tps-head__title">
                <span class="tps-head__icon"><i class="tio-folder-photo"></i></span>
                <span class="tps-head__text">
                    <h1>{{ translate('messages.gallery') }}</h1>
                    <p>{{ translate('Browse every image on the platform, upload new ones and copy the path a record needs.') }}</p>
                </span>
            </div>

            <div class="d-flex flex-wrap align-items-center __gap-12px">
                <button type="button" class="tps-help" data-toggle="modal" data-target="#fmg-help-modal">
                    <i class="tio-help-outlined"></i>
                    <span>{{ translate('How it works') }}</span>
                </button>
            </div>
        </div>

        @if ($has_s3)
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-20 __gap-12px">
                <div class="js-nav-scroller hs-nav-scroller-horizontal mt-2">
                    <ul class="nav nav-tabs tabs-inner border-0 nav--tabs nav--pills">
                        <li class="nav-item">
                            <a class="nav-link {{ $storage == 'local' ? 'active' : '' }}"
                               href="{{ route('admin.business-settings.file-manager.index', ['folder_path' => 'cHVibGlj', 'storage' => 'local']) }}">
                                {{ translate('Local Storage') }}
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ $storage == 's3' ? 'active' : '' }}"
                               href="{{ route('admin.business-settings.file-manager.index', ['folder_path' => 'cHVibGlj', 'storage' => 's3']) }}">
                                {{ translate('Storage bucket') }} (S3)
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        @endif

        <div class="tps-card">
            <div class="fmg-toolbar">
                <nav class="fmg-path" aria-label="{{ translate('Folder path') }}">
                    <a class="fmg-path__step {{ empty($crumbs) ? 'is-current' : '' }}"
                       href="{{ route('admin.business-settings.file-manager.index', ['cHVibGlj', $storage]) }}">
                        <i class="tio-home-vs-1-outlined"></i>
                        <span>{{ $storage == 's3' ? translate('Bucket root') : translate('Storage root') }}</span>
                    </a>
                    @foreach ($crumbs as $crumb)
                        <span class="fmg-path__sep" aria-hidden="true"><i class="tio-chevron-right"></i></span>
                        @if ($loop->last)
                            <span class="fmg-path__step is-current" aria-current="page">{{ $crumb['label'] }}</span>
                        @else
                            <a class="fmg-path__step"
                               href="{{ route('admin.business-settings.file-manager.index', [$crumb['token'], $storage]) }}">{{ $crumb['label'] }}</a>
                        @endif
                    @endforeach
                </nav>

                <div class="fmg-tools">
                    <form class="fmg-search" action="{{ $current_url }}" method="get" role="search">
                        <i class="tio-search fmg-search__icon"></i>
                        <input type="search" name="search" class="form-control" value="{{ $search }}"
                               autocomplete="off"
                               placeholder="{{ translate('Search in this folder') }}"
                               aria-label="{{ translate('Search in this folder') }}">
                        @if ($search !== '')
                            <a class="fmg-search__clear" href="{{ $current_url }}"
                               aria-label="{{ translate('Clear search') }}"><i class="tio-clear"></i></a>
                        @endif
                    </form>

                    <div class="fmg-view" role="group" aria-label="{{ translate('Layout') }}">
                        <button type="button" class="fmg-view__btn is-active" data-fmg-view="grid"
                                title="{{ translate('Grid view') }}" aria-label="{{ translate('Grid view') }}">
                            <i class="tio-apps"></i>
                        </button>
                        <button type="button" class="fmg-view__btn" data-fmg-view="list"
                                title="{{ translate('List view') }}" aria-label="{{ translate('List view') }}">
                            <i class="tio-format-points"></i>
                        </button>
                    </div>

                    @if ($parent_token)
                        <a class="btn btn--reset"
                           href="{{ route('admin.business-settings.file-manager.index', [$parent_token, $storage]) }}">
                            <i class="tio-arrow-backward"></i> {{ translate('Up one level') }}
                        </a>
                    @endif

                    <button type="button"
                            class="btn btn--primary {{ $is_demo ? 'call-demo' : '' }}"
                            @if (! $is_demo && $can_upload) data-toggle="modal" data-target="#fmg-upload-modal" @endif
                            @if (! $can_upload) disabled title="{{ translate('Open a folder inside the bucket to upload') }}" @endif>
                        <i class="tio-upload"></i> {{ translate('Upload files') }}
                    </button>
                </div>
            </div>

            <div class="fmg-body">
                @if (! $can_upload)
                    <div class="tps-note tps-note--info mb-3">
                        <i class="tio-info-outined"></i>
                        <div>{{ translate('Files cannot be dropped straight into the bucket root. Open one of the folders below to upload.') }}</div>
                    </div>
                @endif

                @if ($data->count() === 0)
                    <div class="fmg-empty">
                        <span class="fmg-empty__icon">
                            <i class="{{ $search !== '' ? 'tio-search' : 'tio-folder-opened' }}"></i>
                        </span>
                        @if ($search !== '')
                            <h3>{{ translate('Nothing matched that search') }}</h3>
                            <p>{{ translate('Nothing in this folder has a name containing your search.') }}</p>
                            <a class="btn btn--primary" href="{{ $current_url }}">
                                <i class="tio-clear-circle-outlined"></i> {{ translate('Clear search') }}
                            </a>
                        @else
                            <h3>{{ translate('This folder is empty') }}</h3>
                            <p>{{ translate('Upload an image here, or step back up and open another folder.') }}</p>
                            @if ($can_upload)
                                <button type="button"
                                        class="btn btn--primary {{ $is_demo ? 'call-demo' : '' }}"
                                        @if (! $is_demo) data-toggle="modal" data-target="#fmg-upload-modal" @endif>
                                    <i class="tio-upload"></i> {{ translate('Upload files') }}
                                </button>
                            @endif
                        @endif
                    </div>
                @else
                    @if ($folders->count())
                        <section class="fmg-section">
                            <h2 class="fmg-section__label">
                                {{ translate('Folders') }}
                                <span class="fmg-section__count">{{ $folder_count }}</span>
                            </h2>
                            <div class="fmg-grid fmg-grid--folders">
                                @foreach ($folders as $folder)
                                    <a class="fmg-folder" title="{{ $folder['name'] }}"
                                       href="{{ route('admin.business-settings.file-manager.index', [base64_encode($folder['path']), $storage]) }}">
                                        <span class="fmg-folder__icon"><i class="tio-folder"></i></span>
                                        <span class="fmg-folder__name">{{ $folder['name'] }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    @if ($files->count())
                        <section class="fmg-section">
                            <h2 class="fmg-section__label">
                                {{ translate('Files') }}
                                <span class="fmg-section__count" id="itemCount">{{ $file_count }}</span>
                            </h2>
                            <div class="fmg-grid">
                                @foreach ($files as $file)
                                    @php
                                        $file_url = $storage == 's3'
                                            ? Storage::disk($storage)->url($file['path'])
                                            : asset('storage/app/' . $file['path']);
                                        $download_url = route('admin.business-settings.file-manager.download', [base64_encode($file['path']), $storage]);
                                        $delete_url = route('admin.business-settings.file-manager.destroy', base64_encode($file['path']));
                                        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                                    @endphp
                                    {{-- The preview reads its five values off the tile, so the
                                         thumbnail and the eye button do not each carry a copy. --}}
                                    <div class="fmg-asset"
                                         data-name="{{ $file['name'] }}"
                                         data-url="{{ $file_url }}"
                                         data-file-path="{{ $file['db_path'] }}"
                                         data-download="{{ $download_url }}"
                                         data-delete="{{ $delete_url }}">
                                        <button type="button" class="fmg-asset__thumb fmg-open"
                                                title="{{ $file['name'] }}"
                                                aria-label="{{ translate('View Image') }}">
                                            <img src="{{ $file_url }}" alt="{{ $file['name'] }}" loading="lazy">
                                        </button>

                                        @if ($extension)
                                            <span class="fmg-ext">{{ $extension }}</span>
                                        @endif

                                        <div class="fmg-asset__actions">
                                            <button type="button" class="fmg-act fmg-open"
                                                    title="{{ translate('View Image') }}">
                                                <i class="tio-visible-outlined"></i>
                                            </button>
                                            <button type="button" class="fmg-act copy-test"
                                                    title="{{ translate('Copy Link') }}"
                                                    data-file-path="{{ $file['db_path'] }}">
                                                <i class="tio-copy"></i>
                                            </button>
                                            <a class="fmg-act" title="{{ translate('Download') }}" href="{{ $download_url }}">
                                                <i class="tio-download-to"></i>
                                            </a>
                                            <button type="button" class="fmg-act fmg-act--danger fmg-delete"
                                                    title="{{ translate('Delete') }}"
                                                    data-delete="{{ $delete_url }}"
                                                    data-name="{{ $file['name'] }}">
                                                <i class="tio-delete-outlined"></i>
                                            </button>
                                        </div>

                                        <div class="fmg-asset__meta">
                                            <span class="fmg-asset__name" title="{{ $file['name'] }}">{{ $file['name'] }}</span>
                                            @if (! empty($file['size']))
                                                <span class="fmg-asset__size">{{ $file['size'] }}</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif
                @endif
            </div>

            <div class="page-area">{!! $data->links() !!}</div>
        </div>
    </div>

    {{-- One preview shell for the whole page — the grid used to render a modal
         per file, which put fifty of them in the DOM on every load. --}}
    <div class="modal fade fmg-modal" id="fmg-preview" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content tps fmg">
                <div class="fmg-preview__head">
                    <h5 class="fmg-preview__title" id="fmg-preview-name"></h5>
                    <span class="fmg-preview__dims" id="fmg-preview-dims"></span>
                    <button type="button" class="close btn btn--reset btn-circle" data-dismiss="modal"
                            aria-label="{{ translate('messages.Close') }}">
                        <span aria-hidden="true" class="tio-clear fs-20 opacity-70"></span>
                    </button>
                </div>
                <div class="fmg-preview__stage">
                    <img id="fmg-preview-image" src="" alt="">
                </div>
                <div class="fmg-preview__foot">
                    <div class="tps-readonly">
                        <span class="tps-readonly__value" id="fmg-preview-path"></span>
                        <button type="button" class="tps-readonly__copy copy-test" id="fmg-preview-copy">
                            <i class="tio-copy"></i> {{ translate('messages.Copy') }}
                        </button>
                    </div>
                    <a class="btn btn--reset" id="fmg-preview-download" href="#">
                        <i class="tio-download-to"></i> {{ translate('Download') }}
                    </a>
                    <button type="button" class="btn btn--reset text-danger fmg-delete" id="fmg-preview-delete"
                            data-delete="" data-name="">
                        <i class="tio-delete-outlined"></i> {{ translate('Delete') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade fmg-modal" id="fmg-upload-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content tps fmg">
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('messages.Upload file') }}</h5>
                    <button type="button" class="close btn btn--reset btn-circle" data-dismiss="modal"
                            aria-label="{{ translate('messages.Close') }}">
                        <span aria-hidden="true" class="tio-clear fs-20 opacity-70"></span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="tps-note tps-note--muted mb-3">
                        <i class="tio-folder"></i>
                        <div>
                            {{ translate('Uploading to') }}
                            <strong>{{ $upload_path !== '' ? $upload_path : translate('Storage root') }}</strong>
                        </div>
                    </div>

                    <form action="{{ route('admin.business-settings.file-manager.image-upload') }}" method="post"
                          enctype="multipart/form-data" id="fmg-upload-form">
                        @csrf
                        <input type="hidden" name="path" value="{{ $upload_path }}">
                        <input type="hidden" name="disk" value="{{ $storage }}">

                        <div class="tps-field">
                            <label class="tps-field__label" for="customFileUpload">{{ translate('Upload image') }}</label>
                            <div class="fmg-drop" id="fmg-dropzone">
                                <span class="fmg-drop__icon"><i class="tio-upload"></i></span>
                                <p class="fmg-drop__title">{{ translate('Drop images here or click to browse') }}</p>
                                <p class="fmg-drop__hint">{{ IMAGE_EXTENSION }} &middot; {{ translate('Several files at once is fine') }}</p>
                                <input type="file" name="images[]" id="customFileUpload"
                                       accept="{{ IMAGE_EXTENSION }}" multiple
                                       aria-label="{{ translate('Upload image') }}">
                            </div>
                            <div class="fmg-staged" id="files"></div>
                        </div>

                        <div class="tps-field mt-3">
                            <label class="tps-field__label" for="customZipFileUpload">{{ translate('messages.Upload zip file') }}</label>
                            <div class="fmg-drop fmg-drop--zip" id="fmg-zipzone">
                                <span class="fmg-drop__icon"><i class="tio-folder-add"></i></span>
                                <span>
                                    <span class="fmg-drop__title" id="zipFileLabel"
                                          data-empty="{{ translate('Choose a .zip archive') }}">{{ translate('Choose a .zip archive') }}</span>
                                    <span class="fmg-drop__hint d-block">{{ translate('Its images are extracted into this folder.') }}</span>
                                </span>
                                <input type="file" name="file" id="customZipFileUpload" accept=".zip"
                                       aria-label="{{ translate('messages.Upload zip file') }}">
                            </div>
                        </div>

                        <div class="btn--container justify-content-end mt-4">
                            <button type="reset" class="btn btn--reset" id="fmg-upload-reset">
                                <i class="tio-refresh"></i> {{ translate('messages.Reset') }}
                            </button>
                            <button type="submit" class="btn btn--primary" id="fmg-upload-submit" disabled>
                                <i class="tio-upload"></i> {{ translate('messages.Upload') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="fmg-help-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content tps">
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('How the gallery works') }}</h5>
                    <button type="button" class="close btn btn--reset btn-circle" data-dismiss="modal"
                            aria-label="{{ translate('messages.Close') }}">
                        <span aria-hidden="true" class="tio-clear fs-20 opacity-70"></span>
                    </button>
                </div>
                <div class="modal-body">
                    <ol class="tps-steps mb-3">
                        <li>{{ translate('Open a folder to see what it holds. The trail above the grid walks back up.') }}</li>
                        <li>{{ translate('Upload images into the folder you are looking at, or a zip archive to add many at once.') }}</li>
                        <li>{{ translate('Copy an image path when a setting or a record asks you to point at a stored file.') }}</li>
                        <li>{{ translate('Deleting removes the file from storage — anything still pointing at it will show a broken image.') }}</li>
                    </ol>
                    <div class="tps-note tps-note--muted">
                        <i class="tio-info-outined"></i>
                        <div>{{ translate('Only browsable image formats are listed here. Other files stay in storage but are not shown.') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <form id="fmg-delete-form" action="" method="post" class="d-none">
        @csrf
        @method('delete')
    </form>
@endsection

@push('script_2')
    <script src="{{ asset('public/assets/admin') }}/js/view-pages/file-manager.js"></script>
    <script>
        "use strict";

        window.fmgStrings = {
            confirmTitle: @json(translate('messages.Are you sure?')),
            confirmText: @json(translate('This file will be removed from storage.')),
            yes: @json(translate('messages.Yes')),
            no: @json(translate('messages.No')),
            copied: @json(translate('File path copied successfully!')),
        };
    </script>
@endpush
