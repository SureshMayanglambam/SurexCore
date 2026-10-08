@extends('frontend.layout.default')

@section('title', 'お知らせ | ' . $site->name)
@section('description', $site->name . 'のお知らせ一覧です。')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/frontend/css/news.css') }}">
@endpush

@section('content')
<div class="sx-news">
    <div class="container">
        <h1 class="sx-news__title">お知らせ</h1>

        @if(count($posts))
            <ul class="sx-news__list">
                @foreach($posts as $post)
                    <li>
                        <a href="{{ url_to('news.detail', $post->slug) }}">
                            {{-- Image --}}
                            @if($post->news_image)
                                <img src="{{ media_url($post->news_image) }}" alt="" loading="lazy">
                            @endif
                            <div>
                                <time datetime="{{ $post->published_at }}">{{ format_date($post->published_at) }}</time>
                                {{-- Radio: label of the stored value --}}
                                <span class="sx-news__type">{{ $post->label('news_type') }}</span>
                                {{-- On/off --}}
                                @if($post->news_pickup)
                                    <span class="sx-news__badge">ピックアップ</span>
                                @endif
                                {{-- Text --}}
                                <h2>{{ $post->news_title }}</h2>
                                {{-- Textarea: escape, keep line breaks --}}
                                <p>{!! nl2br(esc($post->news_summary)) !!}</p>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>

            {!! pagination() !!}
        @else
            <p>お知らせはまだありません。</p>
        @endif
    </div>
</div>
@endsection
