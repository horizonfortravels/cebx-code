@props(['name' => 'dashboard'])

@switch($name)
    @case('individual')
        <svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.75', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
            <path d="M12 12.25a3.25 3.25 0 1 0 0-6.5 3.25 3.25 0 0 0 0 6.5Z"></path>
            <path d="M5.75 19.25a6.25 6.25 0 0 1 12.5 0"></path>
            <path d="M16.75 7.75h2.5"></path>
            <path d="M18 6.5v2.5"></path>
        </svg>
        @break

    @case('organization')
        <svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.75', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
            <path d="M5.75 19.25h12.5"></path>
            <path d="M7.25 19.25V6.75a1.5 1.5 0 0 1 1.5-1.5h6.5a1.5 1.5 0 0 1 1.5 1.5v12.5"></path>
            <path d="M10 8.5h1.25"></path>
            <path d="M12.75 8.5H14"></path>
            <path d="M10 11.5h1.25"></path>
            <path d="M12.75 11.5H14"></path>
            <path d="M10 14.5h4"></path>
        </svg>
        @break

    @case('internal')
    @case('admin')
        <svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.75', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
            <path d="M12 4.75 18.25 7v4.5c0 3.9-2.47 7.11-6.25 8.5-3.78-1.39-6.25-4.6-6.25-8.5V7z"></path>
            <path d="M9.5 12.25 11 13.75l3.5-3.5"></path>
        </svg>
        @break

    @case('dashboard')
        <svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.75', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
            <rect x="3.75" y="3.75" width="7.5" height="7.5" rx="1.5"></rect>
            <rect x="12.75" y="3.75" width="7.5" height="5.25" rx="1.5"></rect>
            <rect x="3.75" y="12.75" width="7.5" height="7.5" rx="1.5"></rect>
            <rect x="12.75" y="10.5" width="7.5" height="9.75" rx="1.5"></rect>
        </svg>
        @break

    @case('shipments')
        <svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.75', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
            <path d="M4.75 8.5 12 4.75l7.25 3.75v7L12 19.25l-7.25-3.75z"></path>
            <path d="M12 10.25 4.75 6.5"></path>
            <path d="M12 10.25 19.25 6.5"></path>
            <path d="M12 10.25v9"></path>
        </svg>
        @break

    @case('orders')
        <svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.75', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
            <path d="M7 6.75h10"></path>
            <path d="M7 11.5h10"></path>
            <path d="M7 16.25h6.5"></path>
            <path d="M5.25 4.75h13.5A1.5 1.5 0 0 1 20.25 6.25v11.5a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5V6.25a1.5 1.5 0 0 1 1.5-1.5Z"></path>
        </svg>
        @break

    @case('reports')
        <svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.75', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
            <path d="M5.25 19.25h13.5"></path>
            <path d="M8 17v-4.5"></path>
            <path d="M12 17V8"></path>
            <path d="M16 17v-6.5"></path>
        </svg>
        @break

    @case('wallet')
        <svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.75', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
            <path d="M5 8.5A2.75 2.75 0 0 1 7.75 5.75h8.5A2.75 2.75 0 0 1 19 8.5v7A2.75 2.75 0 0 1 16.25 18.25h-8.5A2.75 2.75 0 0 1 5 15.5z"></path>
            <path d="M15.75 11.25h3.5v2.5h-3.5a1.25 1.25 0 1 1 0-2.5Z"></path>
            <path d="M8 8.5h6.75"></path>
        </svg>
        @break

    @case('users')
        <svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.75', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
            <path d="M12 12.25a3.25 3.25 0 1 0 0-6.5 3.25 3.25 0 0 0 0 6.5Z"></path>
            <path d="M5.75 19.25a6.25 6.25 0 0 1 12.5 0"></path>
        </svg>
        @break

    @case('roles')
        <svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.75', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
            <path d="M12 4.75 18 7v4.75c0 3.46-2.27 6.59-6 7.5-3.73-.91-6-4.04-6-7.5V7z"></path>
            <path d="M9.5 12h5"></path>
        </svg>
        @break

    @case('developer')
        <svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.75', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
            <path d="m8.25 8.5-4 3.5 4 3.5"></path>
            <path d="m15.75 8.5 4 3.5-4 3.5"></path>
            <path d="m13.25 5.75-2.5 12.5"></path>
        </svg>
        @break

    @case('integrations')
        <svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.75', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
            <path d="M8 8.5h3.5v3.5H8z"></path>
            <path d="M12.5 12h3.5v3.5h-3.5z"></path>
            <path d="M11.5 10.25h1"></path>
            <path d="M10.25 11.5v1"></path>
            <path d="M13.75 13.75v1"></path>
            <path d="M12.5 15h1"></path>
            <path d="M6.25 6.75h11.5A1.5 1.5 0 0 1 19.25 8.25v7.5a1.5 1.5 0 0 1-1.5 1.5H6.25a1.5 1.5 0 0 1-1.5-1.5v-7.5a1.5 1.5 0 0 1 1.5-1.5Z"></path>
        </svg>
        @break

    @case('api-key')
    @case('api-keys')
        <svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.75', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
            <circle cx="8.5" cy="11.5" r="2.75"></circle>
            <path d="M11.25 11.5h8"></path>
            <path d="M16 11.5v2"></path>
            <path d="M18.5 11.5v2"></path>
        </svg>
        @break

    @case('webhooks')
        <svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.75', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
            <path d="M7.25 8.75a3 3 0 0 1 3-3H11"></path>
            <path d="M16.75 15.25a3 3 0 0 1-3 3H13"></path>
            <path d="M8.75 15.25h6.5"></path>
            <path d="m12.25 12.75 3-3"></path>
            <path d="m11.75 11.25-3 3"></path>
        </svg>
        @break

    @case('trend')
        <svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.75', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
            <path d="M4.75 18.5h14.5"></path>
            <path d="m6.5 15.75 3-3 3 1.75 5-5"></path>
            <path d="m14.5 9.5 3-.75-.75 3"></path>
        </svg>
        @break

    @case('activity')
        <svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.75', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
            <path d="M4.75 12h3l1.75-4 3 8 1.75-4h5"></path>
        </svg>
        @break

    @case('alert')
        <svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.75', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
            <path d="M12 5.5v5"></path>
            <path d="M12 14.5v.01"></path>
            <path d="M4.75 18.25 11 6.25a1.13 1.13 0 0 1 2 0l6.25 12a1.13 1.13 0 0 1-1 1.75H5.75a1.13 1.13 0 0 1-1-1.75Z"></path>
        </svg>
        @break

    @case('team')
        <svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.75', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
            <path d="M8.25 11a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z"></path>
            <path d="M15.75 12.5a2.25 2.25 0 1 0 0-4.5 2.25 2.25 0 0 0 0 4.5Z"></path>
            <path d="M4.75 18.25a4 4 0 0 1 7 0"></path>
            <path d="M13 18.25a3.5 3.5 0 0 1 6 0"></path>
        </svg>
        @break

    @case('hold')
        <svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.75', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
            <rect x="5.75" y="10.25" width="12.5" height="8.5" rx="1.75"></rect>
            <path d="M8.25 10.25V8.5a3.75 3.75 0 0 1 7.5 0v1.75"></path>
        </svg>
        @break

    @default
        <svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.75', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
            <circle cx="12" cy="12" r="8"></circle>
            <path d="M12 8.5v7"></path>
            <path d="M8.5 12H15.5"></path>
        </svg>
@endswitch
