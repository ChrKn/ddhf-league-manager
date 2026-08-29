<x-filament-panels::page>
    @include('filament.pages.browse.chrome')

    <div class="data-browser">
    <p class="lede">Der ganze Datenbestand zum Durchsehen. Nichts hier schreibt.</p>

    <div class="cards">
        @foreach ($counts as $count)
            <a class="card" href="{{ $count['href'] }}">
                <div class="value">{{ number_format($count['value'], 0, ',', '.') }}</div>
                <div class="label">{{ $count['label'] }}</div>
            </a>
        @endforeach
    </div>

    <h2>Woran man hängen bleibt</h2>
    <p class="lede">
        Zählungen, die beim Prüfen zuerst interessieren. Eine Null ist hier nicht zwingend das
        Ziel — vereinslose Ergebnisse etwa sind zum Teil richtig so.
    </p>

    <div class="table-wrap">
        <ul class="notes">
            @foreach ($notes as $label => $value)
                <li>
                    <span>{{ $label }}</span>
                    <span class="n {{ $value === 0 ? 'zero' : '' }}">{{ $value }}</span>
                </li>
            @endforeach
        </ul>
    </div>
    </div>
</x-filament-panels::page>
