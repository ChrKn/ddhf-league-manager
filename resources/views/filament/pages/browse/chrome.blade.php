{{--
    The look of the browse tables, and the sorting behind them.

    Inline on purpose. The asset pipeline is not built in this project, so anything depending on
    Vite would only work while `npm run dev` happens to be running - and a page for looking
    something up has to work whenever somebody wants to look something up.

    Pushed once per request rather than per page, because six pages share the table view.
--}}
@once
    @push('styles')
        <style>
        /* Confined to the browse pages, variables included: the panel has a palette of its own
           and these names must not reach it. */
        .data-browser {
            --bg: #f6f6f4;
            --panel: #ffffff;
            --ink: #1c1c1a;
            --muted: #6d6d68;
            --line: #dedcd6;
            --accent: #8a6a1f;
            --accent-soft: #fdf6e4;
            --shadow: 0 1px 2px rgba(0, 0, 0, .06);
        }

        /* Filament stamps `dark` on the html element. The browser used to ask the operating
           system directly, which would now fight the panel's own switch. */
        :root.dark .data-browser {
            --bg: #17171a;
            --panel: #1f1f23;
            --ink: #e9e8e4;
            --muted: #9a9993;
            --line: #33333a;
            --accent: #d8b25a;
            --accent-soft: #2a2519;
            --shadow: none;
        }

        .data-browser * { box-sizing: border-box; }

        .data-browser h1 { font-size: 1.4rem; margin: 0 0 .2rem; font-weight: 650; }
        .data-browser h2 { font-size: 1.05rem; margin: 2rem 0 .6rem; font-weight: 650; }
        .data-browser .lede { color: var(--muted); font-size: .875rem; margin: 0 0 1.2rem; }

        /* --- controls ------------------------------------------------------ */

        .data-browser .controls {
            display: flex;
            flex-wrap: wrap;
            gap: .6rem;
            align-items: flex-end;
            margin-bottom: 1rem;
        }

        .data-browser .control { display: flex; flex-direction: column; gap: .2rem; }

        .data-browser .control label {
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--muted);
        }

        .data-browser select, .data-browser input[type="search"] {
            font: inherit;
            font-size: .875rem;
            padding: .4rem .55rem;
            border: 1px solid var(--line);
            border-radius: 6px;
            background: var(--panel);
            color: var(--ink);
            min-width: 11rem;
            max-width: 20rem;
        }

        .data-browser select:focus, .data-browser input:focus { outline: 2px solid var(--accent); outline-offset: -1px; }

        .data-browser .reset {
            font-size: .8rem;
            color: var(--muted);
            text-decoration: none;
            padding: .45rem 0;
        }

        .data-browser .reset:hover { color: var(--accent); }

        /* --- table --------------------------------------------------------- */

        .data-browser .table-wrap {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: var(--shadow);
            overflow-x: auto;
        }

        .data-browser table { border-collapse: collapse; width: 100%; font-size: .875rem; }

        .data-browser thead th {
            position: sticky;
            top: 0;
            background: var(--panel);
            border-bottom: 1px solid var(--line);
            text-align: left;
            padding: .55rem .7rem;
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: var(--muted);
            white-space: nowrap;
            cursor: pointer;
            user-select: none;
        }

        .data-browser thead th:hover { color: var(--accent); }
        .data-browser thead th::after { content: " ⇅"; opacity: .25; font-size: .8em; }
        .data-browser thead th.sorted-asc::after { content: " ↑"; opacity: 1; }
        .data-browser thead th.sorted-desc::after { content: " ↓"; opacity: 1; }
        .data-browser thead th.nosort { cursor: default; }
        .data-browser thead th.nosort::after { content: ""; }

        .data-browser tbody td {
            padding: .45rem .7rem;
            border-bottom: 1px solid var(--line);
            vertical-align: top;
        }

        .data-browser tbody tr:last-child td { border-bottom: 0; }
        .data-browser tbody tr:hover { background: var(--accent-soft); }

        .data-browser td.num { text-align: right; font-variant-numeric: tabular-nums; }
        .data-browser td.id { font-family: ui-monospace, "Cascadia Code", Consolas, monospace; font-size: .8rem; color: var(--muted); }

        .data-browser .empty { color: var(--muted); opacity: .5; }

        .data-browser a { color: var(--accent); }

        .data-browser .count {
            font-size: .8rem;
            color: var(--muted);
            margin: .6rem 0 0;
        }

        /* --- cards --------------------------------------------------------- */

        .data-browser .cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(11rem, 1fr));
            gap: .7rem;
        }

        .data-browser .card {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: .9rem 1rem;
            text-decoration: none;
            color: inherit;
            box-shadow: var(--shadow);
            display: block;
        }

        .data-browser .card:hover { border-color: var(--accent); }
        .data-browser .card .value { font-size: 1.6rem; font-weight: 650; font-variant-numeric: tabular-nums; }
        .data-browser .card .label { font-size: .8rem; color: var(--muted); }

        .data-browser dl.facts {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(13rem, 1fr));
            gap: .1rem 1.2rem;
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 1rem 1.1rem;
            margin: 0 0 1.4rem;
        }

        .data-browser dl.facts div { padding: .25rem 0; }
        .data-browser dl.facts dt { font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); }
        .data-browser dl.facts dd { margin: 0; font-size: .9rem; }

        .data-browser .notes { list-style: none; padding: 0; margin: 0; }

        .data-browser .notes li {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            padding: .4rem .8rem;
            border-bottom: 1px solid var(--line);
            font-size: .875rem;
        }

        .data-browser .notes li:last-child { border-bottom: 0; }
        .data-browser .notes .n { font-variant-numeric: tabular-nums; font-weight: 600; }
        .data-browser .notes .zero { color: var(--muted); font-weight: 400; }

        .data-browser .warning {
            background: var(--accent-soft);
            border: 1px solid var(--accent);
            border-radius: 8px;
            padding: .8rem 1rem;
            font-size: .875rem;
            margin-bottom: 1.4rem;
        }

        .data-browser details.sub summary { cursor: pointer; color: var(--muted); font-size: .8rem; }
        .data-browser details.sub table { margin-top: .4rem; font-size: .8rem; }
        .data-browser details.sub td { padding: .2rem .5rem; }
        .data-browser .not-counted { opacity: .45; }
        /* The rule between what counts and what does not. The results arrive with the counting
           ones first, so it always falls in one place. */
        .data-browser details.sub tr.cut td { border-top: 1px solid var(--line); padding-top: .55rem; }
    </style>
    @endpush

    @push('scripts')
        <script>
    // Live filtering and column sorting, so a table can be narrowed down without a round trip.
    document.querySelectorAll('[data-search]').forEach(function (input) {
        var table = document.getElementById(input.dataset.search);
        if (!table) return;

        input.addEventListener('input', function () {
            var needle = input.value.toLowerCase().trim();
            var shown = 0;

            table.tBodies[0].querySelectorAll('tr').forEach(function (row) {
                var hit = needle === '' || row.textContent.toLowerCase().indexOf(needle) !== -1;
                row.hidden = !hit;
                if (hit) shown++;
            });

            var counter = document.querySelector('[data-count="' + input.dataset.search + '"]');
            if (counter) counter.textContent = shown;
        });
    });

    document.querySelectorAll('table[data-sortable] thead th').forEach(function (header, index) {
        if (header.classList.contains('nosort')) return;

        header.addEventListener('click', function () {
            var table = header.closest('table');
            var body = table.tBodies[0];
            var rows = Array.prototype.slice.call(body.rows);
            var descending = header.classList.contains('sorted-asc');

            table.querySelectorAll('thead th').forEach(function (other) {
                other.classList.remove('sorted-asc', 'sorted-desc');
            });
            header.classList.add(descending ? 'sorted-desc' : 'sorted-asc');

            rows.sort(function (a, b) {
                var x = (a.cells[index] || {}).textContent || '';
                var y = (b.cells[index] || {}).textContent || '';
                var nx = parseFloat(x.replace(',', '.'));
                var ny = parseFloat(y.replace(',', '.'));
                var result = (!isNaN(nx) && !isNaN(ny))
                    ? nx - ny
                    : x.trim().localeCompare(y.trim(), 'de');

                return descending ? -result : result;
            });

            rows.forEach(function (row) { body.appendChild(row); });
        });
    });
</script>
    @endpush
@endonce
