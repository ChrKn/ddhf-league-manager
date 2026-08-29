@extends('site.layout')

@section('content')
    <h1>{{ $title }}</h1>
    <p class="lede">
        {{ __('site.index.lede') }}
        <a href="{{ $url('standings') }}">{{ __('site.index.all') }}</a>
    </p>

    @forelse ($tables as $table)
        <section class="panel">
            <div class="panel-head">
                <h2>{{ $table['title'] }}</h2>
                <a class="more" href="{{ $table['href'] }}">
                    {{ __('site.index.full') }}{{ $table['total'] > count($table['rows']) ? ' · ' . __('site.index.fencers', ['count' => $table['total']]) : '' }} →
                </a>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th scope="col">{{ __('site.columns.rank') }}</th>
                        <th scope="col">{{ __('site.columns.fencer') }}</th>
                        <th scope="col">{{ __('site.columns.club') }}</th>
                        <th class="num" scope="col">{{ __('site.columns.points') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($table['rows'] as $row)
                        <tr>
                            <td class="rank">{{ $row['rank'] }}</td>
                            <td class="{{ $row['anonymized'] ? 'anon' : '' }}">{{ $row['name'] }}</td>
                            <td>{{ $row['group'] ?? '—' }}</td>
                            <td class="num points">{{ $row['points'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @empty
        <div class="note">
            {{ __('site.index.empty', ['year' => $year]) }}
            <a href="{{ $url('standings') }}">{{ __('site.index.empty_link') }}</a>
        </div>
    @endforelse
@endsection
