@foreach ($bundleGroups['bundles'] as $bundle)
    <div class="modal fade" id="{{ $bundle['modal_id'] }}" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        {{ $bundle['name'] }}
                        <span class="badge badge-soft-info ml-1">{{ translate('messages.Bundle') }}</span>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="d-flex justify-content-between fs-12 mb-3">
                        <span class="text-muted">{{ translate('messages.QTY') }} :
                            <strong>{{ $bundle['quantity'] }}</strong></span>
                        <span class="text-muted">
                            {{ \App\CentralLogics\Helpers::format_currency($bundle['unit_price']) }}
                            {{ translate('messages.per bundle') }}
                        </span>
                        <span><strong>{{ \App\CentralLogics\Helpers::format_currency($bundle['total']) }}</strong></span>
                    </div>

                    @foreach ($bundle['lines'] as $line)
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <img width="36" height="36" class="rounded border onerror-image"
                                src="{{ $line['image'] ?? asset('public/assets/admin/img/100x100/2.png') }}"
                                data-onerror-image="{{ asset('public/assets/admin/img/100x100/2.png') }}"
                                alt="{{ $line['name'] }}">
                            <div class="d-flex flex-column">
                                <span class="fs-12 text-dark">{{ $line['name'] }}</span>
                                <span class="fs-10 text-muted">
                                    {{ translate('messages.QTY') }} : {{ $line['quantity'] }}
                                    &middot; {{ \App\CentralLogics\Helpers::format_currency($line['price']) }}
                                </span>
                            </div>
                        </div>
                    @endforeach

                    @if ($bundle['discount'] > 0)
                        <div class="d-flex justify-content-between fs-12 border-top pt-2 mt-3">
                            <span class="text-muted">{{ translate('Bundle discount') }}</span>
                            <strong>-{{ \App\CentralLogics\Helpers::format_currency($bundle['discount']) }}</strong>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endforeach
