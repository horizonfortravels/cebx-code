@if($result)
    <div
        class="shipment-validation-card"
        style="border-color: {{ $style['border'] }}; background: {{ $style['background'] }};"
        data-testid="{{ $prefix }}-address-validation"
    >
        <div class="shipment-validation-card__title" style="color: {{ $style['color'] }};">{{ data_get($result, 'title') }}</div>
        <div class="shipment-inline-meta">{{ data_get($result, 'message') }}</div>

        @if(data_get($result, 'changes'))
            <div class="shipment-validation-card__changes">
                @foreach(data_get($result, 'changes', []) as $change)
                    <div class="shipment-validation-card__change">
                        <div class="shipment-validation-card__label">{{ data_get($labels, data_get($change, 'field_key'), data_get($change, 'field_key')) }}</div>
                        <div class="shipment-validation-card__value">
                            {{ data_get($change, 'suggested') }}
                            @if(filled(data_get($change, 'original')))
                                <span class="shipment-inline-meta">/ {{ data_get($change, 'original') }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @if(data_get($result, 'warnings'))
            <ul class="shipment-inline-meta" style="margin: 0; padding-right: 18px;">
                @foreach(data_get($result, 'warnings', []) as $warning)
                    <li>{{ data_get($warning, 'message') }}</li>
                @endforeach
            </ul>
        @endif

        @if(data_get($result, 'classification') === 'normalized_suggestion')
            @foreach(data_get($result, 'normalized', []) as $field => $value)
                <input type="hidden" name="address_validation_suggestions[{{ $prefix }}][{{ $field }}]" value="{{ $value ?? '' }}">
            @endforeach
            <div class="shipment-form-actions">
                <button type="submit" class="btn btn-s" formaction="{{ route($portalConfig['address_validation_route']) }}" name="address_validation_action" value="apply_{{ $prefix }}" data-testid="apply-{{ $prefix }}-address-suggestion">{{ __('portal_shipments.address_validation.accept') }}</button>
                <button type="submit" class="btn btn-s" formaction="{{ route($portalConfig['address_validation_route']) }}" name="address_validation_action" value="dismiss_{{ $prefix }}" data-testid="dismiss-{{ $prefix }}-address-suggestion">{{ __('portal_shipments.address_validation.keep_original') }}</button>
            </div>
        @endif
    </div>
@endif
