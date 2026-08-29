@extends('site.layout')

@php
    $has_qualifier = collect($rows)->contains(fn ($row) => $row['qualifier'] !== null);
@endphp

@section('content')
    {{-- The standing's own name comes from the database and stays German in both languages. --}}
    <h1>{{ $season->standing?->display_name ?? $title }}</h1>
    <p class="lede">
        {{ __('site.standing.season') }} {{ $season->year }}
        @if ($mode) · {{ __('site.standing.scoring') }}: {{ $mode->label() }} @endif
        · <a href="{{ $url('standings') }}">{{ __('site.standing.all') }}</a>
    </p>

    @if ($error)
        <div class="note">{{ $error }}</div>
    @endif

    @include('site.partials.filters', ['filters' => $filters, 'target' => 'rangliste'])

    <div class="panel">
        <div class="table-wrap">
            <table id="rangliste" data-sortable>
                <thead>
                <tr>
                    <th scope="col">{{ __('site.columns.rank') }}</th>
                    <th scope="col">{{ __('site.columns.fencer') }}</th>
                    <th scope="col">{{ __('site.columns.club') }}</th>
                    <th class="num" scope="col">{{ __('site.columns.points') }}</th>
                    @if ($has_qualifier)
                        <th scope="col">{{ __('site.columns.qualifier') }}</th>
                    @endif
                    <th class="nosort" scope="col">{{ __('site.columns.results') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td class="rank">{{ $row['rank'] }}</td>
                        <td class="{{ $row['anonymized'] ? 'anon' : '' }}">{{ $row['name'] }}</td>
                        <td>{{ $row['group'] ?? '—' }}</td>
                        <td class="num points">{{ $row['points'] }}</td>
                        @if ($has_qualifier)
                            <td>{{ $row['qualifier'] ?? '—' }}</td>
                        @endif
                        <td>
                            @if ($row['anonymized'])
                                <span class="empty">{{ __('site.standing.anonymised') }}</span>
                            @else
                                @php
                                    // The results arrive with the counting ones first - see
                                    // StandingCalculator::describe(). That is what lets the index
                                    // of the first one that does not count be the number that do.
                                    $counted = count(array_filter($row['results'], fn ($result) => $result['counts']));
                                @endphp
                                <details class="results">
                                    <summary>
                                        {{ __('site.standing.show', ['count' => count($row['results'])]) }}
                                        @if ($counted < count($row['results']))
                                            <span class="of-which">{{ __('site.standing.of_which_counted', ['count' => $counted]) }}</span>
                                        @endif
                                    </summary>
                                    <table class="result-list">
                                        <tbody>
                                        @foreach ($row['results'] as $index => $result)
                                            <tr @class([
                                                'not-counted' => ! $result['counts'],
                                                'cut'         => $index === $counted && $counted > 0,
                                            ])>
                                                <td>
                                                    <a href="{{ $url('tournament', $result['tournament_id']) }}">{{ $result['tournament'] }}</a>
                                                </td>
                                                {{-- Not placement_name, which is the German one the
                                                     data browser and the API read. The stored value
                                                     travels alongside it for exactly this. --}}
                                                <td class="num">{{ \App\Standings\Placement::from($result['placement'])->label(app()->getLocale()) }}</td>
                                                <td class="num">{{ $result['points'] }}</td>
                                                {{-- In words as well as in grey: colour on its own is not a signal
                                                     everybody receives. --}}
                                                <td>{{ $result['counts'] ? '' : __('site.standing.not_counted') }}</td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </details>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $has_qualifier ? 6 : 5 }}">
                            <span class="empty">
                                {{ request()->query('verein')
                                    ? __('site.standing.empty_club')
                                    : __('site.standing.empty') }}
                            </span>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="count">
        <span data-count="rangliste">{{ count($rows) }}</span> {{ __('site.standing.count') }}
        @if (count($rows) !== $total)
            {{ __('site.standing.of_total', ['total' => $total]) }}
        @endif
    </p>
@endsection
