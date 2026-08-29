## About this Project

This project aims to build a web application for managing standings records for the German Federation for Historical 
Fencing [DDHF](https://ddhf.de/) using the [Laravel Framework](https://laravel.com/).

## The road to v1

Roughly in this order, because each step depends on the one before it.

**Done.** Database structure, a CRUD interface behind a login, user authentication and management,
import and export for every table, a read-only REST API, and a data browser that puts the whole data
set on one readable page — inside the panel, because the root belongs to the public site. Every
season from 2019 to 2026 is in: 33 standings, 86 tournaments, 1860 results. The sheets they came from
arrived as xlsx, ods, csv, PDF and photographs of tournament brackets, and each was rewritten into the
template before it went anywhere near the database.

**Also done: the public site.** Standings and the tournaments they are made of, at `/`, in the
federation's colours and without a single record id on show. German at the root and English under
`/en`, one set of routes serving both. What is *not* translated is the vocabulary that comes out of
the database — discipline, division and tournament format stay German on the English page, because
what they are called in English is the federation's to say rather than ours to invent. Two labels in
code are in the same position: `ScoringMode::label()`, and `Placement::label()`, which the API also
hands out and so cannot follow a page's language without changing an interface other people depend
on.

**Also done: taking results in from the panel.** Uploading an event's result sheets, matching the
names in them against the database, going through the suggestions with somebody, and only then
writing — all of it at `/verwaltung/ergebnisimport` rather than on a terminal. The template is taken
as a spreadsheet or as text, whichever delimiter and encoding the sender's machine happened to use.
One tournament per file, created by the import out of the standing, the event, and what the file
says about its own field size. An import brings in what is new and never touches a result already there; correcting
something already imported is a different job and will get its own form, so that whoever is at the
screen can tell from the screen which of the two they are doing. See *Importing results*.

**1. Check the whole data set.** With every year in place, the computed standings can be held against
the published ones in one pass. This is the last moment where a wrong assumption is cheap to correct.

**2. Deploy.** **Where** is still open, and it is the last thing in the way. The address will be a
subdomain of ddhf.de. Mail is settled: it goes out through the federation's Google Workspace, and
*forgot my password* works — see *Mail* under Deploying. What the host has to be able to do is
therefore short: PHP, MySQL and something that fires every minute — a crontab entry, or a control
panel that can fetch a URL. Nothing about outgoing mail, since the application never posts a message
itself, and nothing about long-running processes, since the queue worker is started by that same
timer.

**3. Let other people in.** Accounts for the people who maintain the data, which is what the two
points above are for.

**4. Finish the public site.** The pages exist; three things are still open.

- **The English vocabulary**, as above. Until the federation supplies it, `/en` shows German terms
  where the data is German — visibly incomplete rather than invented. The four federations without an
  English name are the same question and should be asked in the same conversation.
- **A dashboard rather than a list** — *nice to have, and the federation's call.* What is there
  answers *where do I stand*; it does not yet show a season taking shape — who moved, which
  tournament changed the picture, how a club is doing across its weapons. That is a different page
  from the standings, not a longer one, and it sits outside what was asked for: a system that
  produces and shows the standings with less human effort. It gets built when the DDHF says what it
  wants on it, and not before — a dashboard nobody specified is a dashboard nobody reads.
- ~~**Search over people.**~~ Done: `/suche`, `/en/search`. A name gives back the standings it
  appears in, taken from `SeasonRanking` rather than from the results, so the search can only
  surface what a published table already shows — somebody who fenced for a club that is not a
  member is in no table and is not findable here either. No page per fencer, and `noindex`:
  findable on the site, not through a search engine.

This is the point at which the project replaces the tables it was built for.

**5. A reduced backend for maintenance.** Filament gives whoever is let in the full run of the data.
The destructive half of that is dealt with — see *Deleting* below — and what is left is the rest:
who may see which parts, and whether everybody with an account needs the same panel.

**6. A submission form real people can use.** Results reach the federation in whatever shape the
organiser had to hand, and every one of them is rewritten into the template by hand. Most of that is
avoidable: the common HEMA tournament programme's export in a handful of recognisable shapes, and a
form that accepts those directly takes the retyping out of the loop on both sides. The reading side
is ready for it: `App\Import\Formats\SheetFormat` is the shape a format has to deliver, and adding
one means writing a class and naming it in `SheetFormats::ALL`, with nothing else in the import
needing to know. What is missing is the formats themselves, and the part below.

**What a sheet says about a person is a suggestion, never an instruction.** The DDHF template
carries a name, a club and a federation and nothing else, so the question has not arisen yet. A
tournament programme's export may well carry a nationality or a date of birth, and then it does.
The rule is settled in advance, because it decides how those formats get built rather than being
discovered afterwards: what a sheet says about somebody already on record is **shown in the review
step with a tick beside it**, the same way the matching is, and never written silently. An empty
cell means nothing at all — never "delete what is there". A sheet arrives from outside, and nothing
from outside changes a record without somebody having looked at it.

**Submitted data is not live data.** Anything arriving through the form lands where it can be looked
at and corrected, and reaches a standing only when somebody with the right role releases it.

**Everything the form receives is hostile until proven otherwise.** The console commands read files
we prepared ourselves and may assume a well-formed sheet; a form open to organisers may not assume
anything. It is the first write path into this database from outside, and it has to be built as one:

- **A submission writes to its own tables and nowhere else.** The tables a standing is computed from
  stay reachable only through the review step. That makes *not live* a structural property rather
  than a promise.
- **The dangerous field is the one that names an existing record**, not the free text. What a
  submission may address has to follow from who is submitting, never from what was posted.
- **Master data is created by a reviewer, not by a submission.** A sheet naming a club or a fencer we
  do not have is the normal case — but if the import answers it by creating the record, the form
  becomes an open write into exactly the data everything else is matched against.
- **A spreadsheet is a program, an archive and a document at once.** xlsx and ods are zip containers
  full of XML: cap size and row count before parsing, never trust the extension or the sent content
  type, store the file outside the webroot under a name the system chooses.
- **A cell starting with `=`, `+`, `-` or `@` is a formula in the next program that opens it.**
  Results move through spreadsheets in both directions here, so a submitted cell can become a command
  on a reviewer's machine. Escape on the way out.
- **A submission is personal data about people who did not submit it.** It needs an account behind it
  rather than an open endpoint, and a limit on how much and how often one account can send.

## Open questions

### Waiting on the federation

**Which standing somebody is ranked in, when they fenced both.** A weapon can be fenced in two
categories in the same year — *Damen+* and *offen* — and a fencer belongs in one of them. The
structure for it is built and switched off: `fencer_season` records the choice, and
`seasons.division_choice_required` decides whether a season asks for one. It is off everywhere,
which keeps the years up to 2024 exactly as published, because until then the federation ranked the
same person in both lists at once. `php artisan standings:division-choices` lists the twenty-five
results this affects.

What is missing is the rule: does the federation want one category per person per weapon and year,
and does it apply retroactively? Neither can be read out of the data — in 2025 the practice is
inconsistent, with one fencer recorded only in Damen+, another only in open, and a third in both at
the same tournament.

**The template already carries the answer per tournament, and the import throws it away.** Column
five of the DDHF sheet is `RL-Gender`: the organiser saying which field somebody entered.
`ResultSheetReader::read()` skips it — `$row[3]` to `$row[5]` — without a comment, unlike the two
columns either side of it, which suggests a gap rather than a decision. It is not a property of the
person, and it is emphatically not `fencers.gender`; it is a declaration, which is exactly what
`fencer_season` holds.

It stays unread until the rule above is settled, because a column nobody evaluates, with a meaning
nobody has fixed, is worse than no column. Note also that an entry per tournament and a declaration
per season are not the same thing: somebody entered under two different categories within one
season is precisely the case the whole structure exists for, and the sheet cannot resolve it.

### Known faults, worth fixing before it goes live

**The import reads membership from the present day.** The federation written onto a result comes
from `Group::scoringFederation()`, which reads the memberships a club holds *now*, so importing an
old tournament for a club that has since joined or left gets it wrong in a way nothing catches.
Storing several memberships did not change this — it is a question of when, not of how many. Fixing the
cause would need joining and leaving dates somewhere the import can reach, and that was deliberately
decided against: membership history is the federation's to publish and none of a tournament system's
business. That leaves correcting by hand whenever old results are imported, or having the import ask
for the federation per row.

**A result records the club, not which of its locations.** A club can train in several towns, and
`results.group_id` does not say which one somebody fenced from. Should it be added? What makes it
worth deciding now is the cost of deciding later: a nullable `results.group_location_id` on an empty
column is a migration, while adding it once the system is live and 1860 rows deep means working out
retroactively where each of them was fenced from, which nobody can do. The two honest options are
*now, while it costs nothing* and *never*; the middle road is the expensive one.

## Deleting

A record may be deleted **while nothing points at it**, and not afterwards. That clears away an
event created by accident or a duplicate ruleset — which is what a delete is legitimately for — and
makes a hole impossible, a hole being by definition something that was pointed at. Where a record
should stop being used rather than cease to exist, the *Aktiv* switch already says so.

Two different failures used to hide behind that one button, and only one of them looked like a
failure.

The load-bearing references are `RESTRICT`, so the database refused them itself: a fencer with
results, a tournament with results, a season with tournaments. But it refused as a raw SQL error,
which tells whoever clicked nothing they can act on.

The dangerous ones are the references that are `SET NULL`, because those **succeeded**:

| Deleting a | quietly sets | with the effect that |
|------------|--------------|----------------------|
| federation | `results.federation_id` → null | **every standing empties** — a standing scores nobody whose result does not name our own federation |
| club       | `results.group_id` → null      | the club comes off every historical result |
| ruleset    | `tournaments.ruleset_id` → null | results drop out of a ruleset-mode standing |

No error, no warning, and nobody notices until the tables move. `App\Filament\Support\Dependents`
holds what protects what, in one file rather than a line per resource, so the list can be read
against the database's own foreign keys in one sitting — which is the only way to notice one going
unguarded.

Bulk deletion is gone from the tables of record altogether. A bulk delete that half succeeds is
neither a correction nor a clean failure, and nobody can tell afterwards which half went.

What stays freely deletable is working material: API keys, a club's aliases and locations, and
result imports.

## Who belongs to whom

A club can belong to more than one federation, and the two it belongs to are not the same sort of
thing. `federations` therefore holds a **kind** — see `App\Federations\FederationKind`:

| Kind            | What it is                                                   | On record         |
|-----------------|--------------------------------------------------------------|-------------------|
| **Dachverband** | a country's governing body                                   | DDHF, ÖFHF, FEDER |
| **Verbund**     | a school or network clubs join alongside one, across borders | INDES             |

INDES Kulmbach is in the DDHF and in INDES; INDES Salzburg is in the ÖFHF and in INDES. One column
could hold one of the two, and there was no honest way to pick.

**Membership does not travel.** Every club states its own, all of them, and nothing is inherited
through a network. Both clubs above are in INDES and only one of them is ours — passing membership
down through INDES would put Salzburg in a German standing. The other side of the same point: INDES
Halle is called that and is not a member. Nothing here ever reads a name.

**Being in the DDHF is what decides a standing.** No flag, nowhere to set it wrongly. A result
records the federation it was fenced for — ours where the club is a member, the club's national
federation otherwise, and never a Verbund, because belonging to INDES says nothing about who ranks
you. See `Group::scoringFederation()`.

That record is a snapshot taken at the import. Entering a membership today changes nothing that has
already been scored; it applies to what is imported afterwards. This is the same rule that keeps a
club changing federation from rewriting last year's standing.

**One exception, and it is written down rather than worked out.** The federation's Kulanzregelung
lets the last tournament somebody took part in before their club joined count, on application. It is
granted case by case and never automatically, because the thing that decides it — whether an
application was made and approved — appears in no date, no club and no result.

So `results.counted_on_request` holds the grounds, and a standing counts the result when it is
filled. Not by writing the federation onto the result: that column says who the club belonged to on
the day, back-filling it would state a membership that never existed, and the next person checking a
standing against the members list would find a discrepancy with nothing to explain it. An exception
should look like an exception. It is a free text field and not a switch, so a granted exception can
never stand there without saying who granted it. Set it in the panel on the result, or filter for
the ones that carry it.

## Running the Project Locally

### Requirements

- PHP 8.5 or newer, with the extensions the dependency tree asks for. `php artisan deploy:check`
  reads that list out of `composer.lock` and says which are missing, rather than repeating it here
  where it would go stale. As of writing there are seventeen, all but three of them compiled into
  every ordinary PHP build.
- Composer 2
- Node.js 20 or newer — **for development only**. Nothing on a server needs it: the site uses no
  Vite bundle and Filament ships its assets built.
- MySQL 8.0.3 or newer, or MariaDB

The three worth checking on a host you do not control are **`intl`**, which `filament/support`
requires and which is not always switched on; and **`zip`** and **`xmlreader`**, which OpenSpout
needs to read the xlsx files an import arrives as.

`pdo_sqlite` belongs to the test suite rather than the server — the tests run against an in-memory
SQLite database, which is why `deploy:check` reads only the production half of the lock file.

The PHP floor is deliberate rather than inherited. Laravel 13 asks for 8.3, Filament for 8.2 and
PHPUnit 13 for 8.4.1; 8.5 is what the suite is actually run on, and saying so is worth more than
claiming a range nobody tests.

### First-time setup

```shell
composer setup
```

This installs the PHP dependencies, creates the `.env` file from `.env.example`, generates the application key, runs
the migrations, installs the npm packages and builds the frontend assets.

Adjust the `DB_*` values in `.env` to match your local MySQL server before running the setup, otherwise the migration
step will fail.

### Creating the first user

The admin interface has no self-registration, so the first user has to be created from the command line:

```shell
php artisan make:filament-user
```

The command asks for the name, email address and password interactively. To skip the questions, pass them as options
instead:

```shell
php artisan make:filament-user --name="Jane Doe" --email="jane@example.com" --password="secret"
```

Every further user can then be created through the Users section of the admin interface.

Do not use `php artisan db:seed` to create a user — the seeder also generates ten fake federations, which you do not
want in a real database.

### Starting the development environment

```shell
composer dev
```

This starts four processes at once and stops all of them when you press `Ctrl+C`:

| Process                | Purpose                                                        |
|------------------------|----------------------------------------------------------------|
| `php artisan serve`    | The application on http://localhost:8000                        |
| `php artisan queue:listen` | Works the queue: imports, exports, password mails            |
| `php artisan pail`     | Streams the application log to the console                      |
| `npm run dev`          | Vite dev server with hot reload                                 |

### Where to go from there

- **Public site:** http://localhost:8000 — standings and the tournaments they are made of, as readers
  see them. German here, English under `/en`.
- **Data browser:** http://localhost:8000/verwaltung/daten — every table in one place, for reading. Inside the
  admin panel, under *Datenbestand*, so the login in front of it is the panel's.
- **Admin interface:** http://localhost:8000/verwaltung (Filament panel, requires a login)
- **REST API:** http://localhost:8000/api/… — ready-made requests including a test API key are kept in `test.http` and
  can be executed directly from PhpStorm. API requests are authenticated with an ordinary bearer token —
  `Authorization: Bearer <key>` — and a refusal answers with `WWW-Authenticate`, so a client that has never seen this
  API can read what it was meant to send. Keys are managed in the admin interface; see *Keys are not readable* below.

### Other useful commands

```shell
composer test              # Run the test suite
php artisan migrate        # Apply pending migrations
php artisan optimize:clear # Clear all caches (config, routes, views)
php artisan deploy:check         # Is this server set up the way it should be
php artisan mail:test <adresse>  # Does mail actually leave this machine — see Mail, under Deploying
```

Files that are handed back and forth but do not belong in the repository — result sheets, screenshots, import review
files — go in `storage/exchange/`. Everything in there is git ignored; see the README in that folder.

### One person, entered twice

A name that arrives spelled differently from one year to the next becomes two records, because the
matcher will not join what it cannot be sure of — which is the right answer for a matcher to give.
Somebody who *is* sure says so:

```shell
php artisan fencers:merge <bleibt> <wird aufgelöst> [...]   # --dry-run first
```

Records are named by their public id, never by their name: two records worth merging are two records
whose names differ, so the name is the wrong handle, and the id is what the data browser puts in
front of whoever noticed.

Results, import rows and ranking choices move to the surviving record; a field the survivor has
nothing in is filled from the others where they agree and left empty where they do not — knowing
that two records are one person is no reason to think the command can pick between two birth dates.
It refuses when the merge would give one person two placements in one tournament, and when either
record has been anonymised, since what identified them is gone and there is nothing left to check
"same person" against.

The merged record is deleted and its id stops resolving. Take the dump first.

### Looking at a Best Three standing

Best Three adds a fencer's three best tournaments and drops the rest, so the page has to show which
three: the counting results come first, the others are greyed out below a rule. The federation has
never fenced more than two tournaments in one season, so that case is nowhere in the live data and
cannot be looked at there.

```shell
php artisan standings:preview-best-three          # three fencers, five tournaments, prints the URLs
php artisan standings:preview-best-three --remove # and away again
```

Everything it creates is written down as it goes, and `--remove` deletes exactly those ids and
nothing else — the live data is being built for real, and a demonstration standing must not end up
in it. While it stands it appears in the standings list like any other.

## Importing results

An import **brings in what is new**. It creates one tournament per file and writes the results
underneath it. It never changes a result that is already there: correcting one is a different job,
and the way to it is to take the import back and apply it again.

**This is the only way results enter the system**, and it has a review step. The panel used to
carry a generic importer on every table as well — upload a csv, create records — which was
scaffolding from the days of emptying and refilling the database while it was being built. Those
are gone. The live system is stocked from a partial dump instead, which is what *Moving the data*
below describes. Filament's own `imports` and `failed_import_rows` tables are still there and stay
empty; they come with the package.

### From the panel

`/verwaltung/ergebnisimport` → *Ergebnisse hochladen*.

1. **Upload.** One import belongs to **one event**, chosen here, and holds all the files of it —
   the longsword, the sabre and the rapier of the same weekend. That is not only convenience:
   somebody who fenced several of them appears in several files, and only within a single import is
   that person created once instead of once per appearance. Each file is read on upload, so a file
   that is not the template — or a workbook holding two tournaments — is refused while the dialogue
   is still open.
2. **Map.** Per file: which standing and year, and whether the field is known to be recorded only
   in part. The tournament is **not** created here.
3. **Match** (*Abgleichen*). Every line is compared against the database and stored as a row,
   **carrying what the matcher believes**: the fencer it found, the club, and what to do with them —
   *Vorschlag verwenden* where somebody was found, *Neu anlegen* where nobody was. The one row it
   forms no belief about is one whose placement is not a placement (`Verletzung/Aufgabe` and the
   like); that cannot be written under any answer, so it is left blank and holds the import up.
4. **Review.** Read down a filled column and correct what is wrong, in the table itself — the
   fencer, the club and the action are all editable in place. What the belief is worth stays next
   to it as a confidence and a score, *Nur unsichere Vorschläge* filters to the ones worth a second
   look, and how many there are is named again in the confirmation before the write.
5. **Apply.** One tournament per file, then the results. Its field size and system come from the
   file — those two are exactly what would otherwise be copied across by hand, and the field size is
   what every point in the standing is computed against. *Probelauf* does the whole thing and rolls
   it back.

**The template is accepted as a spreadsheet or as text.** A csv may be separated by semicolons,
commas or tabs, and may be UTF-8 or the cp1252 that Excel writes on a German machine. None of that
is asked of the sender: each delimiter is tried and the one whose header comes out as the template
wins, and the encoding is decided by whether the bytes are valid UTF-8. Reading a name as the wrong
encoding loses its umlauts silently, which is the one thing a list of people may not do. The
extension is not trusted either — a workbook saved as `.csv` and a csv named `.xlsx` both go
through, because the file itself can be made to say which it is.

The tournament is created at step 5 and not at step 2 on purpose: a review somebody abandons then
leaves nothing behind, rather than an empty tournament that later reads as a real one whose results
nobody entered.

**Three guards against importing the same thing twice**, which matters more here than it used to,
because a second run would no longer produce a duplicate result — it would produce a second
tournament with a full field, and nothing downstream would look wrong:

- a tournament of that standing already exists at that event;
- two files of one import are headed for the same standing — and since the import is one event,
  that is the same thing;
- a row whose fencer already has a result in that tournament is set aside and counted separately in
  the report, rather than skipped quietly.

### Taking one back

`Import zurücknehmen`, on the review page of an import that has been applied. There is a dry run
beside it that says what would go.

It removes **the results this import wrote** — named by the `result_id` each row kept, not searched
for — and a tournament that is left with none, since it only ever existed for this import. A
tournament that still holds a result somebody entered by hand stays.

Fencers and clubs stay. A later import may be using them by now, and one without results bothers
nobody; the summary names those that are left over so somebody can decide. Remembered club
spellings stay too — that a spelling means that club is still true afterwards.

It refuses when **a result has been edited since the import was applied**. Somebody has corrected
something by hand, and taking the import back would throw that away without asking.

Afterwards the import is back in review with its rows intact, so the mistake can be corrected and
the import applied again. That is the point of it: a mis-import is a thing to redo, not a thing to
repair by hand.

### From the console

The same matching and the same writing, for when the panel cannot be reached. The review step is a
csv instead of a table, and the tournament has to exist beforehand.

```shell
php artisan import:results-prepare storage/exchange/2026/*.xlsx \
    --tournament="Dateiname=T7H565C5" --out=storage/exchange/import-review.csv
php artisan import:results-apply storage/exchange/import-review.csv --dry-run
```

Only `aktion` and the two id columns are meant to be edited; `hinweis` is the matcher talking back.
`aktion` takes `use`, `create`, `create_inactive` (for a row the organisers had already anonymised)
or `skip`. `--remember-aliases` records corrected club spellings so the next import resolves them on
its own.

A spelling names one club. The lookup folds a name before it compares — lower case, no punctuation,
no `e. V.` — so `ESK Augsburg` and `ESK-Augsburg e. V.` are one key, and the same key belonging to
two clubs would resolve to whichever row was read last. Several clubs already carry a handful of
spellings that fold together; that is harmless, because each set sits on one club. What is refused
is the other case: a spelling another club already holds, or one that is another club's *name* —
a name wins over an alias, so such a row would be stored and then never read. The importer leaves
the recorded decision standing, writes the results under the club the review screen picked, and says
in the summary that the spelling was not remembered. The panel refuses the entry outright.

## Deploying

Nothing is deployed yet, and two things have to be settled before anything is — see *The road to v1*.
**Where** is undecided, and **no mail transport is configured**, so *forgot my password* does not
work. Until it does, the first account has to be created with a password somebody knows, and nobody
who locks themselves out can get back in.

### Moving the data

The database prepared locally can be transferred. Every table here is `utf8mb4_unicode_ci`, and with
the umlauts, the ß and the Polish and Greek names in this data that is the one thing that must not be
got wrong: create the live database with the same character set and pass `--default-character-set=utf8mb4`
at both ends.

**1. Let the live system build its own schema.**

```shell
php artisan migrate
```

The tables then match the code exactly and the `migrations` table records what has run. Do not copy
the schema from here.

**2. Dump the content, and only the content.**

```shell
mysqldump -u user ddhf_standings --default-character-set=utf8mb4 \
  --no-create-info --complete-insert \
  --result-file=inhalt.sql \
  disciplines divisions standings scoring_matrices seasons rulesets \
  federations groups group_aliases group_locations fencers \
  events event_group tournaments results fencer_season
```

That order is not alphabetical and should not be sorted: no row can be inserted before the row it
points at.

`--result-file` rather than `> inhalt.sql`, and that is not a matter of taste: PowerShell writes a
redirection in UTF-16 with a byte order mark in front of it, and MySQL then stops on the very first
statement with *ASCII '\0' appeared in the statement*. Letting mysqldump write the file itself means
no shell touches the bytes.

**3. Import it, then create the accounts by hand.**

```shell
mysql -u <user> -p <database> --default-character-set=utf8mb4
```

```
source /path/to/inhalt.sql
```

Read in by the client rather than piped through `< inhalt.sql`, because `-p` without a password
attached asks for one, and an ask with stdin redirected reads the first line of the SQL file as the
password. If the password has a quote in it, put it in `~/.my.cnf` (mode 600, the value unquoted)
and leave `-u` and `-p` off entirely.

```shell
php artisan make:filament-user
```

The API key is created afterwards in the admin interface. Do not carry one over — see below.

### What must not travel

**`storage/exchange/` above all.** It holds the membership list, the working notes with people's real
names, and every database dump ever taken. It is git ignored, so a deployment over git leaves it
behind on its own — but `rsync -a storage/` would take it along. This is the step where a
convenience turns into a data protection incident.

The tables left out of the dump above are left out on purpose:

| Table                               | Why it stays here                                            |
|-------------------------------------|--------------------------------------------------------------|
| `users`                             | one local account, with a local password                     |
| `api_keys`                          | hashed against the local `APP_KEY` — the rows are dead weight there |
| `sessions`, `cache`, `cache_locks`  | local state, meaningless elsewhere                           |
| `exports`, `imports`, `job_batches` | they point at files that do not exist there                  |
| `failed_import_rows`                | 759 rows of working material from here                       |

The results themselves are not encrypted, so `APP_KEY` does not matter for the data. It matters for
sessions and cookies, and for `api_keys` — see below. The live system generates its own.

### Keys are not readable

An API key is stored as an HMAC-SHA256 digest of itself, keyed with `APP_KEY`, alongside its first
eight characters. Nothing in the table can be turned back into a working key, which is what makes a
dump of it harmless — and it is why the row is worthless on any other installation, since the secret
it was hashed with stayed behind.

The consequence is a rule and not a setting:

- **A key is shown exactly once**, on the page you land on after creating it, with a copy button. It
  is never displayed again, and nobody — including whoever runs the server — can look it up.
- **A lost key is replaced, not recovered.** *Neuen Schlüssel erzeugen* on the key's page issues a new
  one and invalidates the old one the moment it is confirmed.
- **`APP_KEY` must not change** on a running installation. It never should anyway, because sessions
  and cookies depend on it; the difference here is that changing it invalidates every API key at once,
  silently, and the only cure is issuing them all again.

Sixty-four characters drawn from an alphabet of thirty-two is three hundred and twenty bits, so the
digest needs neither a salt nor a slow hash: there is nothing to guess. Showing the first eight
leaves two hundred and eighty.

### One thing on a timer

Everything this application does on its own hangs off one timer, and nothing else. `php artisan
schedule:list` shows what it drives. Two things, at the moment:

- **Finished exports are deleted** after four weeks, with their files and their notifications.
- **The queue is worked**, a minute at a time — imports, exports, and the password reset mail.

That second one is worth a word, because it is not how a queue is usually run. There is normally a
worker process that is started once and left alone; shared hosting has nowhere to keep one. So
instead of a worker that lives forever, the timer starts one that drains what is there and exits:
`queue:work --stop-when-empty --max-time=…`, guarded by `withoutOverlapping(10)` so an export that
takes longer than a minute does not collect a second worker on top of it. The ten minutes there are
deliberate: that lock otherwise holds for a day, and a worker the host kills rather than lets finish
never releases it — which would stop the queue until tomorrow without a word being said.

The application never notices any of this — same connection, same `jobs` table, same code doing the
dispatching. **On a development machine the timer does nothing at all**, because nothing there runs
`schedule:run`: `composer dev` keeps a real `queue:listen` open instead, which reloads on a code
change and is what you want while writing a job. Local and server differ in who drains the queue,
not in anything that is deployed.

#### On a host with a crontab

One line, and it is the whole of it:

```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

Leave `CRON_WORKER_SECONDS` alone; the default of 55 gives the worker the minute it has.

#### On a host without one

All-Inkl, where this runs, has no crontab. Its control panel offers one kind of timer: **fetch a URL
every minute**. So the scheduler is reachable over HTTP, at `/cron`, and the differences between a
crontab entry and a web request are the whole of `App\Http\Controllers\CronController`.

```
https://ranglisten.ddhf.de/cron
```

with the panel's *HTTP Benutzer* and *HTTP Passwort* fields filled from `CRON_USER` and
`CRON_PASSWORD`. Three things about it are load-bearing:

- **Blank credentials close the route rather than open it.** With either setting empty it answers
  404 to everybody — including whoever also sends nothing, which is the mistake this guards against.
  That is why the route can sit registered on a development machine that never calls it.
- **A refusal is a 404, not a 401.** An address that answers *wrong password* has confirmed it is the
  right address.
- **Silence when the run went well.** The panel mails whatever comes back, once a minute, so a
  success that returns output is 1440 mails a day and a filter rule to hide them — which is how
  monitoring turns into noise nobody reads. `/cron` answers 204 with an empty body when the run
  succeeded and the error with a 500 when it did not, so the mail arrives exactly when there is
  something to know. Put an address in the panel's *E-Mail-Adresse* field and that is the whole
  alerting setup.

Two settings have to be right, and the second one cost an afternoon:

- **`CRON_WORKER_SECONDS`.** A crontab entry gives the worker the minute; a web request gets cut off
  by the server long before that. Twenty seconds is a safe start. Too high and every run is killed
  mid-job — which the ten-minute lock then covers, but killed work is still killed work.
- **`CRON_PHP_BINARY`.** `schedule:run` does not run `queue:work` itself; it starts it as its own
  process, and has to know where PHP is. Symfony's finder takes the running interpreter only when
  the SAPI is `cli` — in a web request it is not, so it guesses: `PHP_BINDIR`, then the PATH. On a
  host that keeps several versions side by side that guess is a PHP this application cannot run on.
  The subprocess then dies, the scheduler throws its output away, and `schedule:run` reports success
  over a queue nothing touched. `php artisan deploy:check` prints the path that belongs here.

The second one is worth recognising by its symptom, because it looks like nothing at all: `/cron`
answers 204, `deploy:check` is green, and a job sits in `jobs` for ever. `php artisan schedule:run`
from a shell empties the queue immediately — and that difference between the shell and the URL *is*
the diagnosis. `/cron` now checks the binary before it starts and answers 500 rather than pretending,
so the same fault today arrives as a mail.

Whichever of the two it is: without it nothing runs, and nothing says so out loud — but
`deploy:check` notices, because work lying in the queue unclaimed is exactly what a timer that never
fires looks like from the outside.

### Not to be found yet

The site is finished before it is meant to be public, and those are two different days. In between
it answers to anybody who knows the address, and it should still not turn up in a search result —
so `SITE_INDEXABLE=false` in the environment, which sends `X-Robots-Tag: noindex, nofollow` with
every response.

A header, and **not** a `Disallow: /` in `robots.txt`. Those are not two ways of saying the same
thing. `Disallow` tells a crawler not to fetch the page — so it never reads the noindex either, and
an address it learned about from somewhere else can still be listed, without a description, which
is worse than either outcome on purpose. Letting it in and telling it to keep nothing is the
instruction that works, which is why `public/robots.txt` stays open and a test says so.

Switching it back on is one line in the environment and `php artisan optimize`. Nothing is deployed
for it.

### Mail

*Forgot my password* is the only thing this application sends, and it is what lets anybody but the
maintainer be given an account at all. It goes out **through the federation's Google Workspace**, as
`ranglisten@ddhf.de`, over Gmail's SMTP.

That detour is the whole point. A message a web server posts itself arrives without SPF and DKIM
that line up with the sending domain and lands in spam — reliably so at GMX, Web.de, T-Online and
Outlook, which is most of the federation. Handed to Google, **Google is the sender**: SPF passes
because ddhf.de already authorises it, and Google signs with the domain's own DKIM key. **There is
nothing to add to the DNS**, and nothing about outgoing mail the host has to allow.

In Google, once:

1. The mailbox `ranglisten@ddhf.de` exists.
2. Two-step verification is on for it — without that there are no app passwords.
3. Create an app password (Account → Security → App passwords). Sixteen characters, and it goes
   into `MAIL_PASSWORD` on the server, nowhere else.

Then the block in `.env.example`, uncommented. Two things there are easy to get wrong:

- **`MAIL_SCHEME` stays empty.** Laravel reads the scheme off the port — 465 means implicit TLS,
  anything else means STARTTLS, which is what 587 wants. `tls` is not a valid value.
- **`MAIL_FROM_ADDRESS` must be the mailbox that authenticates**, or an alias verified in Gmail
  under *send mail as*. Otherwise Gmail rewrites the sender and the address people see is not the
  one configured.

Check it before trusting it:

```shell
php artisan mail:test ich@example.de
```

It names the transport it is about to use — if that says `log`, it refuses rather than reporting
success — sends straight past the queue so a rejection is visible, and names the three usual causes
when one comes back. In the message that arrives, look at the headers: `spf=pass`, `dkim=pass` and
`dmarc=pass` for ddhf.de is the actual proof that the detour did its job.

### On the day

- **Check that the timer is actually there and firing.** It fails quietly, and it carries the
  password reset as well, so without it the message is never sent and the page still says it is on
  its way. Requesting a reset and watching the mail arrive is the whole chain in one go; `select
  count(*) from jobs` on a quiet system should be nought a minute later. Where the timer is a URL,
  `curl -u USER:PASSWORD -i https://…/cron` should give back `204` and nothing else — and the same
  address without credentials should give back `404`.
- **`php artisan deploy:check`** first — it asks the rest of this list for you and exits non-zero if
  anything is wrong, so it can also be left in a cron entry. The one it matters most for is
  `APP_DEBUG`: left on, Laravel's error page hands out the whole `.env` to anybody who can provoke
  an exception.
- **`php artisan mail:test`**, then walk through a real reset at `/verwaltung/password-reset/request`.
  The first says mail leaves the machine; only the second says the whole path works.
- **Anybody with an account can open the whole panel**, data browser included — there are no roles,
  and `User::canAccessPanel()` says so in as many words. Accounts are made by hand; that is the only
  gate. Narrowing it is readme point 5.
- **Decide whether the site is to be found.** `curl -sI https://…/ | grep -i x-robots-tag` says
  which way the switch is set. There is no right answer here, only a decision that has been made —
  see *Not to be found yet*.
- **Create an API key** and hand it out; the one in `test.http` is local. Copy it off the screen while
  it is there — it is shown once and cannot be looked up afterwards, only replaced.
