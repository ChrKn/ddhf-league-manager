{{--
    Styling is inline for the same reason as the data browser: the asset pipeline is not built in
    this project, so a page that depended on Vite would only work while `npm run dev` happened to
    be running.

    The palette is the German flag, and it is used under one rule that comes from measuring it:

        gold only ever on black, red only ever on white

    Gold on white is a contrast ratio of 1.35 and unreadable; red on black is 2.85 and fails as
    well. Giving gold a structural place - the bar under the logo, the footer headings, the marker
    on the current page - keeps it off white surfaces by construction. It also keeps the page from
    drifting into black-white-red, because gold is load-bearing rather than decorative.
--}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? __('site.brand_claim') }} · DDHF</title>
    <link rel="alternate" hreflang="de" href="{{ \App\Support\SiteUrl::alternate('de') }}">
    <link rel="alternate" hreflang="en" href="{{ \App\Support\SiteUrl::alternate('en') }}">
    @isset($noindex)<meta name="robots" content="noindex">@endisset
    <link rel="icon" href="{{ asset('img/ddhf-signet.svg') }}" type="image/svg+xml">
    <style>
        :root {
            --bg: #f7f6f4;
            --panel: #ffffff;
            --ink: #1a1a1a;
            --muted: #5f5f5c;
            --line: #dedcd6;
            --accent: #cc1d1b;       /* 5.59 on white */
            --accent-soft: #fdf0ef;
            --gold: #ffdd00;         /* only ever on --dark */
            --dark: #1a1a1a;
            --on-dark: #ffffff;
            --on-dark-muted: #c9c9c4; /* 10.47 on --dark */
            --shadow: 0 1px 2px rgba(0, 0, 0, .06);
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #141414;
                --panel: #1e1e1e;
                --ink: #ededed;
                --muted: #a3a29d;
                --line: #333330;
                --accent: #e8524f;   /* 5.05 on #141414; the theme red is 3.30 and fails */
                --accent-soft: #2a1a1a;
                --shadow: none;
            }
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--ink);
            font: 16px/1.55 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }

        .wrap { max-width: 66rem; margin: 0 auto; padding: 0 1.25rem; }

        /* --- header -------------------------------------------------------- */

        /* The logo carries black, gold and red itself, so it needs a light plate in both colour
           schemes rather than being inverted. */
        .masthead { background: #ffffff; border-bottom: 3px solid var(--gold); }

        .masthead .wrap { display: flex; align-items: center; gap: 1rem; padding-block: .9rem; }

        .masthead img { height: 3.1rem; width: auto; max-width: 100%; }

        .masthead .claim {
            color: #5f5f5c;
            font-size: .82rem;
            border-left: 1px solid #dedcd6;
            padding-left: 1rem;
        }

        @media (max-width: 34rem) {
            .masthead .claim { display: none; }
        }

        nav.main { background: var(--dark); }

        nav.main .wrap { display: flex; flex-wrap: wrap; gap: .1rem; padding-inline: 1.05rem; }

        nav.main a {
            color: var(--on-dark-muted);
            text-decoration: none;
            padding: .7rem .8rem;
            font-size: .92rem;
            border-bottom: 3px solid transparent;
        }

        nav.main a:hover { color: var(--on-dark); }

        nav.main a[aria-current="page"] {
            color: var(--gold);
            border-bottom-color: var(--gold);
            font-weight: 600;
        }

        nav.main a:focus-visible { outline: 2px solid var(--gold); outline-offset: -2px; }

        /* Pushed to the far end, so it reads as a setting rather than a fourth page. */
        nav.main a.lang { margin-left: auto; font-size: .84rem; letter-spacing: .02em; }
        nav.main a.lang::before { content: "◍ "; opacity: .6; }

        /* --- content ------------------------------------------------------- */

        main { padding: 2rem 0 4rem; }

        h1 { font-size: 1.6rem; margin: 0 0 .3rem; font-weight: 650; letter-spacing: -.01em; }
        h2 { font-size: 1.1rem; margin: 0; font-weight: 650; }
        .lede { color: var(--muted); font-size: .9rem; margin: 0 0 1.6rem; }

        a { color: var(--accent); }
        a:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }

        .panel {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 10px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .panel + .panel { margin-top: 1.4rem; }

        .panel-head {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            justify-content: space-between;
            gap: .5rem;
            padding: .9rem 1.1rem;
            border-bottom: 1px solid var(--line);
        }

        .panel-head .more { font-size: .85rem; text-decoration: none; }
        .panel-head .more:hover { text-decoration: underline; }

        /* --- tables -------------------------------------------------------- */

        .table-wrap { overflow-x: auto; }

        table { border-collapse: collapse; width: 100%; font-size: .92rem; }

        thead th {
            text-align: left;
            padding: .5rem .9rem;
            border-bottom: 1px solid var(--line);
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--muted);
            white-space: nowrap;
        }

        /* The sortable state is set by script, so a page without one shows no affordance it
           cannot honour. */
        thead th.sortable { cursor: pointer; user-select: none; }
        thead th.sortable:hover { color: var(--accent); }
        thead th.sortable:focus-visible { outline: 2px solid var(--accent); outline-offset: -2px; }
        thead th.sortable::after { content: " ⇅"; opacity: .35; font-size: .9em; }
        thead th[aria-sort="ascending"]::after { content: " ↑"; opacity: 1; }
        thead th[aria-sort="descending"]::after { content: " ↓"; opacity: 1; }
        thead th[aria-sort]:not([aria-sort="none"]) { color: var(--accent); }

        tbody td { padding: .5rem .9rem; border-bottom: 1px solid var(--line); }
        tbody tr:last-child td { border-bottom: 0; }
        tbody tr:hover { background: var(--accent-soft); }

        td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
        td.rank { width: 3rem; font-variant-numeric: tabular-nums; color: var(--muted); }
        td.points { font-weight: 650; font-variant-numeric: tabular-nums; }

        .empty { color: var(--muted); }
        .anon { color: var(--muted); font-style: italic; }

        /* --- a fencer's results, unfolded inside the standing --------------- */

        details.results > summary { cursor: pointer; font-size: .88rem; color: var(--accent); }
        details.results > summary:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
        details.results .of-which { color: var(--muted); }

        table.result-list { margin: .5rem 0 .2rem; font-size: .88rem; }
        table.result-list td { padding: .3rem .9rem .3rem 0; border-bottom: 0; }

        /* Which results the total is made of, without having to add the numbers up: the ones that
           count are on top, the rest are greyed and set below a rule. The last column says the
           same in words, because colour alone is not a signal everybody receives. */
        table.result-list tr.not-counted { color: var(--muted); }
        table.result-list tr.not-counted a { color: inherit; }
        table.result-list tr.cut td { border-top: 1px solid var(--line); padding-top: .75rem; }

        tbody tr a { text-decoration: none; }
        tbody tr a:hover { text-decoration: underline; }

        /* --- filters ------------------------------------------------------- */

        form.controls {
            display: flex;
            flex-wrap: wrap;
            gap: .7rem;
            align-items: flex-end;
            margin-bottom: 1.3rem;
        }

        .control { display: flex; flex-direction: column; gap: .25rem; }

        .control label {
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--muted);
        }

        select, input[type="search"] {
            font: inherit;
            font-size: .9rem;
            padding: .45rem .6rem;
            border: 1px solid var(--line);
            border-radius: 7px;
            background: var(--panel);
            color: var(--ink);
            min-width: 10rem;
        }

        select:focus-visible, input:focus-visible { outline: 2px solid var(--accent); outline-offset: -1px; }

        .controls button {
            font: inherit;
            font-size: .9rem;
            padding: .45rem .9rem;
            border: 1px solid var(--accent);
            border-radius: 7px;
            background: var(--accent);
            color: #ffffff;
            cursor: pointer;
        }

        .controls .reset { font-size: .85rem; padding: .5rem 0; }

        .count { font-size: .82rem; color: var(--muted); margin: .8rem 0 0; }

        /* --- facts --------------------------------------------------------- */

        dl.facts {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(12rem, 1fr));
            gap: .2rem 1.4rem;
            margin: 0;
            padding: 1.1rem;
        }

        dl.facts div { padding: .3rem 0; }
        dl.facts dt { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); }
        dl.facts dd { margin: 0; font-size: .95rem; }

        .note {
            background: var(--accent-soft);
            border: 1px solid var(--line);
            border-left: 3px solid var(--accent);
            border-radius: 8px;
            padding: .8rem 1rem;
            font-size: .88rem;
            margin: 0 0 1.4rem;
        }

        /* --- footer -------------------------------------------------------- */

        footer.site {
            background: var(--dark);
            color: var(--on-dark-muted);
            border-top: 3px solid var(--gold);
            margin-top: 3rem;
            font-size: .9rem;
        }

        footer.site .wrap { padding-block: 2rem 1.4rem; }

        .foot-cols {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr));
            gap: 1.6rem;
        }

        footer.site h3 {
            color: var(--gold);
            font-size: .74rem;
            text-transform: uppercase;
            letter-spacing: .07em;
            margin: 0 0 .55rem;
        }

        footer.site p { margin: 0 0 .3rem; }
        footer.site a { color: var(--on-dark); }
        footer.site a:hover { color: var(--gold); }
        footer.site a:focus-visible { outline: 2px solid var(--gold); outline-offset: 2px; }

        .social { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .2rem; padding: 0; list-style: none; }

        .social a {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            border: 1px solid #4a4a46;
            border-radius: 999px;
            padding: .3rem .75rem;
            text-decoration: none;
            font-size: .84rem;
        }

        .social a:hover { border-color: var(--gold); }

        .foot-bottom {
            display: flex;
            flex-wrap: wrap;
            gap: .4rem 1.2rem;
            justify-content: space-between;
            border-top: 1px solid #333330;
            margin-top: 1.6rem;
            padding-top: 1rem;
            font-size: .84rem;
        }

        .foot-bottom ul { display: flex; gap: 1.1rem; list-style: none; margin: 0; padding: 0; }
    </style>
