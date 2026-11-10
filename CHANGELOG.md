# Changelog

Notable changes to the DDHF standings system.  
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).  
Versions follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 1.8.0 - 2026-11-10

### Added
- A season can set its own threshold (`seasons.min_participants`, "Gewertet ab Teilnehmenden" in
  the panel), kept apart from its scoring matrix. Empty keeps the threshold the matrix implies, so
  no season changes. Shown in the standing's info panel and next to the season on the matrix page.
- Tournaments record how many had registered (`registered_count`, "Gemeldet"), as evidence only:
  it never enters a point. Results can be marked as given up (`withdrew`, "Aufgabe"), which changes
  neither the points nor the field size.
- Import: rows for people who registered and never fenced ("nicht angetreten", "n.a.", "DNS" …)
  are skipped, not counted towards the field, and reported as a proposal for "Gemeldet".
- Scored on decision: a tournament below the season's threshold can be scored on a decision of
  the federation (`scored_on_decision` with the grounds, `decided_on`, optionally the
  announcement). It lifts the season's threshold only: points come from the season's matrix at
  the field that fenced, and the matrix's own floor stays. Section "Wertung auf Beschluss" in the
  tournament form, with a warning where the registrations are missing.

### Changed
- Whether a tournament is scored is decided in one place, `SeasonScoring`, for the standing, its
  steps, the tournament pages, their JSON and the data browser alike.

### Fixed
- A band of zeros at the bottom of a matrix no longer counts as a column: a field below the
  threshold of a matrix that has one is no longer listed as a step of the standing.

## 1.7.0 - 2026-11-09

### Added
- A page per scoring matrix (`/wertung/{id}`, `/en/scoring/{id}`), linked from a new
  info panel on every standing. Not indexed, not in the sitemap.
- The main menu folds into a burger below 768 px.
- The footer shows the version, read from this file, with a link to it as a page at
  `/changelog`, not indexed and not in the sitemap. `deploy:check` compares the version
  with the latest git tag.

### Changed
- External links in announcements open in a new tab.
- "Verein" is now "Gruppe" on the public site.
- German texts use gender-neutral terms throughout, on the site and in the admin panel.

## 1.6.0 - 2026-11-07

### Added
- Announcements: the federation can post news such as corrected results, shown under
  `/meldungen` (`/en/news`) and on the front page.

## 1.5.0 - 2026-11-05

### Added
- Standings show how each placement moved since the previous event, and can be read as
  they stood after any event.
- Meta descriptions on the main pages.

### Changed
- The site is open to search engines; sitemap and robots.txt are active.

### Fixed
- The sitemap failed on servers with `short_open_tag` enabled.

## 1.4.0 - 2026-11-02

### Added
- Import: workbooks with one sheet per tournament; files can be added, replaced or removed
  while an import is open; every problem in every file is reported at once.
- Tournament systems are a table of their own and chosen from a list.

### Changed
- Tournament system and field size are set per tournament in the mapping form, no longer
  in the file.
- The field size is required when a tournament is entered by hand.
- The result import moved under "Systemverwaltung" in the panel menu.

## 1.3.0 - 2026-10-22

### Added
- Sitemap in both languages, named in robots.txt.
- The public pages as JSON under `/data`, described in `/llms.txt`.
- Import: starters already anonymised by the organisers get a checkbox of their own.

### Changed
- Logo and favicon are cached for a year under addresses that change with the file;
  the favicon was redrawn.

### Fixed
- Spacing between a tournament's facts and the filters below them.

## 1.2.1 - 2026-10-03

### Changed
- Tournament pages moved to `/turniere` (`/en/tournaments`).
- Every page is kept out of search indexes by header and meta tag.

## 1.2.0 - 2026-10-03

### Added
- A season can be held back until it is released.

### Changed
- Import: the chosen action decides whether a fencer is reused or created.

## 1.1.0 - 2026-10-02

### Changed
- The public site runs without a session and sets no cookies.

### Fixed
- The import review no longer hits Livewire's call limit.
- A club is created only once per import, whatever its spelling.

## 1.0.0 - 2026-08-29

First release.

- Admin panel with login, user and API key management, editing, import and export for
  every table, and a data browser.
- Result import from the panel: upload, name matching, review, write.
- Standings computed from scoring matrices in four modes (standard, zones, best three,
  ruleset).
- Public site in German and English: standings, tournaments, person search.
- Read-only REST API.
