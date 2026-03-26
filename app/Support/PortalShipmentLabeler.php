<?php

namespace App\Support;

use Illuminate\Support\Str;

final class PortalShipmentLabeler
{
    public static function carrier(?string $carrierCode, ?string $fallback = null): string
    {
        return self::resolveLabel(
            'portal_shipments.carriers.' . self::normalizeTokenKey($carrierCode),
            $fallback
        );
    }

    public static function service(?string $serviceCode, ?string $fallback = null): string
    {
        return self::resolveLabel(
            'portal_shipments.services.' . self::normalizeTokenKey($serviceCode),
            $fallback ?: self::humanizeToken($serviceCode)
        );
    }

    public static function carrierServicePair(
        ?string $carrierCode,
        ?string $serviceCode,
        ?string $carrierFallback = null,
        ?string $serviceFallback = null
    ): string {
        $carrier = self::carrier($carrierCode, $carrierFallback);
        $service = self::service($serviceCode, $serviceFallback);
        $notAvailable = __('portal_shipments.common.not_available');

        if ($carrier === $notAvailable && $service === $notAvailable) {
            return $notAvailable;
        }

        if ($carrier === $notAvailable) {
            return $service;
        }

        if ($service === $notAvailable) {
            return $carrier;
        }

        if ($carrier === $service) {
            return $carrier;
        }

        return $carrier . ' / ' . $service;
    }

    public static function status(?string $status, ?string $fallback = null): string
    {
        $normalized = CanonicalShipmentStatus::normalize($status);
        $translated = __('portal_shipments.statuses.' . self::normalizeTokenKey($normalized));

        if ($translated !== 'portal_shipments.statuses.' . self::normalizeTokenKey($normalized)) {
            return $translated;
        }

        return $fallback && trim($fallback) !== '' && trim($fallback) !== trim((string) $status)
            ? $fallback
            : __('portal_shipments.common.not_available');
    }

    public static function event(?string $eventType, ?string $fallback = null): string
    {
        return self::resolveLabel(
            'portal_shipments.events.' . self::normalizeTokenKey($eventType),
            $fallback ?: __('portal_shipments.common.not_available')
        );
    }

    public static function documentType(?string $documentType, ?string $fallback = null): string
    {
        return self::resolveLabel(
            'portal_shipments.documents.types.' . self::normalizeTokenKey($documentType),
            $fallback ?: __('portal_shipments.documents.types.other')
        );
    }

    public static function documentFormat(?string $format, ?string $fallback = null): string
    {
        return self::resolveLabel(
            'portal_shipments.documents.formats.' . self::normalizeTokenKey($format),
            $fallback ?: self::humanizeToken($format)
        );
    }

    public static function retrievalMode(?string $retrievalMode, ?string $fallback = null): string
    {
        return self::resolveLabel(
            'portal_shipments.documents.retrieval_modes.' . self::normalizeTokenKey($retrievalMode),
            $fallback ?: __('portal_shipments.common.not_available')
        );
    }

    public static function source(?string $source, ?string $fallback = null): string
    {
        return self::resolveLabel(
            'portal_shipments.sources.' . self::normalizeTokenKey($source),
            $fallback ?: __('portal_shipments.common.not_available')
        );
    }

    public static function location(?string $location, ?string $fallback = null): string
    {
        $normalized = trim((string) $location);

        if ($normalized === '') {
            $fallback = trim((string) $fallback);

            return $fallback !== ''
                ? $fallback
                : __('portal_shipments.common.not_available');
        }

        return self::carrier($normalized, $fallback ?: $normalized);
    }

    private static function resolveLabel(string $translationKey, ?string $fallback = null): string
    {
        $translated = __($translationKey);

        if ($translated !== $translationKey) {
            return $translated;
        }

        $fallback = trim((string) $fallback);

        return $fallback !== ''
            ? $fallback
            : __('portal_shipments.common.not_available');
    }

    private static function normalizeTokenKey(?string $value): string
    {
        return Str::of((string) $value)
            ->trim()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/u', '_')
            ->trim('_')
            ->value();
    }

    private static function humanizeToken(?string $value): string
    {
        $normalized = trim((string) $value);

        if ($normalized === '') {
            return __('portal_shipments.common.not_available');
        }

        return Str::of($normalized)
            ->replace(['.', '_', '-'], ' ')
            ->squish()
            ->title()
            ->value();
    }
}
