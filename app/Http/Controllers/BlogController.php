<?php

namespace App\Http\Controllers;

use App\Application\Blog\PublicBlogQuery;
use App\Support\LandingHost;
use App\Support\Seo\BlogSeo;
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

        $legacyCategory = $this->blog->resolveCategorySlug($request->string('category')->toString() ?: null);

        if ($legacyCategory !== null) {
            return redirect()->route('blog.category', array_filter([
                'slug' => $legacyCategory,
                'q' => $request->string('q')->toString() ?: null,
                'page' => $request->integer('page') > 1 ? $request->integer('page') : null,
            ]), 301);
        }

        return $this->listing($request, null);
    }

    public function category(Request $request, string $slug): View|RedirectResponse
    {
        if ($redirect = $this->redirectGuestsAwayFromLanding($request)) {
            return $redirect;
        }

        $category = $this->blog->findCategory($slug);
        abort_if($category === null, 404);

        return $this->listing($request, $category);
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
            'seo' => BlogSeo::post($post),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $category
     */
    private function listing(Request $request, ?array $category): View|RedirectResponse
    {
        $query = trim($request->string('q')->limit(80, '')->toString());
        $page = max(1, $request->integer('page', 1));
        $posts = $this->blog->paginate($category['slug'] ?? null, $query !== '' ? $query : null, $page);

        if ($posts->currentPage() > $posts->lastPage()) {
            $base = $category ? route('blog.category', $category['slug']) : route('blog');
            $params = array_filter(['q' => $query !== '' ? $query : null, 'page' => $posts->lastPage() > 1 ? $posts->lastPage() : null]);

            return redirect()->to($params === [] ? $base : $base.'?'.http_build_query($params));
        }

        return view('blog', [
            'posts' => $posts,
            'categories' => $this->blog->filters(),
            'activeCategory' => $category['slug'] ?? null,
            'category' => $category,
            'query' => $query,
            'seo' => $category ? BlogSeo::category($category, $posts, $query) : BlogSeo::index($posts, $query),
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
