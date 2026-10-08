@extends('frontend.layout.default')

@section('title', ($post->meta_title ?: $post->title) . ' | ' . $site->name)
@section('description', $post->meta_description ?: mb_strimwidth((string) $post->news_summary, 0, 120, '…'))
@if($post->news_image)
    @section('og_image', media_url($post->news_image))
@endif

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendor/ckeditor5/ckeditor5-content.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/frontend/css/news.css') }}">
@endpush

@section('content')
<div class="sx-news">
    <article class="container sx-news__article">
        <time datetime="{{ $post->published_at }}">{{ format_date($post->published_at) }}</time>

        {{-- Radio: $post->news_type is the value ("event"), label() the label ("イベント") --}}
        <span class="sx-news__type">{{ $post->label('news_type') }}</span>

        {{-- On/off: true / false --}}
        @if($post->news_pickup)
            <span class="sx-news__badge">ピックアップ</span>
        @endif

        {{-- Text --}}
        <h1>{{ $post->news_title }}</h1>

        {{-- Checkbox: label() gives an array of labels --}}
        @if($post->news_category)
            <ul class="sx-news__tags">
                @foreach($post->label('news_category') as $category)
                    <li>#{{ $category }}</li>
                @endforeach
            </ul>
        @endif

        {{-- Image --}}
        @if($post->news_image)
            <img class="sx-news__image" src="{{ media_url($post->news_image) }}" alt="{{ $post->news_title }}">
        @endif

        {{-- Textarea --}}
        @if($post->news_summary)
            <p class="sx-news__summary">{!! nl2br(esc($post->news_summary)) !!}</p>
        @endif

        {{-- CKEditor: HTML, output raw (not escaped) inside .ck-content --}}
        <div class="ck-content">{!! $post->news_detail !!}</div>

        {{-- Repeater: a list of rows, each row an array of its sub fields --}}
        @if($post->field('news_links', []))
            <section class="sx-news__links">
                <h2>関連リンク</h2>
                <ul>
                    @foreach($post->field('news_links', []) as $link)
                        <li><a href="{{ $link['link_url'] }}" target="_blank" rel="noopener">{{ $link['link_label'] }}</a></li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- Relation: relation() gives the linked entries (published only): a list, or one entry for a single relation --}}
        @if($post->relation('news_related'))
            <section class="sx-news__links">
                <h2>関連するお知らせ</h2>
                <ul>
                    @foreach($post->relation('news_related') as $related)
                        <li><a href="{{ url_to('news.detail', $related->slug) }}">{{ $related->title }}</a></li>
                    @endforeach
                </ul>
            </section>
        @endif

        <p><a href="{{ url_to('news') }}">← お知らせ一覧へ</a></p>
    </article>
</div>
@endsection