</head>
<body>

<header class="masthead">
    <div class="wrap">
        <a href="{{ $url('index') }}">
            <img src="{{ asset('img/ddhf-logo.svg') }}"
                 alt="Deutscher Dachverband für historisches Fechten">
        </a>
        <span class="claim">{{ __('site.brand_claim') }}</span>
    </div>
</header>

<nav class="main" aria-label="{{ __('site.main_menu') }}">
    <div class="wrap">
        @php
            // Short names, so one loop serves both languages: the current page is site.standings
            // in German and site.en.standings in English, and both answer to "standings".
            $current = \App\Support\SiteUrl::currentShortName();
        @endphp
        @foreach (['index', 'standings', 'tournaments', 'search'] as $name)
            <a href="{{ $url($name) }}"
               @if ($current === $name) aria-current="page" @endif>{{ __('site.nav.' . $name) }}</a>
        @endforeach

        {{-- The same page in the other language, not that language's front page. --}}
        <a class="lang"
           href="{{ \App\Support\SiteUrl::alternate() }}"
           hreflang="{{ \App\Support\SiteUrl::other() }}"
           lang="{{ \App\Support\SiteUrl::other() }}">{{ __('site.language.switch_to') }}</a>
    </div>
</nav>

<main>
    <div class="wrap">
        @yield('content')
    </div>
</main>

<footer class="site">
    <div class="wrap">
        <div class="foot-cols">
            <div>
                <h3>Deutscher Dachverband für historisches Fechten</h3>
                <p>DDHF e.&nbsp;V.</p>
                <p>Fuhlsbüttler Straße 472</p>
                <p>22309 Hamburg</p>
            </div>

            <div>
                <h3>{{ __('site.footer.membership') }}</h3>
                {{-- Unescaped because the sentence carries a link, and the only thing put into
                     it is the fixed markup below - nothing here comes from a request. --}}
                <p>{!! __('site.footer.membership_text', [
                        'link' => '<a href="https://ifhema.org/" rel="noopener">IFHEMA</a>',
                    ]) !!}</p>
            </div>

            <div>
                <h3>{{ __('site.footer.follow') }}</h3>
                <ul class="social">
                    <li><a href="https://www.facebook.com/DDHFeV/" rel="noopener me">Facebook</a></li>
                    <li><a href="https://twitter.com/ddhf_ev" rel="noopener me">X</a></li>
                    <li><a href="https://www.youtube.com/channel/UC9N3yXRjxtSa6mJ6ifoqDgQ" rel="noopener me">YouTube</a></li>
                    <li><a href="https://de.wikipedia.org/wiki/Deutscher_Dachverband_Historischer_Fechter" rel="noopener">Wikipedia</a></li>
                </ul>
            </div>
        </div>

        <div class="foot-bottom">
            <span>© {{ date('Y') }} Deutscher Dachverband für historisches Fechten e.&nbsp;V.</span>
            <ul>
                <li><a href="https://ddhf.de/impressum">{{ __('site.footer.imprint') }}</a></li>
                <li><a href="https://ddhf.de/datenschutz">{{ __('site.footer.privacy') }}</a></li>
                <li><a href="https://ddhf.de/">ddhf.de</a></li>
            </ul>
        </div>
    </div>
</footer>

{{--
    All of this runs at the end of the body, after the tables exist. An earlier attempt put the
    search script next to the filter form, which sits above its table - getElementById returned
    null there and the listener was never attached, so the box did nothing at all.

    Everything here is an enhancement of a page that already works: the dropdowns submit a plain
    GET form on their own, and the tables are readable unsorted.
--}}
<script>
    // --- filters: submit on change, so the button is only needed without a script -----------
    document.querySelectorAll('form.controls').forEach(function (form) {
        form.querySelectorAll('select').forEach(function (select) {
            select.addEventListener('change', function () { form.submit(); });
        });

        var button = form.querySelector('button[type="submit"]');
        if (button) button.hidden = true;
    });

    // --- free text: narrows the rows already on screen ---------------------------------------
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

    // --- sorting -----------------------------------------------------------------------------
    // A cell may carry data-sort with the value to sort by, which is how "4tel Finale" sorts
    // after "4." and a date sorts by day rather than by the digits it starts with.
    function sortValue(cell) {
        if (!cell) return '';
        return cell.dataset.sort !== undefined ? cell.dataset.sort : cell.textContent;
    }

    document.querySelectorAll('table[data-sortable]').forEach(function (table) {
        var headers = table.querySelectorAll('thead th');

        headers.forEach(function (header, index) {
            if (header.classList.contains('nosort')) return;

            header.classList.add('sortable');
            header.setAttribute('role', 'button');
            header.setAttribute('tabindex', '0');
            header.setAttribute('aria-sort', 'none');

            function sort() {
                var body = table.tBodies[0];
                var rows = Array.prototype.slice.call(body.rows);
                var descending = header.getAttribute('aria-sort') === 'ascending';

                headers.forEach(function (other) {
                    if (!other.classList.contains('nosort')) other.setAttribute('aria-sort', 'none');
                });
                header.setAttribute('aria-sort', descending ? 'descending' : 'ascending');

                rows.sort(function (a, b) {
                    var x = sortValue(a.cells[index]);
                    var y = sortValue(b.cells[index]);
                    var nx = parseFloat(String(x).replace(',', '.'));
                    var ny = parseFloat(String(y).replace(',', '.'));
                    var result = (!isNaN(nx) && !isNaN(ny) && String(x).trim() !== '' && String(y).trim() !== '')
                        ? nx - ny
                        : String(x).trim().localeCompare(String(y).trim(), 'de');

                    return descending ? -result : result;
                });

                rows.forEach(function (row) { body.appendChild(row); });
            }

            header.addEventListener('click', sort);
            header.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    sort();
                }
            });
        });
    });
</script>

</body>
</html>
