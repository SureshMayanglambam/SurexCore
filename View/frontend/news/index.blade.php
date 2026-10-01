{{-- SAMPLE VIEW — list of お知らせ (App/Controller/News.php::index) --}}
@extends('frontend.layout.default')

@section('title', 'お知らせ | ' . $site->name)
@section('description', $site->name . 'のお知らせ一覧です。')

@section('content')

    <section class="page-header">
        <div class="container">
            <h1>お知らせ</h1>
            <p>News</p>
        </div>
    </section>

    <section class="section">
        <div class="container">
            @if(count($items))
                <ul class="news-list">
                    @foreach($items as $item)
                        <li>
                            <a href="{{ url_to('news.detail', $item->slug) }}">
                                <time datetime="{{ $item->published_at }}">{{ format_date($item->published_at) }}</time>
                                <span>{{ $item->title }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>

                {!! $pager->links() !!}
            @else
                <p>お知らせはまだありません。管理画面の「お知らせ」から投稿を追加してください。</p>
            @endif
        </div>
    </section>

@endsection
