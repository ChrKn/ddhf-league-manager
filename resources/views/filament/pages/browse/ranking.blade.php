@php
    // Only the grouped modes name a winning zone or ruleset; in the standard mode the column
    // would be a row of dashes.
    $has_qualifier = collect($rows)->contains(fn ($row) => $row['qualifier'] !== null);
@endphp

<x-filament-panels::page>
    @include('filament.pages.browse.chrome')

    <div class="data-browser">
    <p class="lede">
        <a href="{{ \App\Filament\Pages\Browse\Standings::getUrl() }}">← alle Ranglisten</a>
        · <a href="{{ \App\Filament\Pages\Browse\Tournaments::getUrl(['season' => $season->public_id]) }}">Turniere dieser Saison</a>
        @if ($mode)
            · Auswertung: {{ $mode->label() }}
        @endif
        @if ($season->scoring_matrix)
            · Punkteschlüssel: {{ $season->scoring_matrix->name }}
        @endif
    </p>

    @if ($error)
        <div class="warning">{{ $error }}</div>
    @endif

    <div class="warning">
        Gelistet wird, wer das Ergebnis für einen Verein des {{ \App\Models\Federation::OWN }}
        gefochten hat — Mitgliedschaft wird am Ergebnis abgelesen, nicht an der Person.
        Anonymisierte Fechter stehen an ihrem Platz, aber ohne Namen, ohne Verein und ohne
        Einzelergebnisse — nur die Punktzahl bleibt. Wer hier fehlt, obwohl er gefochten hat,
        hatte an dem Tag keinen Verband am Ergebnis.
    </div>

    @include('browse.partials.controls', ['filters' => [], 'target' => 'ranking'])

    <div class="table-wrap">
        <table id="ranking" data-sortable>
            <thead>
            <tr>
                <th scope="col">Platz</th>
                <th scope="col">Fechter</th>
                <th scope="col">Verein</th>
                <th scope="col">Punkte</th>
                @if ($has_qualifier)
                    <th scope="col">Gewertet über</th>
                @endif
                <th scope="col">Turniere</th>
                <th class="nosort" scope="col">Einzelergebnisse</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="num">{{ $row['rank'] }}</td>
                    <td>{{ $row['fencer']->display_name }}</td>
                    <td>{{ $row['group'] ?? '—' }}</td>
                    <td class="num">{{ $row['points'] }}</td>
                    @if ($has_qualifier)
                        <td>{{ $row['qualifier'] ?? '—' }}</td>
                    @endif
                    {{-- A dash rather than a nought: the tournaments are withheld, not missing. --}}
                    <td class="num">{{ $row['anonymized'] ? '—' : count($row['results']) }}</td>
                    <td>
                        @if ($row['anonymized'])
                            <span class="empty">anonymisiert</span>
                        @else
                            @php
                                $counted = count(array_filter($row['results'], fn ($result) => $result['counts']));
                            @endphp
                            <details class="sub">
                                <summary>
                                    {{ count($row['results']) }} anzeigen
                                    @if ($counted < count($row['results'])) · {{ $counted }} gewertet @endif
                                </summary>
                                <table>
                                    <tbody>
                                    @foreach ($row['results'] as $index => $result)
                                        {{-- Counting first, so the index of the first one that does not
                                             count is the number that do - see StandingCalculator. --}}
                                        <tr @class([
                                            'not-counted' => ! $result['counts'],
                                            'cut'         => $index === $counted && $counted > 0,
                                        ])>
                                            <td>
                                                @if ($result['tournament_id'])
                                                    <a href="{{ \App\Filament\Pages\Browse\Tournament::getUrl(['public_id' => $result['tournament_id']]) }}">{{ $result['tournament'] }}</a>
                                                @else
                                                    {{ $result['tournament'] }}
                                                @endif
                                            </td>
                                            <td class="num">{{ $result['placement_name'] }}</td>
                                            <td class="num">{{ $result['points'] }} P.</td>
                                            <td>{{ $result['counts'] ? '' : 'zählt nicht' }}</td>
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
                    <td colspan="{{ $has_qualifier ? 7 : 6 }}">
                        <span class="empty">Kein gewertetes Ergebnis in dieser Saison.</span>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <p class="count"><span data-count="ranking">{{ count($rows) }}</span> Zeilen</p>
    </div>
</x-filament-panels::page>
