@extends('site.layout')

@section('content')
    <h1>{{ __('site.news.title') }}</h1>
    <p class="lede">{{ __('site.news.lede') }}</p>

    @if ($announcements === [])
        <div class="note">{{ __('site.news.empty') }}</div>
    @else
        <div class="panel">
            @include('site.partials.announcements', ['announcements' => $announcements])
        </div>
    @endif
@endsection
