{{--
    The dropdowns narrow the query and reload; the search box narrows what is already on screen.
    Both are plain HTML - the form submits on its own, and the layout adds submit-on-change and
    the search behaviour at the end of the body, where the table it has to find already exists.

    The option values are database values and stay as they are stored, in both languages. Only
    the labels around them are translated.
--}}
<form class="controls" method="get">
    @foreach ($filters as $name => $filter)
        <div class="control">
            <label for="filter-{{ $name }}">{{ $filter['label'] }}</label>
            <select id="filter-{{ $name }}" name="{{ $name }}">
                <option value="">{{ __('site.filters.all') }}</option>
                @foreach ($filter['options'] as $value => $label)
                    <option value="{{ $value }}" @selected(request()->query($name) == $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    @endforeach

    <div class="control">
        <label for="filter-suche">{{ __('site.filters.search') }}</label>
        <input type="search" id="filter-suche" data-search="{{ $target }}"
               placeholder="{{ __('site.filters.hint') }}">
    </div>

    <button type="submit">{{ __('site.filters.submit') }}</button>

    @if (collect($filters)->keys()->contains(fn ($key) => request()->query($key)))
        <a class="reset" href="{{ url()->current() }}">{{ __('site.filters.reset') }}</a>
    @endif
</form>
