# DDHF Rankings

> The official rankings of the Deutscher Dachverband für historisches Fechten e. V. (DDHF), the German umbrella federation for historical European martial arts (HEMA): one standing per weapon and division, recalculated each year from the tournaments that count towards it.

The site is in German at {{ url('/') }} and in English at {{ route('site.en.index') }}. Names of weapons, divisions, clubs and rounds are the federation's own and stay German in both.

Everything below is also available as JSON, one document per page, carrying exactly what that page shows. Only released seasons are published. Fencers who asked to be anonymised appear as "{{ \App\Models\Fencer::ANONYMOUS_NAME }}", with their rank and points but without club or tournaments; clubs that asked not to be named are left out. There is deliberately no document per person and no download of everything at once.

## Data

- [All standings]({{ route('data.standings') }}): every released standing and year, each linking to its full table as a page and as JSON.
- [All tournaments]({{ route('data.tournaments') }}): every tournament that counts towards a released standing, each linking to its full field.

## Pages

- [Standings]({{ route('site.standings') }}) ([English]({{ route('site.en.standings') }}))
- [Tournaments]({{ route('site.tournaments') }}) ([English]({{ route('site.en.tournaments') }}))
- [Announcements]({{ route('site.news') }}) ([English]({{ route('site.en.news') }})): what the federation announced in the last twelve months, such as corrected results. Not available as JSON.

## How a standing is scored

Each tournament awards points by placement and field size, from the season's points table. A standing adds those up according to its scoring mode: all points, the best zone, the best ruleset, or the best three tournaments. Results that did not count under that mode are listed with `"counts": false`. Equal points share a rank.
