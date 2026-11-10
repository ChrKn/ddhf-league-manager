@extends('site.layout')

{{-- Explains a table rather than being a page to arrive at from a search engine. --}}
@section('noindex', 'true')

@section('content')
    {{-- The matrix's name comes from the database and stays German in both languages. --}}
    <h1>{{ $title }}</h1>
    <p class="lede">{{ __('site.scoring.lede') }}</p>

    @if ($rows === [])
        <div class="note">{{ __('site.scoring.empty') }}</div>
    @else
        {{-- Transposed against the official graphic: placements down the side, field sizes across.
             The round a placement column is also reached by sits next to it in the row heading.
             Each cell is tinted by its share of the highest value in the matrix. --}}
        <div class="panel">
            <div class="table-wrap">
                <table class="matrix">
                    <thead>
                    <tr>
                        <th scope="col" rowspan="2" class="corner">{{ __('site.scoring.place') }}</th>
                        <th scope="colgroup" colspan="{{ count($bands) }}" class="span">{{ __('site.scoring.participants') }}</th>
                    </tr>
                    <tr>
                        @foreach ($bands as $band)
                            <th scope="col" class="num">{{ $band }}</th>
                        @endforeach
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <th scope="row">
                                <span class="place">{{ $row['label'] }}</span>
                                @if ($row['round'])
                                    <span class="round">{{ $row['round'] }}</span>
                                @endif
                            </th>
                            @foreach ($row['points'] as $points)
                                @include('site.partials.matrix-cell', ['points' => $points, 'max' => $max])
                            @endforeach
                        </tr>
                    @endforeach
                    @if ($pools !== null)
                        <tr class="pools">
                            <th scope="row"><span class="place">{{ __('site.scoring.pools') }}</span></th>
                            @foreach ($pools as $points)
                                @include('site.partials.matrix-cell', ['points' => $points, 'max' => $max])
                            @endforeach
                        </tr>
                    @endif
                    </tbody>
                </table>
            </div>
        </div>

        <p class="count">
            @if ($threshold)
                {{ __('site.scoring.threshold', ['count' => $threshold]) }}
            @endif
            {{ __('site.scoring.zero') }}
        </p>
    @endif

    @if ($seasons !== [])
        <h2 class="used-in">{{ __('site.scoring.used_in') }}</h2>
        <ul class="used-in">
            @foreach ($seasons as $season)
                <li>
                    <a href="{{ $season['href'] }}">{{ $season['name'] }}</a>
                    @if ($season['threshold'])
                        <span class="threshold">· {{ $season['threshold'] }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
@endsection
