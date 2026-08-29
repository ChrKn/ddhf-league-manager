# Exchange

Working area for files that are handed back and forth but do not belong in the repository:
result sheets from organisers, screenshots of a scoring table, review files of an import.

Everything in here is git ignored except this README. Drop files in, take files out, nothing
gets committed by accident.

## Why not somewhere else

- **Not the project root.** Source files pile up next to `README.md` and `composer.json`, and a
  stray `git add -A` commits them.
- **Not `storage/app/private/`.** That is the `local` filesystem disk. Filament writes its
  exports and Livewire its uploads there, so hand delivered files would sit among files the
  application manages itself.

## Careful with personal data

Result sheets carry fencer names, clubs and placements. That is exactly the kind of data the
anonymisation process in the main README exists for, so:

- do not commit anything from here, even if git would let you
- delete a file once the import it belonged to is done
- an import review file is a copy of the source data and deserves the same treatment

## Commands that use this folder

Every file of an event belongs in one `prepare` run — someone entering several tournaments appears in several
files, and only within a run are they recognised as one person.

```shell
php artisan import:results-prepare \
    "storage/exchange/DDHF Turnier Rapier Open.xlsx" \
    "storage/exchange/DDHF Turnier Rapier Frauen+1.xlsx" \
    --tournament="DDHF Turnier Rapier Open=TURNIERID" \
    --tournament="DDHF Turnier Rapier Frauen+1=TURNIERID"

php artisan import:results-apply storage/exchange/import-review.csv --dry-run
```

Files have to be in the DDHF template, one tournament each; the header is checked. See the import section of the
main README for the columns. Test fixtures live in `tests/fixtures/import/` with invented people, so no result
sheet is needed to run the test suite.
