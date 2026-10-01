<?php

namespace App\Controller;

use App\Model\NewsModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * SAMPLE CONTROLLER — お知らせ list and detail pages.
 *
 *   routes/web.php  →  News::index() / News::detail($slug)  →  NewsModel  →  View/frontend/news/*.blade.php
 *
 * Copy it for your own pages (e.g. Store.php + StoreModel.php + View/frontend/store/).
 */
class News extends FrontController
{
    /**
     * /news — list with pagination
     */
    public function index(): string
    {
        // Until the content type exists, show how to create it (only needed in this sample).
        if (! content_type_exists('news')) {
            return $this->render('frontend.news.setup');
        }

        $news = model(NewsModel::class);

        return $this->render('frontend.news.index', [
            'items' => $news->paginated((int) setting('posts_per_page', 10)),
            'pager' => $news->pager,
        ]);
    }

    /**
     * /news/{slug} — one item
     */
    public function detail(string $slug): string
    {
        $item = content_type_exists('news') ? model(NewsModel::class)->findBySlug($slug) : null;

        if ($item === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->render('frontend.news.detail', ['item' => $item]);
    }
}
