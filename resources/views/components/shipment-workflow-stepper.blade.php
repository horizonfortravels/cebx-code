@props([
    'current' => 'create',
    'createRoute' => null,
    'offersRoute' => null,
    'declarationRoute' => null,
    'showRoute' => null,
    'documentsRoute' => null,
    'stateOverrides' => [],
])

@php
    $steps = [
        'create' => [
            'label' => 'طلب الشحنة',
            'caption' => 'البيانات والتحقق',
            'route' => $createRoute,
        ],
        'offers' => [
            'label' => 'العروض',
            'caption' => 'المقارنة والاختيار',
            'route' => $offersRoute,
        ],
        'declaration' => [
            'label' => 'إقرار المحتوى',
            'caption' => 'التصريح القانوني',
            'route' => $declarationRoute,
        ],
        'show' => [
            'label' => 'الإصدار والمتابعة',
            'caption' => 'المحفظة والتتبع',
            'route' => $showRoute,
        ],
        'documents' => [
            'label' => 'الوثائق',
            'caption' => 'التنزيل والطباعة',
            'route' => $documentsRoute,
        ],
    ];

    $orderedKeys = array_keys($steps);
    $currentIndex = array_search($current, $orderedKeys, true);
@endphp

<nav {{ $attributes->class('shipment-stepper') }} aria-label="مراحل رحلة الشحنة">
    @foreach($steps as $key => $step)
        @php
            $index = array_search($key, $orderedKeys, true);
            $state = $stateOverrides[$key]
                ?? match (true) {
                    $currentIndex === false => 'upcoming',
                    $index < $currentIndex => 'complete',
                    $index === $currentIndex => 'current',
                    default => 'upcoming',
                };

            $tag = filled($step['route']) && $state !== 'current' ? 'a' : 'div';
        @endphp
        <{{ $tag }}
            @if($tag === 'a') href="{{ $step['route'] }}" @endif
            class="shipment-stepper__item shipment-stepper__item--{{ $state }}"
            @if($state === 'current') aria-current="step" @endif
        >
            <span class="shipment-stepper__index">{{ str_pad((string) ($loop->iteration), 2, '0', STR_PAD_LEFT) }}</span>
            <span class="shipment-stepper__copy">
                <span class="shipment-stepper__label">{{ $step['label'] }}</span>
                <span class="shipment-stepper__caption">{{ $step['caption'] }}</span>
            </span>
        </{{ $tag }}>
    @endforeach
</nav>
