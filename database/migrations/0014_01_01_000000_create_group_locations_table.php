<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Records where a club actually trains.
 *
 * `groups.country` says which country a club belongs to; it does not say where anybody meets, and
 * a club can meet in several towns. The Europäische Schwertkunst trains in seven, the Fechtschule
 * Krîfon in five, and reducing that to one line would be a decision rather than a record - so the
 * places get a table of their own.
 *
 * Filled from <https://ddhf.de/mitglieder/>, which prints "Training in" and "Land" per member,
 * and for the two Austrian INDES clubs from their own name. 98 of the 99 clubs the page lists are
 * on record here and are matched by name; the Merseburger Raben are not in the database at all
 * and stay an open point. Clubs outside the DDHF keep no location for now: their towns would have
 * to be read off a directory rather than a member list, and a guessed place is worse than none.
 */
return new class extends Migration
{
    /**
     * Club name on record => the towns it trains in, with the federal state of each.
     *
     * The website writes both as comma separated lists that do not always line up - five towns
     * against three states for the Krîfon, one town against two states for the Schildwache - so
     * those two were assigned town by town. Everything else is either one state for every town or
     * two lists of equal length.
     */
    private const TRAINS_IN = [
        'ANNO 1838 – Hau = Stoßfechten e. V.'               => [['Hamburg', 'Hamburg']],
        'ASV Ludwigsburg-Oßweil Historische Kampfkünste'     => [['Ludwigsburg', 'Baden-Württemberg']],
        'ASV Mannheim "Schwertfisch"'                            => [['Mannheim', 'Baden-Württemberg']],
        'Academia da Espada Deutschland'                         => [['Iggelheim', 'Rheinland-Pfalz']],
        'Baukauer Turnclub 1879 e. V. Abteilung Historisches Fechten' => [['Herne', 'Nordrhein-Westfalen']],
        'Bis Behend'                                             => [['München', 'Bayern']],
        'Bunte Schar'                                            => [['Budenheim', 'Rheinland-Pfalz']],
        'De Schola Pugnator'                                     => [['Roding', 'Bayern']],
        'Deathcorps Unicorns'                                    => [['Goslar', 'Niedersachsen']],
        'Der Fechtboden'                                         => [['Großefehn', 'Niedersachsen']],
        'Deutsches Klingenmuseum'                                => [['Solingen', 'Nordrhein-Westfalen']],
        'Die Freifechter – Gesellschaft für Historische Fechtkunst e. V.' => [['Köln', 'Nordrhein-Westfalen']],
        'Dimicator Schola'                                       => [['Hamburg', 'Hamburg']],
        'Discipuli – historisches Fechten'                     => [['Berlin', 'Berlin']],
        'Drei Helme der TG Landshut'                             => [['Landshut', 'Bayern']],
        'Drei Klingen'                                           => [['Augsburg', 'Bayern']],
        'Europäische Schwertkunst'                              => [
            ['München', 'Bayern'],
            ['Puchheim', 'Bayern'],
            ['Starnberg', 'Bayern'],
            ['Erding', 'Bayern'],
            ['Einsassen / Gerolsbach', 'Bayern'],
            ['Augsburg', 'Bayern'],
            ['Pleinfeld', 'Bayern'],
        ],
        'Fechtboden Kurpfalz'                                    => [['Mannheim', 'Baden-Württemberg']],
        'Fechtergilde Aurich e. V.'                            => [['Aurich', 'Niedersachsen']],
        'Fechtschule Asteria'                                    => [['Lüneburg', 'Niedersachsen']],
        'Fechtschule Krîfon'                                    => [
            ['Edingen', 'Baden-Württemberg'],
            ['Herford', 'Nordrhein-Westfalen'],
            ['Koblenz', 'Rheinland-Pfalz'],
            ['Mainz', 'Rheinland-Pfalz'],
            ['Worms', 'Rheinland-Pfalz'],
        ],
        'Fechtschule Schrankhut e. V.'                         => [['Püttlingen', 'Saarland']],
        'Fechtwerk – Historisches Fechten im Allgäu'          => [['Kempten', 'Bayern']],
        'Freifechter im MTV Giessen'                             => [['Gießen', 'Hessen']],
        'Freifechter zu Willich'                                 => [['Willich', 'Nordrhein-Westfalen']],
        'Freyfechter Augustini'                                  => [['München', 'Bayern']],
        'Gassenhauer TuS 08 Brakelsiek e. V.'                  => [['Brakelsiek', 'Nordrhein-Westfalen']],
        'Gilde Bonn – Bonner Schule e. V.'                   => [['Bonn', 'Nordrhein-Westfalen']],
        'Gladius Strictus'                                       => [['Darmstadt', 'Hessen']],
        'Grün-Weiß Holten e. V.'                           => [['Oberhausen', 'Nordrhein-Westfalen']],
        'HEMA Finsterwalde'                                      => [['Doberlug-Kirchhain', 'Brandenburg']],
        'HEMA Köln e. V.'                                     => [['Köln', 'Nordrhein-Westfalen']],
        'Hammaborg – Historischer Schwertkampf e. V.'        => [['Hamburg', 'Hamburg']],
        'Henger Sportverein 1963 e. V. – Abteilung Schwertkampf' => [['Postbauer-Heng', 'Bayern']],
        'Historische Kampfkünste Neu-Ulm (TSV Neu-Ulm)'     => [['Neu-Ulm', 'Bayern']],
        'Historisches Fechten Gießen e. V.'                   => [['Gießen', 'Hessen']],
        'Historisches Fechten Greifswald e. V.'                => [['Greifswald', 'Mecklenburg-Vorpommern']],
        'Historisches Fechten Würzburg e. V.'                 => [['Würzburg', 'Bayern']],
        'Historisches Fechten im PSV Karlsruhe'                  => [['Karlsruhe', 'Baden-Württemberg']],
        'Historisches Fechten im RASPO e. V.'                  => [['Osnabrück', 'Niedersachsen']],
        'Historisches Fechten – Judo-Club Herrenberg e. V.' => [['Herrenberg', 'Baden-Württemberg']],
        'Historisches Fechten – Osnabrück e. V.'            => [['Osnabrück', 'Niedersachsen']],
        'Historisches Schwertfechten Nordhessen e. V.'         => [
            ['Kassel', 'Hessen'],
            ['Bad Wildungen', 'Hessen'],
        ],
        'Historisches-Fechten Sportbund DJK Rosenheim e. V.' => [['Rosenheim', 'Bayern']],
        'Hospitaliter zu Magdeburg'                              => [['Magdeburg', 'Sachsen-Anhalt']],
        'IHS Schrobenhausen'                                     => [['Schrobenhausen', 'Bayern']],
        'IN MOTU'                                                => [['Nordhausen', 'Thüringen']],
        'INDES Kulmbach'                                         => [['Kulmbach', 'Bayern']],
        'INDES Regensburg'                                       => [['Regensburg', 'Bayern']],
        'INDES – Historische Fechtkuenste Halle a.d. Saale e. V.' => [['Halle an der Saale', 'Sachsen-Anhalt']],
        'Ju Jutsu Dojo Geisenheim'                               => [['Geisenheim', 'Hessen']],
        'Kampfhûs e. V.'                                      => [['Hirschhorn', 'Hessen']],
        'Kenshinkai Berlin e. V. Sektion Schwertkampf'         => [['Berlin', 'Berlin']],
        'Kielhau'                                                => [['Kiel', 'Schleswig-Holstein']],
        'Knirsch und (Hau)'                                      => [['Hof', 'Bayern']],
        'Leichtmeisterei – Historisches Fechten Kassel e. V.' => [['Kassel', 'Hessen'], ['Schwalmstadt', 'Hessen']],
        'Leipziger Klingen e. V.'                              => [['Leipzig', 'Sachsen']],
        'Manus ad Gladio'                                        => [['Salzwedel', 'Sachsen-Anhalt']],
        'Mittelalterliches Fechten Victoria Lauenau e. V.'     => [['Lauenau', 'Niedersachsen']],
        'Montantero Bonn – Bonner Schule e. V.'              => [['Bonn', 'Nordrhein-Westfalen']],
        'Neue Marxbrüder'                                       => [['Frankfurt am Main', 'Hessen']],
        'OSC Berlin e. V.'                                     => [['Berlin', 'Berlin']],
        'Ochs – historische Kampfkünste e. V.'              => [['München', 'Bayern']],
        'Orbis Martial Arts'                                     => [['Waiblingen', 'Baden-Württemberg']],
        'PSV Cottbus'                                            => [['Cottbus', 'Brandenburg']],
        'Pfälzer Schwertlöwen'                                 => [
            ['Neustadt an der Weinstraße', 'Rheinland-Pfalz'],
        ],
        'Ritterschaft zu Gmünd'                                 => [['Schwäbisch Gmünd', 'Baden-Württemberg']],
        'Ronneburger Fechtschule'                                => [['Sprendlingen', 'Hessen']],
        'SC Borchen'                                             => [['Paderborn', 'Nordrhein-Westfalen']],
        'Saalefechter'                                           => [['Rudolstadt', 'Thüringen']],
        'Schildwache Potsdam e. V.'                            => [['Potsdam', 'Brandenburg']],
        'Schwabenfedern'                                         => [['Ulm', 'Baden-Württemberg']],
        'Schwert und Bogen'                                      => [['Nürnberg', 'Bayern']],
        'Schwertbund Nurmberg e. V.'                           => [['Nürnberg', 'Bayern']],
        'Schwertkampf – Lüneburg'                             => [['Lüneburg', 'Niedersachsen']],
        'Schwertspiel Verein für traditionelle Kampfkunst e. V.' => [['Dresden', 'Sachsen']],
        'Schwert-Greifen Rostock Verein für historische Kampfkunst e. V.' => [
            ['Rostock', 'Mecklenburg-Vorpommern'],
            ['Dessau', 'Sachsen-Anhalt'],
        ],
        'Scuola di Scherma e. V.'                              => [['Koblenz', 'Rheinland-Pfalz']],
        'Sieben Schwerter'                                       => [['Ludwigsburg', 'Baden-Württemberg']],
        'Siegburger Fechtschule'                                 => [['Siegburg', 'Nordrhein-Westfalen']],
        'Solve et Coagula'                                       => [['Fulda', 'Hessen']],
        'Stahlakademie'                                          => [['Leipzig', 'Sachsen']],
        'TSV 1876 Nobitz'                                        => [['Nobitz', 'Thüringen']],
        'TSV Schwaigern 1898 e. V. Abt. Historisches Fechten'  => [['Schwaigern', 'Baden-Württemberg']],
        'TSV Silberstedt e. V. Sulverstede Armatus'            => [['Silberstedt', 'Schleswig-Holstein']],
        'Tremonia Fechten e. V.'                                 => [['Dortmund', 'Nordrhein-Westfalen']],
        'TuS Grün-Weiß Himmelsthür e. V. – Historisches Fechten' => [['Hildesheim', 'Niedersachsen']],
        'TuS Rot-Weiß Koblenz e. V.'                        => [['Koblenz', 'Rheinland-Pfalz']],
        'Turnergemeinde Münster von 1862 e. V.'               => [['Münster', 'Nordrhein-Westfalen']],
        'Twerchhau e. V.'                                      => [['Berlin', 'Berlin']],
        'Vehterkraejen – Historisches Fechten'                 => [['Arnstadt', 'Thüringen']],
        'Warriors Martial Arts Team'                             => [['Forchheim', 'Bayern']],
        'Wettiner Löwen e. V.'                                => [['Grimma', 'Sachsen']],
        'Widukinds Wächter e. V.'                             => [['Magdeburg', 'Sachsen-Anhalt']],
        'Winkelfechter'                                          => [['Achim', 'Niedersachsen']],
        'Zornhau – historische Fechtkunst e. V.'             => [['Offenbach', 'Hessen']],
        'Zwerch von Links – Historischer Schwertkampf e. V.' => [['Kaiserslautern', 'Rheinland-Pfalz']],
        'strîtschar'                                            => [['Sexau', 'Baden-Württemberg']],
    ];

    /** Austria's INDES names its locations, and two of them are clubs on record here. */
    private const AUSTRIAN = [
        'INDES Salzburg' => [['Salzburg', 'Salzburg']],
        'INDES Wien'     => [['Wien', 'Wien']],
    ];

    public function up(): void
    {
        Schema::create('group_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            // The town. Everything else about a place can be looked up from it.
            $table->string('locality');
            // The federal state, province or canton. Not every country has one worth naming.
            $table->string('region')->nullable();
            // Normally the club's own, but a club may well train across a border - the INDES
            // locations do exactly that - so the place carries its own.
            $table->char('country', 2)->nullable();
            $table->timestamps();

            $table->unique(['group_id', 'locality']);
        });

        $this->recordWhereTheClubsTrain();
    }

    public function down(): void
    {
        Schema::dropIfExists('group_locations');
    }

    private function recordWhereTheClubsTrain(): void
    {
        // A fresh installation has no clubs, so there is nothing to fill in and nothing to check.
        if (DB::table('groups')->count() === 0) {
            return;
        }

        $places = self::TRAINS_IN + self::AUSTRIAN;

        $this->makeSureEveryNamedClubExists(array_keys($places));

        $now = now();
        $rows = [];

        foreach ($places as $name => $towns) {
            $group = DB::table('groups')->where('name', $name)->first();

            foreach ($towns as [$locality, $region]) {
                $rows[] = [
                    'group_id'   => $group->id,
                    'locality'   => $locality,
                    'region'     => $region,
                    // The club's own country, which is right for all of these: the two German
                    // INDES locations belong to German clubs and the two Austrian ones do not.
                    'country'    => $group->country,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('group_locations')->insert($rows);
    }

    /**
     * The list above matches on the club name, and names carry the en dashes and narrow spaces
     * App\Traits\NameNormalization writes. A typo would therefore not fail, it would silently
     * leave a club without a place - so the names are checked before anything is written.
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
                "Diese Vereinsnamen aus der Standortliste treffen nicht genau einen Verein. Bitte "
                . "den Namen in der Migration berichtigen, dann erneut migrieren:\n  - "
                . implode("\n  - ", $missing)
            );
        }
    }
};
