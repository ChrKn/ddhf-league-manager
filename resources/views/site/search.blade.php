@extends('site.layout')

@section('content')
    <h1>{{ __('site.search.title') }}</h1>
    <p class="lede">{{ __('site.search.lede') }}</p>

    {{-- The classes the filter forms already use, so the search looks like the rest of the site
         rather than like a page that arrived later. --}}
    <form method="GET" action="{{ $url('search') }}" class="controls" role="search">
        <div class="control">
            <label for="q">{{ __('site.search.label') }}</label>
            <input type="search" id="q" name="q" value="{{ $query }}"
                   autocomplete="off" autofocus
                   placeholder="{{ __('site.search.placeholder') }}">
        </div>
        <button type="submit">{{ __('site.search.submit') }}</button>
    </form>

    @if ($tooShort)
        <div class="note">{{ __('site.search.too_short', ['count' => \App\Standings\FencerSearch::MINIMUM]) }}</div>
    @elseif ($query !== '' && $total === 0)
        {{-- Says what it looked in, so that "nothing" does not read as "you are not on record".
             Somebody who fenced for a club that is not a member is genuinely in no table. --}}
        <div class="note">{{ __('site.search.nothing', ['query' => $query]) }}</div>
    @endif

    @foreach ($people as $person)
        <div class="panel">
            <div class="panel-head">
                <h2>{{ $person['name'] }}</h2>
                <span class="empty">{{ $person['group'] ?? __('site.search.no_club') }}</span>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th scope="col">{{ __('site.columns.standing') }}</th>
                        <th class="num" scope="col">{{ __('site.columns.rank') }}</th>
                        <th class="num" scope="col">{{ __('site.columns.points') }}</th>
                        <th scope="col"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($person['appearances'] as $appearance)
                        <tr>
                            <td>{{ $appearance['season']->display_name }}</td>
                            {{-- The field size belongs next to the rank: fourth of thirty-one is
                                 not the same achievement as fourth of five. --}}
                            <td class="num">{{ \App\Support\Ordinal::format($appearance['rank'], app()->getLocale()) }} {{ __('site.search.of', ['total' => $appearance['of']]) }}</td>
                            <td class="num points">{{ $appearance['points'] }}</td>
                            <td>
                                <a href="{{ $url('standing', $appearance['season']->public_id) }}">{{ __('site.search.to_standing') }}</a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach

    @if ($trimmed)
        <p class="count">{{ __('site.search.trimmed', ['count' => \App\Standings\FencerSearch::LIMIT]) }}</p>
    @elseif ($total > 0)
        <p class="count">{{ trans_choice('site.search.count', $total, ['count' => $total]) }}</p>
    @endif
@endsection
