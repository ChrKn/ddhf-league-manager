<x-filament-panels::page>
    @include('filament.pages.browse.chrome')

    <div class="data-browser">
    <p class="lede">Alle Felder wie gespeichert, Verweise mit dem Namen statt der ID.</p>

    @include('browse.partials.controls', ['filters' => $filters ?? [], 'target' => 'data-table'])
    @include('browse.partials.table', ['columns' => $columns, 'rows' => $rows, 'id' => 'data-table'])
    </div>
</x-filament-panels::page>
