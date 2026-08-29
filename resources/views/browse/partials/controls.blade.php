{{--
    The dropdowns above a table. Filtering happens on the server, because a filter has to narrow
    the data set and not just hide rows; the search box next to it is the opposite - it hides rows
    on the spot, which is what you want when you are hunting for one name.

    @param array<string, array{label: string, options: array}> $filters
    @param string $target  the DOM id of the table the search box belongs to
--}}
@php
    $filters = $filters ?? [];
    $target  = $target ?? 'data-table';
@endphp

<form method="GET" class="controls">
    @foreach ($filters as $name => $filter)
        <div class="control">
            <label for="filter-{{ $name }}">{{ $filter['label'] }}</label>
            <select id="filter-{{ $name }}" name="{{ $name }}" onchange="this.form.submit()">
                <option value="">alle</option>
                @foreach ($filter['options'] as $value => $label)
                    <option value="{{ $value }}" @selected((string) request($name) === (string) $value)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
        </div>
    @endforeach

    <div class="control">
        <label for="search-{{ $target }}">Suche in der Tabelle</label>
        {{-- Enter would submit the surrounding filter form and throw the typing away. --}}
        <input type="search" id="search-{{ $target }}" data-search="{{ $target }}"
               placeholder="tippen zum Eingrenzen…" autocomplete="off"
               onkeydown="if (event.key === 'Enter') event.preventDefault();">
    </div>

    <noscript><button type="submit">Filtern</button></noscript>

    @if (collect($filters)->keys()->contains(fn ($name) => request()->filled($name)))
        <a class="reset" href="{{ url()->current() }}">Filter zurücksetzen</a>
    @endif
</form>
