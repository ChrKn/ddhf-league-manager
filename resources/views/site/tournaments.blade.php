@extends('site.layout')

@section('content')
    <h1>{{ __('site.tournaments.title') }}</h1>
    <p class="lede">{{ __('site.tournaments.lede') }}</p>

    @include('site.partials.filters', ['filters' => $filters, 'target' => 'turniere'])

    <div class="panel">
        <div class="table-wrap">
            <table id="turniere" data-sortable>
                <thead>
                <tr>
                    <th scope="col">{{ __('site.columns.tournament') }}</th>
                    <th scope="col">{{ __('site.columns.date') }}</th>
                    <th scope="col">{{ __('site.columns.format') }}</th>
                    <th class="num" scope="col">{{ __('site.columns.participants') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr>
                        {{-- Name and format are database values and stay German in both
                             languages; the format is free text out of the import sheets. --}}
                        <td><a href="{{ $row['href'] }}">{{ $row['name'] }}</a></td>
                        <td data-sort="{{ $row['date_sort'] ?? '' }}">{{ $row['date'] ?? '—' }}</td>
                        <td>{{ $row['format'] ?? '—' }}</td>
                        <td class="num">{{ $row['participants'] ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4"><span class="empty">{{ __('site.tournaments.empty') }}</span></td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="count">
        <span data-count="turniere">{{ count($rows) }}</span> {{ __('site.tournaments.count') }}
    </p>
@endsection
