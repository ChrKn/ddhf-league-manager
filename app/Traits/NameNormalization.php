<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Tidies the spelling of a club or federation name on the way into the database.
 *
 * Two rules, and both are deliberately narrow. What is written here is what every page, export
 * and report will show for years, so the mutator may correct typography and must not change what
 * the name says.
 */
trait NameNormalization
{
    protected function name(): Attribute
    {
        return Attribute::make(
            set: function (?string $value): ?string {
                if ($value === null) {
                    return null;
                }

                // "e.V." and "e. V." are the same abbreviation written two ways, and the spaced
                // form is the correct one. This never changes which characters are letters.
                $value = str_replace(['e.V.', 'e. V.'], 'e. V.', $value);

                // A dash standing alone between spaces separates parts of a name and is
                // typographically an en dash: "Hammaborg – Historischer Schwertkampf".
                //
                // One glued between characters joins words and is a hyphen doing a different
                // job: "Neu-Ulm", "Grün-Weiß", "ARMA-PL". The rule used to convert those as
                // well, which mangled ten club names - and because the mutator runs on every
                // write, correcting one by hand turned it straight back.
                //
                // The space is the whole distinction, and it is the same signal a typesetter
                // reads. Nothing here has to know what the words mean.
                return preg_replace('/(?<= )-(?= )/u', '–', $value);
            },
        );
    }
}
