<?php

namespace App\Http\Controllers;

use App\Application\Blog\PublicBlogQuery;
use App\Support\LandingHost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function __construct(
        private readonly PublicBlogQuery $blog,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->redirectGuestsAwayFromLanding($request)) {
            return $redirect;
        }

        $category = $this->blog->resolveCategorySlug($request->string('category')->toString() ?: null);
        $query = trim($request->string('q')->limit(80, '')->toString());
        $page = max(1, $request->integer('page', 1));
        $posts = $this->blog->paginate($category, $query !== '' ? $query : null, $page);

        if ($posts->currentPage() > $posts->lastPage()) {
            return redirect()->route('blog', array_filter([
                'category' => $category,
                'q' => $query !== '' ? $query : null,
                'page' => $posts->lastPage() > 1 ? $posts->lastPage() : null,
            ]));
        }

        return view('blog', [
            'posts' => $posts,
            'categories' => $this->blog->filters(),
            'activeCategory' => $category,
            'query' => $query,
        ]);
    }

    public function show(Request $request, string $slug): View|RedirectResponse
    {
        if ($redirect = $this->redirectGuestsAwayFromLanding($request)) {
            return $redirect;
        }

        $post = $this->blog->findPublishedBySlug($slug);
        abort_if($post === null, 404);

        return view('blog-show', [
            'post' => $post,
            'related' => $this->blog->related($slug),
        ]);
    }

    private function redirectGuestsAwayFromLanding(Request $request): ?RedirectResponse
    {
        if (LandingHost::matches($request)) {
            return null;
        }

        return redirect()->route('login');
    }
}
