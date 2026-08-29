{{--
    One table, driven by a column map and a list of rows.

    A cell value is either a scalar, which is printed, or an array with a href, which becomes a
    link. Both go through Blade's escaping - no cell ever carries raw markup, so a club name with
    an ampersand in it cannot break the page.

    @param array<string, string> $columns  key => heading
    @param array<int, array>     $rows
    @param string                $id       DOM id, needed by the search box and the sorter
--}}
@php
    $id = $id ?? 'data-table';
@endphp

<div class="table-wrap">
    <table id="{{ $id }}" data-sortable>
        <thead>
        <tr>
            @foreach ($columns as $key => $heading)
                <th scope="col">{{ $heading }}</th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @forelse ($rows as $row)
            <tr>
                @foreach ($columns as $key => $heading)
                    @php $value = $row[$key] ?? null; @endphp
                    <td class="{{ is_int($value) ? 'num' : '' }} {{ $key === 'public_id' ? 'id' : '' }}">
                        @if (is_array($value) && isset($value['href']))
                            <a href="{{ $value['href'] }}">{{ $value['text'] }}</a>
                        @elseif ($value === null || $value === '')
                            <span class="empty">—</span>
                        @else
                            {{ $value }}
                        @endif
                    </td>
                @endforeach
            </tr>
        @empty
            <tr>
                <td colspan="{{ count($columns) }}"><span class="empty">Keine Datensätze.</span></td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>

<p class="count"><span data-count="{{ $id }}">{{ count($rows) }}</span> Zeilen</p>
