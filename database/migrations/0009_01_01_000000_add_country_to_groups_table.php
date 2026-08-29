<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Records where a club is based.
 *
 * The federation says who a club belongs to, not where it sits, and those are different questions
 * once the field is international. Shaped after fencers.nationality: a char(2) holding an ISO
 * 3166-1 alpha-2 code, labelled in German through App\Data\Countries.
 *
 * The 156 clubs on record are filled in here, from three sources in descending order of weight:
 * the federation they belong to, the hemaratings club directory, and - for the handful of clubs
 * that directory does not list - a look at where their fencers are registered. One club is left
 * empty on purpose; see the readme. A wrong country is worse than a missing one, because only the
 * missing one asks to be looked up.
 */
return new class extends Migration
{
    /**
     * A club in a national federation sits in that federation's country.
     *
     * The DDHF case is a ruling of Christian's - its members are German clubs. The same reading
     * carries to the Polish federation, and two of its four clubs (Fechtschule Gdańsk, Valkyrie
     * HEMA Wrocław) say so in their name anyway. The other federations on record have no clubs.
     */
    private const FEDERATION_COUNTRIES = [
        'DDHF'  => 'DE',
        'FEDER' => 'PL',
    ];

    /**
     * Clubs the hemaratings directory lists, with the country it gives them.
     *
     * Checking all 156 against that directory found exactly one disagreement: it flags INDES
     * Regensburg as Austrian while giving Regensburg as its town. That is an error on their side,
     * and the club is a DDHF member, so the rule above settles it and it is not listed here.
     */
    private const CLUBS_ON_HEMARATINGS = [
        'BEC Escrime'                                                    => 'FR',
        'Braunschweiger Fechtkultur'                                     => 'DE',
        'Brückenschlag'                                                  => 'DE',
        'Compagnia de Le Due Maestà'                                     => 'IT',
        'De Zwaardkring'                                                 => 'NL',
        'Dobrovolný šerm'                                                => 'CZ',
        'Fechtzirkel Herrenberg'                                         => 'DE',
        'Fencing Club Dresden'                                           => 'DE',
        'Gladius et Codex'                                               => 'CH',
        'Hema Copenhagen'                                                => 'DK',
        'HEMA Riga'                                                      => 'LV',
        'Historisch Vrijvechten Nederland'                               => 'NL',
        'Houw en Hoede'                                                  => 'NL',
        'INDES Salzburg'                                                 => 'AT',
        'Klub Sportowy Dawnych Europejskich Sztuk Walki "Greifenfechter"' => 'PL',
        'Københavns Historiske Fægteklub'                                => 'DK',
        'Krakowska Szkoła Fechtunku'                                     => 'PL',
        'Kunst des Historischen Fechtens Tirol'                          => 'AT',
        'Lanistae liberi Pragenses'                                      => 'CZ',
        'Le Cercle des Épées Libres'                                     => 'FR',
        'Marmaridae'                                                     => 'BE',
        'Örebro HEMA'                                                    => 'SE',
        'Poznańska Grupa Fechtunku "Salut"'                              => 'PL',
        'Reisläufer Bern'                                                => 'CH',
        "Sala d'arme Dell'Appeso"                                        => 'IT',
        'Schildwall Vorarlberg'                                          => 'AT',
        'Schule des inneren Schwertes'                                   => 'DE',
        'Silkfencing Team'                                               => 'PL',
        "Società d'Arme Major Militia"                                   => 'IT',
        'Sprezzatura'                                                    => 'AT',
        'Sprezzatura Geneva'                                             => 'CH',
        'Stockholmspolisens Idrottsförening Fäktning'                    => 'SE',
        'Zwaard & Steen'                                                 => 'NL',
    ];

    /**
     * Clubs hemaratings does not list, settled one at a time.
     *
     * Either the name gives a town, a region or a country outright - a place, never the language,
     * since Dutch may be NL or BE and German DE, AT or CH - or the club is not there but its
     * fencers are, and their entries name a club that is. Where that second road was taken, the
     * comment says so; which fencer carried the evidence is deliberately not written down here,
     * since it names a person and the country does not depend on who they are. That list is kept
     * outside the repository, with the club country section of the working notes.
     */
    private const CLUBS_SETTLED_ONE_BY_ONE = [
        'ARMA-PL'                          => 'PL',
        'Bellum Nobile Düsseldorf'         => 'DE',
        'Fechtclub Konstanz'               => 'DE',
        'Gebennensis Artium Gladiatorium Schola' => 'CH', // Gebennensis is Geneva
        'Kunst des Fechtens Jena'          => 'DE',
        'Kunst des Fechtens Leipzig'       => 'DE',
        'Mordschlag Łódź'                  => 'PL',
        'Ort Coburg'                       => 'DE',
        'SAEA Paris'                       => 'FR',

        // Settled through a fencer registered at "Ausardia" in Windsor.
        'Ausardia HEMA Club'               => 'GB',

        // Salvador is the town the club names, and a fencer of it is registered at "First Blood -
        // Historical Fencing" there.
        'Batalha Cenica Salvador'          => 'BR',

        // The only Paridon is "Škola šermu Paridon" in Brno.
        'Paridon'                          => 'CZ',

        // Settled through a fencer registered at "La Salle d'Armes Escrime Ancienne" in Paris.
        "Salle d'armes d'escrime ancienne" => 'FR',

        // Settled through a fencer registered at "Walk the Path Berlin".
        'Walk The Path'                    => 'DE',

        // Listed under a second name, "Fencer F.C. HEMA (Xiphos Academy of HEMA)" in Thessaloniki,
        // and settled through a fencer registered there.
        'Xiphos Academy'                   => 'GR',
    ];

    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->char('country', 2)->nullable()->after('abbreviation');
        });

        $this->recordWhereTheClubsAre();
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('country');
        });
    }

    private function recordWhereTheClubsAre(): void
    {
        // A fresh installation has no clubs, so there is nothing to fill in and nothing to check.
        if (DB::table('groups')->count() === 0) {
            return;
        }

        foreach (self::FEDERATION_COUNTRIES as $abbreviation => $country) {
            $federation = DB::table('federations')->where('abbreviation', $abbreviation)->value('id');

            if ($federation === null) {
                continue;
            }

            DB::table('groups')
                ->where('federation_id', $federation)
                ->whereNull('country')
                ->update(['country' => $country]);
        }

        $named = self::CLUBS_ON_HEMARATINGS + self::CLUBS_SETTLED_ONE_BY_ONE;

        $this->makeSureEveryNamedClubExists(array_keys($named));

        foreach ($named as $name => $country) {
            DB::table('groups')
                ->where('name', $name)
                ->whereNull('country')
                ->update(['country' => $country]);
        }
    }

    /**
     * The lists above match on the club name, and names carry the en dashes App\Traits\
     * NameNormalization writes. A typo would therefore not fail, it would silently leave the club
     * without a country - so the names are checked before anything is written.
     *
     * @param  list<string>  $names
     */
    private function makeSureEveryNamedClubExists(array $names): void
    {
        $missing = [];

        foreach ($names as $name) {
            $found = DB::table('groups')->where('name', $name)->count();

            if ($found !== 1) {
                $missing[] = $name . ' (' . $found . ' Treffer)';
            }
        }

        if ($missing !== []) {
            throw new RuntimeException(
                "Diese Vereinsnamen aus der Nacherfassung treffen nicht genau einen Verein. Bitte "
                . "den Namen in der Migration berichtigen, dann erneut migrieren:\n  - "
                . implode("\n  - ", $missing)
            );
        }
    }
};
