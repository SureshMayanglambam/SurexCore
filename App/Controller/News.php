<?php

namespace App\Controller;

use App\Model\NewsModel;

class News extends FrontController
{
    public function index(): string
    {
        $posts = NewsModel::published()->latest()->paginate(10);

        return $this->render('frontend.news.index', compact('posts'));
    }

    public function detail(string $slug): string
    {
        $post = NewsModel::published()->where('slug', $slug)->firstOrFail();

        return $this->render('frontend.news.detail', compact('post'));
    }
}
