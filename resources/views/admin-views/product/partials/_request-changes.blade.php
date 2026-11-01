<div class="card mb-3">
    <div class="idt-card__head">
        <h2 class="idt-card__title">{{ translate('messages.What this request changes') }}</h2>
        @if(count($changes))
            <span class="idt-card__hint">
                {{ translate('messages.Fields that differ from the published item') }}: {{ count($changes) }}
            </span>
        @endif
    </div>

    @if(count($changes))
        <div class="idt-diff">
            @foreach($changes as $change)
                <div class="idt-diff__row">
                    <span class="idt-diff__label">{{ $change['label'] }}</span>

                    @if($change['kind'] === 'image')
                        <img class="idt-diff__thumb onerror-image" src="{{ $change['from'] }}"
                             data-onerror-image="{{ asset('public/assets/admin/img/160x160/img2.jpg') }}"
                             alt="{{ translate('messages.Published') }}">
                        <i class="idt-diff__arrow tio-arrow-forward" aria-hidden="true"></i>
                        <img class="idt-diff__thumb onerror-image" src="{{ $change['to'] }}"
                             data-onerror-image="{{ asset('public/assets/admin/img/160x160/img2.jpg') }}"
                             alt="{{ translate('messages.Requested') }}">
                    @elseif($change['kind'] === 'edited')
                        <span class="idt-diff__from">{{ translate('messages.Published version') }}</span>
                        <i class="idt-diff__arrow tio-arrow-forward" aria-hidden="true"></i>
                        <span class="idt-diff__to">
                            <span class="idt-diff__badge"><i class="tio-edit"></i> {{ translate('messages.Edited') }}</span>
                        </span>
                    @else
                        <span class="idt-diff__from" title="{{ $change['from'] }}">
                            {{ $change['from'] !== '' ? Str::limit($change['from'], 120) : '—' }}
                        </span>
                        <i class="idt-diff__arrow tio-arrow-forward" aria-hidden="true"></i>
                        <span class="idt-diff__to" title="{{ $change['to'] }}">
                            {{ $change['to'] !== '' ? Str::limit($change['to'], 120) : '—' }}
                        </span>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        <div class="idt-empty">{{ translate('messages.Nothing in this request differs from the published item.') }}</div>
    @endif
</div>
