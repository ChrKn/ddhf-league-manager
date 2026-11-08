@extends('site.layout')

{{-- For whoever wants to know what changed, not a page to arrive at from a search engine. --}}
@section('noindex', 'true')

@section('content')
    {{-- English on every language of the site, and marked as such for a screen reader. The page
         around it - menu, footer - follows the reader's language. --}}
    <h1 lang="en">Changelog</h1>
    <p class="lede">{{ __('site.changelog.lede') }}</p>

    <div class="panel">
        <div class="changelog" lang="en">
            {!! $html !!}
        </div>
    </div>
@endsection
