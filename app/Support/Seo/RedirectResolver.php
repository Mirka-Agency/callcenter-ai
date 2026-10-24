<?php

namespace App\Support\Seo;

use App\Domain\Seo\RedirectStatus;
use App\Models\SeoRedirect;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Matches request paths against active redirect rules. Exact rules win over prefix rules,
 * and longer prefixes win over shorter ones. Rules are cached until a redirect changes.
 */
class RedirectResolver
{
    private const CACHE_KEY = 'seo:redirects';

    public const MAX_CHAIN = 10;

    public static function normalizePath(string $path): string
    {
        $path = trim($path);

        if (preg_match('#^https?://#i', $path)) {
            $path = (string) (parse_url($path, PHP_URL_PATH) ?? '/');
        }

        $path = rawurldecode(strtok($path, '?#') ?: '/');
        $path = '/'.ltrim($path, '/');
        $path = preg_replace('#/+#', '/', $path);
        $path = $path !== '/' ? rtrim($path, '/') : $path;

        if (str_ends_with($path, '/*')) {
            $path = substr($path, 0, -2) ?: '/';
        }

        return mb_strtolower($path);
    }

    /** @return array{id: int, target: string|null, status: int}|null */
    public function match(string $path): ?array
    {
        $path = self::normalizePath($path);
        $rules = $this->rules();

        if (isset($rules['exact'][$path])) {
            return $rules['exact'][$path];
        }

        foreach ($rules['prefix'] as $prefix => $rule) {
            if ($path === $prefix || str_starts_with($path, rtrim($prefix, '/').'/')) {
                if ($rule['target'] !== null && $rule['keep_suffix']) {
                    $rule['target'] = rtrim($rule['target'], '/').substr($path, strlen(rtrim($prefix, '/')));
                }

                return $rule;
            }
        }

        return null;
    }

    public function recordHit(int $id): void
    {
        rescue(fn () => DB::table('seo_redirects')->where('id', $id)->update([
            'hits' => DB::raw('hits + 1'),
            'last_hit_at' => now(),
        ]), report: false);
    }

    /**
     * Rejects rules that point to themselves or form a loop through other rules.
     *
     * @throws ValidationException
     */
    public function assertNoLoop(string $source, string $matchType, ?string $target, ?int $ignoreId = null): void
    {
        if ($target === null) {
            return;
        }

        $source = self::normalizePath($source);
        $seen = [$source];
        $next = $this->internalPath($target);

        for ($step = 0; $next !== null && $step < self::MAX_CHAIN; $step++) {
            $hitsSource = $matchType === SeoRedirect::PREFIX
                ? ($next === $source || str_starts_with($next, rtrim($source, '/').'/'))
                : $next === $source;

            if ($hitsSource || in_array($next, $seen, true)) {
                throw ValidationException::withMessages(['target_url' => __('seo.redirects.loop')]);
            }

            $seen[] = $next;
            $rule = SeoRedirect::query()
                ->where('is_active', true)
                ->where('source_path', $next)
                ->where('match_type', SeoRedirect::EXACT)
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->first();

            $next = $rule?->target_url ? $this->internalPath($rule->target_url) : null;
        }
    }

    /**
     * Point rules that redirect to $from directly at $to, so visitors never follow a chain.
     */
    public function flattenChainsTo(string $from, ?string $to, ?int $exceptId = null): void
    {
        if ($to === null) {
            return;
        }

        SeoRedirect::query()
            ->where('status_code', '!=', RedirectStatus::Gone->value)
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->get()
            ->filter(fn (SeoRedirect $rule): bool => $rule->target_url !== null && $this->internalPath($rule->target_url) === self::normalizePath($from))
            ->each(function (SeoRedirect $rule) use ($to): void {
                if (self::normalizePath($rule->source_path) !== $this->internalPath($to)) {
                    $rule->update(['target_url' => $to]);
                } else {
                    $rule->delete();
                }
            });
    }

    /** Path of $url when it points to this site, otherwise null. */
    public function internalPath(string $url): ?string
    {
        if (str_starts_with($url, '/')) {
            return self::normalizePath($url);
        }

        $host = parse_url($url, PHP_URL_HOST);
        $hosts = array_filter([parse_url(config('app.url'), PHP_URL_HOST), ...config('app.landing_hosts', []), request()?->getHost()]);

        return is_string($host) && in_array(strtolower($host), array_map('strtolower', $hosts), true)
            ? self::normalizePath((string) (parse_url($url, PHP_URL_PATH) ?? '/'))
            : null;
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array{exact: array<string, array{id: int, target: string|null, status: int, keep_suffix: bool}>, prefix: array<string, array{id: int, target: string|null, status: int, keep_suffix: bool}>} */
    private function rules(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            $rules = ['exact' => [], 'prefix' => []];

            SeoRedirect::query()->where('is_active', true)->get()->each(function (SeoRedirect $rule) use (&$rules): void {
                $rules[$rule->match_type === SeoRedirect::PREFIX ? 'prefix' : 'exact'][$rule->source_path] = [
                    'id' => $rule->id,
                    'target' => $rule->target_url,
                    'status' => $rule->status_code->value,
                    'keep_suffix' => $rule->match_type === SeoRedirect::PREFIX && $rule->target_url !== null && str_ends_with($rule->target_url, '/*'),
                ];
            });

            uksort($rules['prefix'], fn (string $a, string $b): int => strlen($b) <=> strlen($a));

            foreach ($rules['prefix'] as &$rule) {
                if ($rule['keep_suffix']) {
                    $rule['target'] = substr($rule['target'], 0, -2);
                }
            }

            return $rules;
        });
    }
}
