<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives a federation a country and a name in English.
 *
 * The country is the same char(2) the clubs carry, and for the same reason: a federation is not
 * its country. One country can hold several of them - Poland has two on record here, and a split
 * by weapon is the case that prompted this - so the country belongs on the federation rather than
 * being inferred from it.
 *
 * The English name is optional and only earns its keep where the proper name cannot be read by
 * everyone: ΕΟΙΕΠΤ is a federation nobody outside Greece can type, let alone find. Where a
 * federation already calls itself something Latin and English - HEMA Ireland, Swiss Federation for
 * Historical European Martial Arts - the field stays empty rather than repeating the name.
 *
 * It holds what a federation calls itself in English, not a translation made up here. Only the
 * ones that could be sourced are filled in; the rest are listed in the readme.
 */
return new class extends Migration
{
    /** Every federation on record names its country in its own name. */
    private const COUNTRIES = [
        'Deutscher Dachverband für historisches Fechten e. V.'   => 'DE',
        'Fédération Française des AMHE'                          => 'FR',
        'H|E|M|A Bond Nederland'                                 => 'NL',
        'HEMA Ireland'                                           => 'IE',
        'Österreichischer Fachverband für historisches Fechten'  => 'AT',
        'Polska Federacja Dawnych Europejskich Sztuk Walki'      => 'PL',
        'Swiss Federation for Historical European Martial Arts'  => 'CH',
        'Związek Sportowy Szermierki Historycznej'               => 'PL',
        'Ελληνικη Ομοσπονδια Ιστορικων Ευρωπαϊκων Πολεμικων Τεχνων' => 'GR',
    ];

    /** Only where the federation itself uses the name, not where it would have to be translated. */
    private const ENGLISH_NAMES = [
        // polish-hema-federation.pl carries this as its own heading.
        'Związek Sportowy Szermierki Historycznej' => 'Polish HEMA Federation',
        // The name the Greek federation is announced under in English-language HEMA sources.
        'Ελληνικη Ομοσπονδια Ιστορικων Ευρωπαϊκων Πολεμικων Τεχνων' => 'Greek Federation of Historical European Martial Arts',
    ];

    public function up(): void
    {
        Schema::table('federations', function (Blueprint $table) {
            $table->string('english_name', 250)->nullable()->after('name');
            $table->char('country', 2)->nullable()->after('abbreviation');
        });

        // A fresh installation has no federations, so there is nothing to fill in.
        if (DB::table('federations')->count() === 0) {
            return;
        }

        foreach (self::COUNTRIES as $name => $country) {
            DB::table('federations')->where('name', $name)->whereNull('country')->update(['country' => $country]);
        }

        foreach (self::ENGLISH_NAMES as $name => $english) {
            DB::table('federations')->where('name', $name)->whereNull('english_name')->update(['english_name' => $english]);
        }
    }

    public function down(): void
    {
        Schema::table('federations', function (Blueprint $table) {
            $table->dropColumn(['english_name', 'country']);
        });
    }
};
