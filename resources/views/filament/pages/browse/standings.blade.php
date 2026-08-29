<x-filament-panels::page>
    @include('filament.pages.browse.chrome')

    <div class="data-browser">
    <p class="lede">
        Eine Rangliste ist die Kombination aus Disziplin und Abteilung; gefochten wird sie je
        Saison. Ein Jahr anklicken, um die gerechnete Tabelle zu sehen.
    </p>

    @foreach ($standings as $standing)
        <h2>{{ $standing->display_name }}</h2>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th class="nosort" scope="col">ID</th>
                    <th class="nosort" scope="col">Disziplin</th>
                    <th class="nosort" scope="col">Abteilung</th>
                    <th class="nosort" scope="col">Saisons</th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td class="id">{{ $standing->public_id }}</td>
                    <td>{{ $standing->discipline?->name ?? '—' }}</td>
                    <td>{{ $standing->division?->name ?? '—' }}</td>
                    <td>
                        @forelse ($standing->seasons->sortByDesc('year') as $season)
                            <a href="{{ \App\Filament\Pages\Browse\Ranking::getUrl(['public_id' => $season->public_id]) }}">{{ $season->year }}</a>@if (!$loop->last), @endif
                        @empty
                            <span class="empty">noch keine Saison</span>
                        @endforelse
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    @endforeach
    </div>
</x-filament-panels::page>
