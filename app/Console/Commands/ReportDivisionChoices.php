<?php

namespace App\Console\Commands;

use App\Models\Federation;
use App\Models\Fencer;
use App\Models\Result;
use App\Models\Season;
use Illuminate\Console\Command;

/**
 * Lists everybody who competed in both categories of one weapon in one year.
 *
 * Those are the only people for whom the question "Damen+ or offen?" arises at all, and the only
 * ones who need a declaration. Where a season asks for one and none is recorded, the fencer is
 * ranked in neither list - which is deliberate, but has to be findable, and this is where it is
 * found.
 */
class ReportDivisionChoices extends Command
{
    protected $signature = 'standings:division-choices
        {--year= : Nur dieses Jahr}
        {--missing : Nur die Fälle ohne hinterlegte Wahl}';

    protected $description = 'Zeigt, wer in zwei Kategorien einer Waffe gefochten hat und wo er gewertet wird';

    public function handle(): int
    {
        // The standing is this federation's, so being ranked at all is measured against it.
        $own = Federation::own()?->id;

        $seasons = Season::with('standing.discipline', 'standing.division')
            ->when($this->option('year'), fn ($query, $year) => $query->where('year', $year))
            ->get();

        /** @var array<string, array{jahr: int, disziplin: string, seasons: array<int, Season>}> $gruppen */
        $gruppen = [];

        foreach ($seasons as $season) {
            $discipline = $season->standing?->discipline?->name ?? '?';
            $gruppen[$season->year . '|' . $discipline]['jahr'] = $season->year;
            $gruppen[$season->year . '|' . $discipline]['disziplin'] = $discipline;
            $gruppen[$season->year . '|' . $discipline]['seasons'][] = $season;
        }

        ksort($gruppen);

        $zeilen = [];
        $ohneWahl = 0;

        foreach ($gruppen as $gruppe) {
            if (count($gruppe['seasons']) < 2) {
                continue;   // one category only, so nothing was ever to be chosen
            }

            // Who has results in which of the sibling seasons.
            $proFechter = [];
            foreach ($gruppe['seasons'] as $season) {
                $ids = Result::query()
                    ->whereHas('tournament', fn ($query) => $query->where('season_id', $season->id))
                    ->whereNotNull('fencer_id')
                    ->distinct()
                    ->pluck('fencer_id');

                foreach ($ids as $id) {
                    $proFechter[$id][] = $season;
                }
            }

            foreach ($proFechter as $fencerId => $seiten) {
                if (count($seiten) < 2) {
                    continue;
                }

                $gewaehlt = null;
                foreach ($gruppe['seasons'] as $season) {
                    if ($season->fencers()->where('fencers.id', $fencerId)->exists()) {
                        $gewaehlt = $season;
                    }
                }

                $pflicht = collect($gruppe['seasons'])->contains(fn ($s) => $s->division_choice_required);

                if ($gewaehlt === null) {
                    $ohneWahl++;
                } elseif ($this->option('missing')) {
                    continue;
                }

                $fencer = Fencer::find($fencerId);

                // Only somebody this federation ranks appears in a standing at all, so only
                // their missing choice costs anything. The rest are listed because a membership
                // correction can move them into the same question overnight.
                //
                // Asked exactly the way StandingCalculator asks it, against the own federation
                // and not merely against "has a federation at all". Any federation would say yes
                // for a fencer of a foreign one, who is in neither of our lists and whose missing
                // declaration therefore costs nothing - a false alarm that reads like a wrongly
                // ranked member.
                //
                // Anonymisation is not asked about: an anonymised fencer keeps their place in the
                // standing, so the same question arises for them - and it is one nobody can
                // answer, since the entry says nothing about which category they entered. That is
                // worth seeing in the list rather than hiding.
                $wertbar = $own !== null && Result::query()
                    ->where('fencer_id', $fencerId)
                    ->where(fn ($query) => $query
                        ->where('federation_id', $own)
                        // The Kulanzregelung counts a single result without the federation, and
                        // it puts somebody in a standing just the same.
                        ->orWhere('counted_on_request', '<>', ''))
                    ->whereHas('tournament', fn ($query) => $query
                        ->whereIn('season_id', array_map(fn ($s) => $s->id, $seiten)))
                    ->exists();

                $zeilen[] = [
                    $gruppe['jahr'],
                    $gruppe['disziplin'],
                    $fencer?->display_name ?? "#{$fencerId}",
                    implode(' + ', array_map(fn ($s) => $s->standing?->division?->name ?? '?', $seiten)),
                    $gewaehlt?->standing?->division?->name ?? '— keine —',
                    $wertbar ? 'ja' : 'nein',
                    $pflicht ? 'ja' : 'nein',
                ];
            }
        }

        if ($zeilen === []) {
            $this->info('Niemand ist in zwei Kategorien einer Waffe angetreten.');

            return self::SUCCESS;
        }

        $this->table(
            ['Jahr', 'Disziplin', 'Fechter', 'Angetreten in', 'Gewertet in', 'Eigener Verband', 'Wahlpflicht'],
            $zeilen,
        );

        $mitglieder = collect($zeilen)->filter(fn ($z) => $z[5] === 'ja')->count();

        $this->newLine();
        $this->line(sprintf(
            '%d %s, davon %d ohne hinterlegte Wahl.',
            count($zeilen),
            count($zeilen) === 1 ? 'Fall' : 'Fälle',
            $ohneWahl,
        ));
        $this->line(sprintf(
            '%d davon %s den eigenen Verband und damit tatsächlich eine Rangliste.',
            $mitglieder,
            $mitglieder === 1 ? 'betrifft' : 'betreffen',
        ));

        // Only where the season insists, and only for somebody this federation ranks, does a
        // missing choice actually cost a place in a standing.
        $scharf = collect($zeilen)
            ->filter(fn ($z) => $z[6] === 'ja' && $z[5] === 'ja' && $z[4] === '— keine —')
            ->count();

        if ($scharf > 0) {
            $this->warn(sprintf(
                '%d davon in Saisons mit Wahlpflicht - diese Fechter stehen in KEINER der beiden Ranglisten.',
                $scharf,
            ));
        }

        return self::SUCCESS;
    }
}
