<?php

namespace App\Federations;

/**
 * What sort of body a row in `federations` is.
 *
 * The table holds two things that are not alike. A **Dachverband** is a country's governing body:
 * a club joins one, or none, and joining is what decides whether its fencers are ranked here. A
 * **Verbund** is a school or network that clubs belong to alongside one, and it pays no attention
 * to borders - INDES has members in Germany and in Austria, and being one of them says nothing
 * about who ranks you.
 *
 * Two things follow from telling them apart, and both are the reason the column exists.
 *
 * A result stays unambiguous: `results.federation_id` records a Dachverband, so a club that is in
 * a federation *and* an association still has exactly one answer to "who was this fenced for".
 *
 * And membership stays untransitive. A club states its own memberships, every one of them, and
 * nothing is inherited through an association. INDES Salzburg is in the ÖFHF and in INDES; INDES
 * Kulmbach is in the DDHF and in INDES. Passing membership down through INDES would make Salzburg
 * one of ours, which it is not.
 */
enum FederationKind: string
{
    case National = 'national';

    case Association = 'association';

    public function label(): string
    {
        return match ($this) {
            self::National    => 'Dachverband',
            self::Association => 'Verbund',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::National    => 'Der Verband eines Landes. Mitgliedschaft im DDHF entscheidet, '
                . 'wer in den Ranglisten steht.',
            self::Association => 'Ein Schulverbund oder Netzwerk, dem Vereine zusätzlich angehören. '
                . 'Zählt für keine Rangliste und kennt keine Landesgrenzen.',
        };
    }

    /** @return array<string, string> Keyed by value, for Filament select fields. */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
