@extends('layouts.vendor.app')

@section('title',translate('Review list'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/item-reviews.css') }}">
@endpush

@section('content')
    <div class="content container-fluid rvw">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/star.png')}}" class="w--26" alt="">
                </span>
                <span>
                    {{translate('messages.Customers reviews')}}
                    <span class="badge badge-soft-dark ml-2">{{ $reviews->total() }}</span>
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('What customers said about your items, newest first.') }}</p>
        </div>
        <div id="review-index" data-ajax-region data-ajax-url="{{ url()->full() }}">
            <div class="card">
                <div class="card-header flex-wrap py-2 border-0">
                    <div class="search--button-wrapper justify-content-end">
                        @include('partials._table-head', [
                            'subtitle' => translate('messages.Ratings and comments customers left on your items.'),
                            'count'    => null,
                        ])


                        <form class="search-form">
                            <div class="input-group input--group">
                                <input name="search" type="search" value="{{ request()?->search }}" class="form-control h--40px" placeholder="{{ translate('Ex') . ' : ' . translate('Search by') }} {{ strtolower($module_item_label) }} {{ translate('Name') }}" aria-label="Search here">
                                <button type="submit" class="btn btn--secondary h--40px"><i class="tio-search"></i></button>
                            </div>
                        </form>
                        <div class="hs-unfold">
                            <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle btn export-btn font--sm"
                                href="javascript:;"
                                data-hs-unfold-options="{
                                    &quot;target&quot;: &quot;#usersExportDropdown&quot;,
                                    &quot;type&quot;: &quot;css-animation&quot;
                                }"
                                data-hs-unfold-target="#usersExportDropdown" data-hs-unfold-invoker="">
                                <i class="tio-download-to mr-1"></i> {{ translate('Export') }}
                            </a>

                            <div id="usersExportDropdown"
                                class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right hs-unfold-content-initialized hs-unfold-css-animation animated hs-unfold-reverse-y hs-unfold-hidden">

                                <span class="dropdown-header">{{ translate('Download options') }}</span>
                                <a id="export-excel" class="dropdown-item"
                                    href="{{ route('vendor.reviewsExport', ['export_type' => 'excel', request()->getQueryString()]) }}">
                                    <img class="avatar avatar-xss avatar-4by3 mr-2"
                                        src="{{ asset('public/assets/admin/svg/components/excel.svg') }}"
                                        alt="Image Description">
                                    Excel
                                </a>
                                <a id="export-csv" class="dropdown-item"
                                    href="{{ route('vendor.reviewsExport', ['export_type' => 'excel', request()->getQueryString()]) }}">
                                    <img class="avatar avatar-xss avatar-4by3 mr-2"
                                        src="{{ asset('public/assets/admin/svg/components/placeholder-csv-format.svg') }}"
                                        alt="Image Description">
                                    CSV
                                </a>

                            </div>
                        </div>
                    </div>
                </div>
                <div class="table-responsive datatable-custom">
                    <table id="columnSearchDatatable"
                            class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table"
                            data-hs-datatables-options='{
                                "order": [],
                                "orderCellsTop": true,
                                "paging": false
                            }'>
                        <thead class="thead-light">
                        <tr>
                            <th class="border-0">{{translate('Review ID')}}</th>
                            <th class="border-0">{{ $module_item_label }}</th>
                            <th class="border-0">{{translate('messages.Reviewer')}}</th>
                            <th class="border-0">{{translate('messages.review')}}</th>
                            <th class="border-0">{{translate('messages.Reply')}}</th>
                            <th class="border-0">{{translate('messages.Date')}}</th>
                            <th class="border-0">{{translate('messages.Visibility')}}</th>
                            @if($store_review_reply == '1')
                                <th class="text-center">{{translate('messages.Action')}}</th>
                            @endif
                        </tr>
                        </thead>

                        <tbody>
                        @foreach($reviews as $review)
                            @php($attachments = \App\CentralLogics\Helpers::decodeJsonToArray($review->attachment))
                            <tr>
                                <td><span class="rvw-id">{{$review->review_id}}</span></td>
                                <td>
                                    @if ($review->item)
                                        <div class="media align-items-center rvw-item">
                                            <img class="avatar avatar-lg mr-3 onerror-image"
                                                 src="{{ $review->item['image_full_url'] }}"
                                                 data-onerror-image="{{ asset('public/assets/admin/img/160x160/img1.jpg') }}"
                                                 alt="{{ $review->item->name }}">
                                            <div class="media-body cell--truncate">
                                                <a class="rvw-item__name" href="{{ route('vendor.item.view', [$review->item['id']]) }}">
                                                    {{ Str::limit($review->item['name'], 30) }}
                                                </a>
                                                @if($review->order_id)
                                                    <a class="rvw-meta" href="{{ route('vendor.order.details', ['id' => $review->order_id]) }}">
                                                        <i class="tio-receipt-outlined"></i> {{ translate('messages.Order ID') }}: {{ $review->order_id }}
                                                    </a>
                                                @endif
                                            </div>
                                        </div>
                                    @else
                                        {{ $is_service_review ? translate('messages.Service deleted!') : translate('messages.Food deleted!') }}
                                    @endif
                                </td>
                                <td>
                                    @if($review->customer)
                                        <div>
                                            <h5 class="d-block text-hover-primary mb-1">{{Str::limit($review->customer['f_name']." ".$review->customer['l_name'])}} </h5>
                                            <span class="d-block font-size-sm text-body">{{Str::limit($review->customer->phone)}}</span>
                                        </div>
                                    @else
                                        {{translate('No data found')}}
                                    @endif
                                </td>
                                <td class="rvw-cell--text">
                                    <div class="rvw-rating">
                                        <i class="tio-star"></i>
                                        <span class="rvw-rating__value">{{$review->rating}}</span>
                                    </div>
                                    @if($review->comment)
                                        <p class="rvw-comment" data-toggle="tooltip" data-placement="top"
                                           title="{{ $review->comment }}">{{ $review->comment }}</p>
                                    @else
                                        <span class="rvw-muted">{{ translate('messages.No comment left') }}</span>
                                    @endif
                                    @if(count($attachments))
                                        <span class="rvw-chip"><i class="tio-image"></i> {{ count($attachments) }}</span>
                                    @endif
                                </td>
                                <td class="rvw-cell--text">
                                    @if($review->reply)
                                        <p class="rvw-comment" data-toggle="tooltip" data-placement="top"
                                           title="{{ $review->reply }}">{{ $review->reply }}</p>
                                        @if($review->replied_at)
                                            <span class="rvw-meta">{{ \App\CentralLogics\Helpers::date_format($review->replied_at) }}</span>
                                        @endif
                                    @else
                                        <span class="rvw-pill rvw-pill--warn">{{ translate('messages.Awaiting reply') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="rvw-date">
                                        <span>{{ \App\CentralLogics\Helpers::date_format($review->created_at) }}</span>
                                        <span class="rvw-meta">{{ \App\CentralLogics\Helpers::time_format($review->created_at) }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="rvw-pill {{ $review->status ? '' : 'rvw-pill--warn' }}">
                                        {{ $review->status ? translate('messages.Visible') : translate('messages.Hidden') }}
                                    </span>
                                </td>
                                @if($store_review_reply == '1')
                                    <td>
                                        <div class="btn--container justify-content-center">
                                            <button type="button"
                                                    class="btn btn-sm {{ $review->reply ? 'btn-outline-primary' : 'btn--primary' }} rvw-reply-open"
                                                    data-toggle="modal" data-target="#review-reply-modal"
                                                    data-action="{{ route('vendor.review-reply', [$review->id]) }}"
                                                    data-review-id="{{ $review->review_id }}"
                                                    data-date="{{ \App\CentralLogics\Helpers::date_format($review->created_at) }}, {{ \App\CentralLogics\Helpers::time_format($review->created_at) }}"
                                                    data-item-name="{{ $review->item?->name ?? ($is_service_review ? translate('messages.Service deleted!') : translate('messages.Food deleted!')) }}"
                                                    data-item-image="{{ $review->item?->image_full_url }}"
                                                    data-order-id="{{ $review->order_id }}"
                                                    data-customer="{{ $review->customer ? $review->customer->f_name.' '.$review->customer->l_name : translate('No data found') }}"
                                                    data-rating="{{ $review->rating }}"
                                                    data-comment="{{ $review->comment }}"
                                                    data-attachments="{{ json_encode($review->attachment_full_url) }}"
                                                    data-reply="{{ $review->reply }}"
                                                    data-replied-at="{{ $review->replied_at ? \App\CentralLogics\Helpers::date_format($review->replied_at) : '' }}">
                                                <i class="{{ $review->reply ? 'tio-edit' : 'tio-send' }}"></i> {{ $review->reply ? translate('Edit reply') : translate('messages.Reply') }}
                                            </button>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    @if(count($reviews) !== 0)
                    <hr>
                    @endif
                    <table>
                        <tfoot>
                        {!! $reviews->links() !!}
                        </tfoot>
                    </table>
                    @if(count($reviews) === 0)
                    <div class="empty--data">
                        <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                        <h5>
                            {{translate('No data found')}}
                        </h5>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="modal fade" id="review-reply-modal" tabindex="-1" role="dialog" aria-labelledby="review-reply-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered rvw-reply-dialog" role="document">
                <div class="modal-content rvw-reply">
                    <form method="post" data-ajax-form data-ajax-close="#review-reply-modal" data-ajax-refresh="#review-index">
                        @csrf
                        <div class="modal-header rvw-reply__head">
                            <div class="rvw-reply__heading">
                                <h2 class="rvw-reply__title" id="review-reply-title">
                                    <span data-reply-mode="new">{{ translate('Reply to review') }}</span>
                                    <span data-reply-mode="edit" hidden>{{ translate('Edit reply') }}</span>
                                </h2>
                                <p class="rvw-reply__desc">
                                    <span class="rvw-id" data-reply-field="review-id"></span>
                                    <span data-reply-field="date"></span>
                                </p>
                            </div>
                            <button type="button" class="close rvw-reply__close" data-dismiss="modal" aria-label="{{ translate('messages.Close') }}">
                                <span class="tio-clear" aria-hidden="true"></span>
                            </button>
                        </div>
                        <div class="modal-body rvw-reply__body">
                            <section class="rvw-reply__review">
                                <div class="rvw-reply__item">
                                    <img class="rvw-reply__thumb onerror-image" data-reply-field="item-image" alt=""
                                         data-onerror-image="{{ asset('public/assets/admin/img/160x160/img1.jpg') }}">
                                    <div class="rvw-reply__item-body">
                                        <span class="rvw-reply__item-name" data-reply-field="item-name"></span>
                                        <span class="rvw-meta" data-reply-order>
                                            <i class="tio-receipt-outlined"></i> {{ translate('messages.Order ID') }}: <span data-reply-field="order-id"></span>
                                        </span>
                                    </div>
                                    <div class="rvw-reply__score">
                                        <span class="rvw-stars" data-reply-stars aria-hidden="true"></span>
                                        <span class="rvw-rating__value" data-reply-field="rating"></span>
                                    </div>
                                </div>
                                <div class="rvw-reply__author">
                                    <i class="tio-user-outlined"></i> <span data-reply-field="customer"></span>
                                </div>
                                <p class="rvw-reply__comment" data-reply-field="comment"></p>
                                <p class="rvw-muted mb-0" data-reply-no-comment hidden>{{ translate('messages.No comment left') }}</p>
                                <div class="rvw-reply__attachments" data-reply-attachments data-alt="{{ translate('messages.Review attachment') }}" hidden></div>
                            </section>
                            <div class="rvw-reply__field">
                                <label class="rvw-reply__label" for="review-reply-text">
                                    <span>{{ translate('messages.Your reply') }}</span>
                                    <span class="rvw-meta" data-reply-replied hidden>{{ translate('messages.Replied') }} <span data-reply-field="replied-at"></span></span>
                                </label>
                                <textarea id="review-reply-text" name="reply" class="form-control" rows="5" maxlength="65000" required
                                          placeholder="{{ translate('messages.Write your reply here') }}"></textarea>
                                <p class="rvw-reply__hint"><i class="tio-info-outined"></i> {{ translate('Customers see your reply below their review.') }}</p>
                            </div>
                        </div>
                        <div class="modal-footer rvw-reply__foot">
                            <button type="button" class="btn btn--reset" data-dismiss="modal"><i class="tio-clear-circle-outlined"></i> {{ translate('messages.Cancel') }}</button>
                            <button type="submit" class="btn btn--primary" data-reply-mode="new"><i class="tio-send"></i> {{ translate('messages.Send reply') }}</button>
                            <button type="submit" class="btn btn--primary" data-reply-mode="edit" hidden><i class="tio-save"></i> {{ translate('messages.Update reply') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('script_2')
    <script>
        "use strict";

        function initReviewDatatable($root) {
            $root.find('#columnSearchDatatable').each(function () {
                $.HSCore.components.HSDatatables.init($(this));
            });
        }

        $(document).on('ready', function () {
            initReviewDatatable($(document));
        });

        $(document).on('ajax:mounted', '#review-index', function () {
            initReviewDatatable($(this));
        });

        $(document).on('click', '.rvw-reply-open', function () {
            const $trigger = $(this);
            const $modal = $('#review-reply-modal');
            const $form = $modal.find('form');
            const field = name => $modal.find('[data-reply-field="' + name + '"]');
            const reply = $trigger.attr('data-reply') || '';
            const comment = $trigger.attr('data-comment') || '';
            const orderId = $trigger.attr('data-order-id') || '';
            const repliedAt = $trigger.attr('data-replied-at') || '';
            const rating = parseInt($trigger.attr('data-rating'), 10) || 0;
            let attachments = [];

            try {
                attachments = JSON.parse($trigger.attr('data-attachments') || '[]');
            } catch (error) {
                attachments = [];
            }

            $form.attr('action', $trigger.attr('data-action'));
            $form.find('.form-validation-error').remove();
            $form.find('.is-invalid').removeClass('is-invalid');

            $modal.find('[data-reply-mode="new"]').prop('hidden', reply !== '');
            $modal.find('[data-reply-mode="edit"]').prop('hidden', reply === '');

            field('review-id').text($trigger.attr('data-review-id'));
            field('date').text($trigger.attr('data-date'));
            field('item-name').text($trigger.attr('data-item-name'));
            field('item-image').attr({src: $trigger.attr('data-item-image'), alt: $trigger.attr('data-item-name')});
            field('order-id').text(orderId);
            $modal.find('[data-reply-order]').prop('hidden', orderId === '');
            field('customer').text($trigger.attr('data-customer'));
            field('rating').text(rating);
            field('comment').text(comment).prop('hidden', comment === '');
            $modal.find('[data-reply-no-comment]').prop('hidden', comment !== '');

            const $stars = $modal.find('[data-reply-stars]').empty();
            for (let star = 1; star <= 5; star++) {
                $stars.append($('<i>', {class: star <= rating ? 'tio-star' : 'tio-star-outlined'}));
            }

            const $attachments = $modal.find('[data-reply-attachments]').empty().prop('hidden', attachments.length === 0);
            $.each(attachments, function (index, url) {
                $('<a>', {href: url, target: '_blank', rel: 'noopener'})
                    .append($('<img>', {src: url, alt: $attachments.attr('data-alt')}))
                    .appendTo($attachments);
            });

            field('replied-at').text(repliedAt);
            $modal.find('[data-reply-replied]').prop('hidden', repliedAt === '');
            $('#review-reply-text').val(reply);
        });

        $('#review-reply-modal').on('shown.bs.modal', function () {
            $('#review-reply-text').trigger('focus');
        });
    </script>
@endpush
