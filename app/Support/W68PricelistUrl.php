<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

class W68PricelistUrl
{
    // W68_HOSTFORGE_PRICELIST_DOMAIN_FIX_20260917
    private const HOSTFORGE_PRICELIST_URL = 'https://pricelist-w68autoparts-lisxk.hostforgeplatforms.com';

    public static function baseUrl(?Request $request = null): string
    {
        $configured = trim((string) Config::get('services.w68_pricelist.url', ''));

        // Keep an explicitly configured external Pricelist URL, except for the
        // old HostForge W68 URL that still points to /w68_Pricelist/public.
        if ($configured !== '' && !self::isLocalUrl($configured)) {
            if (!self::isLegacyHostforgePricelistUrl($configured)) {
                return rtrim($configured, '/');
            }
        }

        $request ??= request();
        $path = self::pathFromConfiguredUrl($configured) ?: '/w68_Pricelist/public';
        $scheme = $request?->getScheme()
            ?: (parse_url((string) Config::get('app.url', ''), PHP_URL_SCHEME) ?: 'http');
        $host = $request?->getHost()
            ?: (parse_url((string) Config::get('app.url', ''), PHP_URL_HOST) ?: 'localhost');

        // Production W68 is now separate from the W68 Pricelist application.
        // Admin, Regular and Special customer QR/link generation must point
        // to the dedicated Pricelist HostForge deployment.
        if (self::isW68HostforgeAppHost($host)) {
            return self::HOSTFORGE_PRICELIST_URL;
        }

        if (self::isLocalHost($host)) {
            $host = self::serverNetworkHost($request) ?: $host;
        }

        $port = $request?->getPort();
        $hostWithPort = self::hostWithPort($host, $port, $scheme);

        return rtrim($scheme . '://' . $hostWithPort . $path, '/');
    }

    private static function isLegacyHostforgePricelistUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        return self::isW68HostforgeAppHost($host)
            && str_starts_with(
                '/' . ltrim($path, '/'),
                '/w68_pricelist/public'
            );
    }

    private static function isW68HostforgeAppHost(?string $host): bool
    {
        $host = strtolower(trim((string) $host));

        return $host === 'w68autoparts.hostforgeplatforms.com';
    }

    private static function pathFromConfiguredUrl(string $configured): string
    {
        $path = parse_url($configured, PHP_URL_PATH);
        return $path && $path !== '/' ? $path : '';
    }

    private static function isLocalUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (!$host) {
            return true;
        }

        return self::isLocalHost($host);
    }

    private static function isLocalHost(?string $host): bool
    {
        $host = strtolower(trim((string) $host, '[] '));

        return in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true);
    }

    private static function serverNetworkHost(?Request $request): ?string
    {
        $candidates = [
            $request?->server('SERVER_ADDR'),
            gethostbyname(gethostname()),
        ];

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '' && !self::isLocalHost($candidate) && filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }

        return null;
    }

    private static function hostWithPort(string $host, ?int $port, string $scheme): string
    {
        $defaultPort = strtolower($scheme) === 'https' ? 443 : 80;
        $needsPort = $port && $port !== $defaultPort;

        if (str_contains($host, ':') && !str_starts_with($host, '[')) {
            $host = '[' . trim($host, '[]') . ']';
        }

        return $needsPort ? $host . ':' . $port : $host;
    }
}
