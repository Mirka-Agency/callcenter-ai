<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Remembers the first marketing touch (UTM parameters or external referrer) for the visitor's session,
 * so lead forms submitted later can be attributed to a channel.
 */
class CaptureLeadAttribution
{
    public const SESSION_KEY = 'lead_attribution';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && $request->hasSession() && ! $request->session()->has(self::SESSION_KEY)) {
            $attribution = $this->fromRequest($request);

            if ($attribution !== null) {
                $request->session()->put(self::SESSION_KEY, $attribution);
            }
        }

        return $next($request);
    }

    /** @return array{source: string, utm_source: string|null, utm_medium: string|null, utm_campaign: string|null}|null */
    private function fromRequest(Request $request): ?array
    {
        $utm = [
            'utm_source' => $this->clean($request->query('utm_source')),
            'utm_medium' => $this->clean($request->query('utm_medium')),
            'utm_campaign' => $this->clean($request->query('utm_campaign')),
        ];

        if ($utm['utm_source'] !== null) {
            return ['source' => $utm['utm_source'], ...$utm];
        }

        $referrerHost = parse_url((string) $request->headers->get('referer'), PHP_URL_HOST);

        if (is_string($referrerHost) && $referrerHost !== '' && $referrerHost !== $request->getHost()) {
            return ['source' => mb_substr(preg_replace('/^www\./', '', $referrerHost), 0, 100), ...$utm];
        }

        return null;
    }

    private function clean(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? mb_substr(trim($value), 0, 100) : null;
    }

    /** @return array{source: string, utm_source: string|null, utm_medium: string|null, utm_campaign: string|null} */
    public static function forLead(Request $request): array
    {
        $stored = $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null;

        return is_array($stored)
            ? array_merge(['source' => 'direct', 'utm_source' => null, 'utm_medium' => null, 'utm_campaign' => null], $stored)
            : ['source' => 'direct', 'utm_source' => null, 'utm_medium' => null, 'utm_campaign' => null];
    }
}
