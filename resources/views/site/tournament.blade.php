@extends('site.layout')

@section('content')
    {{-- The tournament's name is built from the event, year, discipline and division, all of
         which come from the database and stay German in both languages. --}}
    <h1>{{ $title }}</h1>
    <p class="lede">
        <a href="{{ $url('tournaments') }}">{{ __('site.tournament.back') }}</a>
        @if ($standing)
            · <a href="{{ $standing }}">{{ __('site.tournament.standing') }}</a>
        @endif
    </p>

    <div class="panel">
        <dl class="facts">
            @foreach ($facts as $label => $value)
                <div>
                    <dt>{{ $label }}</dt>
                    <dd>{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </div>

    @include('site.partials.filters', ['filters' => $filters, 'target' => 'ergebnisse'])

    <div class="panel">
        <div class="panel-head">
            <h2>{{ __('site.tournament.results') }}</h2>
        </div>
        <div class="table-wrap">
            <table id="ergebnisse" data-sortable>
                <thead>
                <tr>
                    <th scope="col">{{ __('site.columns.rank') }}</th>
                    <th scope="col">{{ __('site.columns.fencer') }}</th>
                    <th scope="col">{{ __('site.columns.club') }}</th>
                    <th class="num" scope="col">{{ __('site.columns.points') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td class="rank" data-sort="{{ $row['placement_sort'] }}">{{ $row['placement'] }}</td>
                        <td>{{ $row['fencer'] ?? '—' }}</td>
                        <td>{{ $row['group'] ?? '—' }}</td>
                        <td class="num points">{{ $row['points'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">
                            <span class="empty">
                                {{ request()->query('verein')
                                    ? __('site.tournament.empty_club')
                                    : __('site.tournament.empty') }}
                            </span>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="count">
        <span data-count="ergebnisse">{{ count($rows) }}</span> {{ __('site.tournament.count') }}
        @if (count($rows) !== $total)
            {{ __('site.tournament.of_total', ['total' => $total]) }}
        @endif
        · {{ __('site.tournament.note') }}
    </p>
@endsection
