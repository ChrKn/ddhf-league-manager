<?php

/**
 * Rebuilds the xlsx fixtures next to this file. Everything in them is invented; the point is the
 * shape of the file, not the people in it - a delivered result sheet carries names, clubs and
 * placements and has no business in the repository.
 *
 *     php tests/fixtures/import/make-fixtures.php
 */

require __DIR__ . '/../../../vendor/autoload.php';

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

$target = __DIR__;

function write(string $path, array $sheets): void
{
    $writer = new Writer();
    $writer->openToFile($path);

    $first = true;

    foreach ($sheets as $name => $rows) {
        if ($first) {
            $writer->getCurrentSheet()->setName($name);
            $first = false;
        } else {
            $writer->addNewSheetAndMakeItCurrent()->setName($name);
        }

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
    }

    $writer->close();
    echo "geschrieben: {$path}\n";
}

$header = ['Platzierung', 'Name', 'Gruppe', 'Verband', 'RL-Gender', 'Turnierzone', 'Turniergröße', 'Turniersystem'];

// The template as the organisers hand it in: heading, title line, cut, an example block with a
// second heading, then the results.
write("{$target}/vorlage.xlsx", ['Tabelle1' => [
    $header,
    ['"Beispielturnier Rapier" "Offen" "2026" "Turniersystem"', '', '', '', '', '', '', 'Round Robin mit K.O. Phase'],
    ['', '', '', '', '', '', '', 'Schnitt: 4'],
    ['Beispiel'],
    array_slice($header, 0, 7),
    ['', '', '', '', '', '', '5'],
    ['1', 'Anna Beispiel (7)', 'Musterfechter', 'DDHF', 'Damen+', 'Nord', '5'],
    ['2', 'Berta Muster (12)', '', '', 'Damen+', 'Nord', '5'],
    ['2', 'Carl Testmann (3)', 'Probeklingen', 'DDHF', 'Offen', 'Nord', '5'],
    ['4', 'Dora Übung (44)', 'Musterfechter', 'DDHF', 'Offen', 'Nord', '5'],
    ['Verletzung/Aufgabe', 'Emil Ausfall (9)', '', '', 'Offen', 'Nord', '5'],
]]);

// The same five results without the example block, to prove the data is found below a single
// heading as well. Its last column is the "Turniersystem" this import once asked for; it is not read.
write("{$target}/vorlage-ohne-beispiel.xlsx", ['Tabelle1' => [
    $header,
    ['1', 'Anna Beispiel (7)', 'Musterfechter', 'DDHF', 'Damen+', 'Nord', '2', 'Turnierbaum'],
    ['2', 'Berta Muster (12)', '', '', 'Damen+', 'Nord', '2', 'Turnierbaum'],
]]);

// The field size the file states does not match the results it lists.
write("{$target}/teilnehmerzahl-weicht-ab.xlsx", ['Tabelle1' => [
    $header,
    ['1', 'Anna Beispiel (7)', 'Musterfechter', 'DDHF', 'Offen', 'Nord', '9'],
    ['2', 'Berta Muster (12)', '', '', 'Offen', 'Nord', '9'],
]]);

// What a hand built sheet used to look like. It has to be rejected.
write("{$target}/falsche-kopfzeile.xlsx", ['Tabelle1' => [
    ['Platz', 'Name', 'Verein', 'Verband'],
    ['1', 'Anna Beispiel', 'Musterfechter', 'DDHF'],
]]);

// One workbook, one sheet per tournament - how several organisers deliver.
write("{$target}/zwei-blaetter.xlsx", [
    'Rapier Offen'  => [$header, ['1', 'Anna Beispiel (7)', 'Musterfechter', 'DDHF', 'Offen', 'Nord', '1']],
    'Rapier Damen+' => [$header, ['1', 'Berta Muster (12)', 'Probeklingen', 'DDHF', 'Damen+', 'Nord', '1']],
]);

$template = array_slice($header, 0, 7);

// The template as the federation hands it out: seven columns, the system named in prose on the
// title line, and the margin to the right used for a list of systems to choose from.
write("{$target}/vorlage-ddhf.xlsx", ['Ergebnisse hier eintragen' => [
    [...$template, '', 'Turniersysteme zu Auswahl', 'Verband', 'RL-Gender'],
    ['"Beispielturnier Rapier" "Offen" "2026" "Vorgepooltes K.-o." "Schnitt: 4"', '', '', '', '', '', '', '', 'vorgepooltes K.-o.-System', 'DDHF', 'Offen'],
    ['1', 'Anna Beispiel (7)', 'Musterfechter', 'DDHF', 'Damen+', 'Nord', '2', '', 'Round Robin'],
    ['2', 'Berta Muster (12)', '', '', 'Damen+', 'Nord', '2'],
]]);

// The template's own example sheet next to the results. It is filled, and it is not results.
write("{$target}/vorlage-mit-beispielblatt.xlsx", [
    'Beispiel' => [
        $template,
        ['1', 'Xaver Vorbild', 'Saalefechter', 'DDHF', 'Offen', 'Nord', '75'],
    ],
    'Ergebnisse hier eintragen' => [
        $template,
        ['1', 'Anna Beispiel (7)', 'Musterfechter', 'DDHF', 'Damen+', 'Nord', '2'],
        ['2', 'Berta Muster (12)', '', '', 'Damen+', 'Nord', '2'],
    ],
]);

// Several things wrong at once, so that all of them have to be said: a sheet in some other
// shape, and one whose field size column disagrees with itself and holds a word.
write("{$target}/mehrere-fehler.xlsx", [
    'Selbstgebaut' => [
        ['Platz', 'Name', 'Verein'],
        ['1', 'Anna Beispiel', 'Musterfechter'],
    ],
    'Vorlage' => [
        $template,
        ['1', 'Anna Beispiel (7)', 'Musterfechter', 'DDHF', 'Offen', 'Nord', '3'],
        ['2', 'Berta Muster (12)', '', '', 'Offen', 'Nord', '3'],
        ['3', 'Carl Testmann (3)', 'Probeklingen', 'DDHF', 'Offen', 'Nord', '4'],
        ['4', 'Dora Übung (44)', 'Musterfechter', 'DDHF', 'Offen', 'Nord', 'drei'],
    ],
]);

// Nobody filled in the field size. That is asked in the mapping form, not refused.
write("{$target}/ohne-turniergroesse.xlsx", ['Tabelle1' => [
    $template,
    ['1', 'Anna Beispiel (7)', 'Musterfechter', 'DDHF', 'Offen', 'Nord', ''],
    ['2', 'Berta Muster (12)', '', '', 'Offen', 'Nord', ''],
]]);
