<?php

namespace App\Http\Middleware;

use App\Support\Seo\RedirectResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies admin-defined redirect rules (301/302/307/308) and 410 Gone responses
 * before routing, so old URLs work even when no route matches them.
 */
class HandleSeoRedirects
{
    private const SKIPPED_PREFIXES = ['admin', 'livewire', 'filament', 'build', 'storage', 'up', 'webhooks', 'broadcasting'];

    public function __construct(private readonly RedirectResolver $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }

        $first = strtolower(explode('/', trim($request->path(), '/'))[0] ?? '');

        if (in_array($first, self::SKIPPED_PREFIXES, true)) {
            return $next($request);
        }

        $rule = rescue(fn () => $this->resolver->match('/'.$request->path()), null, report: false);

        if ($rule === null) {
            return $next($request);
        }

        $this->resolver->recordHit($rule['id']);

        if ($rule['status'] === 410 || $rule['target'] === null) {
            return response()->view('errors.410', [], 410);
        }

        $target = $rule['target'];

        if ($request->getQueryString() && ! str_contains($target, '?')) {
            $target .= '?'.$request->getQueryString();
        }

        return redirect()->to($target, $rule['status']);
    }
}
