<div class="rp-perm" data-rp-perm>
    <div class="rp-perm__head">
        <h5 class="rp-perm__title">{{ $card['label'] }}</h5>
        <span class="rp-count" data-rp-perm-count>0/{{ count($card['items']) }}</span>
        <label class="rp-check rp-check--master">
            <input type="checkbox" data-rp-perm-all id="{{ $cardId }}-all">
            <span class="rp-check__label">{{ translate('Select all') }}</span>
        </label>
    </div>
    <div class="rp-perm__body">
        <div class="rp-grid">
            @foreach ($card['items'] as $key => $labelKey)
                @php($label = translate($labelKey))
                <div class="rp-item" data-rp-item data-rp-search="{{ \Illuminate\Support\Str::lower($label.' '.str_replace(['_', '-'], ' ', $key)) }}">
                    <label class="rp-check">
                        <input type="checkbox" name="modules[]" value="{{ $key }}" id="cr_{{ $cardId }}_{{ $key }}"
                            data-rp-input @checked(in_array($key, $selectedModules))>
                        <span class="rp-check__label" data-rp-label>{{ $label }}</span>
                    </label>
                </div>
            @endforeach
        </div>
    </div>
</div>
