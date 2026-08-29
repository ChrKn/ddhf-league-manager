<x-filament-panels::page>
    @include('filament.pages.browse.chrome')

    <div class="data-browser">
    <p class="lede">
        <a href="{{ \App\Filament\Pages\Browse\Tournaments::getUrl() }}">← alle Turniere</a>
        @if ($ranking)
            · <a href="{{ $ranking }}">Rangliste dieser Saison</a>
        @endif
    </p>

    <dl class="facts">
        @foreach ($facts as $label => $value)
            <div>
                <dt>{{ $label }}</dt>
                <dd>{{ $value ?? '—' }}</dd>
            </div>
        @endforeach
    </dl>

    <h2>Ergebnisse</h2>
    <p class="lede">
        Die Punkte stehen nirgends in der Datenbank — sie werden hier aus Teilnehmerzahl und
        Platzierung mit dem Punkteschlüssel der Saison gerechnet, genau wie in der Rangliste.
        Ob ein Platz die Runde meint oder den echten Rang, sagt das Turniersystem oben.
    </p>

    @include('browse.partials.controls', ['filters' => [], 'target' => 'results'])
    @include('browse.partials.table', ['columns' => $columns, 'rows' => $rows, 'id' => 'results'])
    </div>
</x-filament-panels::page>
