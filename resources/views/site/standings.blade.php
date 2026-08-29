@extends('site.layout')

@section('content')
    <h1>{{ __('site.standings.title') }}</h1>
    <p class="lede">{{ __('site.standings.lede') }}</p>

    @include('site.partials.filters', ['filters' => $filters, 'target' => 'ranglisten'])

    <div class="panel">
        <div class="table-wrap">
            <table id="ranglisten" data-sortable>
                <thead>
                <tr>
                    <th scope="col">{{ __('site.columns.discipline') }}</th>
                    <th scope="col">{{ __('site.columns.division') }}</th>
                    <th class="num" scope="col">{{ __('site.columns.year') }}</th>
                    <th class="num" scope="col">{{ __('site.columns.tournaments') }}</th>
                    <th class="nosort" scope="col"></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr>
                        {{-- Discipline and division come out of the database and stay German in
                             both languages until the federation says what they are called. --}}
                        <td><a href="{{ $row['href'] }}">{{ $row['discipline'] ?? '—' }}</a></td>
                        <td>{{ $row['division'] ?? '—' }}</td>
                        <td class="num">{{ $row['year'] }}</td>
                        <td class="num">{{ $row['tournaments'] }}</td>
                        <td><a href="{{ $row['href'] }}">{{ __('site.standings.table') }}</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5"><span class="empty">{{ __('site.standings.empty') }}</span></td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="count">
        <span data-count="ranglisten">{{ count($rows) }}</span> {{ __('site.standings.count') }}
    </p>
@endsection
