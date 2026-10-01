{{--
    SAMPLE VIEW — one お知らせ (App/Controller/News.php::detail).
    Also the default preview template for the "news" content type (frontend.{slug}.detail).
--}}
@extends('frontend.layout.default')

@section('title', ($item->meta_title ?: $item->title) . ' | ' . $site->name)
@section('description', $item->meta_description ?: mb_strimwidth(trim(strip_tags((string) $item->body)), 0, 120, '…'))
@section('og_type', 'article')

@push('styles')
    {{-- Styles for CKEditor content (headings, lists, tables, images) --}}
    <link rel="stylesheet" href="{{ asset('assets/vendor/ckeditor5/ckeditor5-content.css') }}">
@endpush

@section('content')

    <section class="section">
        <div class="container">
            <article class="article">
                <time datetime="{{ $item->published_at }}">{{ format_date($item->published_at) }}</time>
                <h1>{{ $item->title }}</h1>

                {{-- "body" is a 本文 (CKEditor) field: trusted HTML, so {!! !!} --}}
                <div class="article__body ck-content">{!! $item->body !!}</div>
            </article>

            <p><a class="btn btn-outline" href="{{ url_to('news') }}">← お知らせ一覧へ</a></p>
        </div>
    </section>

@endsection
