<?php

namespace App\Services\RpgCombat;

final class CombatTraits
{
    public static function describe(array $profile): array
    {
        $rows = [];
        foreach ([...$profile['advantages'], ...$profile['disadvantages']] as $name) {
            $rows[$name] = match ($name) {
                'Kampfreflexe' => 'Automatisch: +2 auf Ausweichen.',
                'Kaltblütig' => 'Automatisch: +1 auf Verteidigung.',
                'Schnell' => 'Automatisch: +1 Initiative und +2 m Grundbewegung.',
                'Panzerung' => 'Automatisch: Schutz +1 je Instanz, zusätzlich zur Rüstung.',
                'Zäh' => 'Automatisch: Schutz +1.',
                'Scharfschütze' => 'Automatisch: Fernkampf +1; Schaden +1 bis zum ersten Reichweitenintervall.',
                'Taratzenfutter' => 'Automatisch: eingehender Schadenswurf +1.',
                'Natürliche Waffen' => 'Waffenmodus vorhanden; Schadensauslegung SL-07 beim ersten Einsatz.',
                'Regeneration' => 'Heilfrist aus VM und Spielsekunden, geteilt durch 10 je Instanz; SL-17.',
                'Psychische Kraft' => 'Zugeordnete Talente ab FW 1 und WI 0 nutzbar; siehe PEP und Talentliste.',
                'Psychisches Reservoir' => 'Bereits im PEP-Vorrat enthalten; höchster psychischer FW zählt doppelt.',
                'Gesteigertes Attribut' => 'Bereits in den gespeicherten Attributen enthalten; kein zweiter Bonus.',
                'High-Tech-Ausrüstung' => 'Bereits im Inventar enthalten. Mindestbildung und Benutzungsproben gelten weiterhin.',
                'Primitiv' => 'Automatisch: technische Waffen und Rüstung sind nicht nutzbar.',
                'Tiergefährte' => 'Ausgeschlossen: Das vereinbarte Szenario umfasst nur die beiden Charaktere.',
                'Lichtscheu' => 'Kein Abzug bei diffusem Licht ohne intensive Hautbestrahlung in dieser Arena.',
                'Blutdurst' => 'Versorgung vor Kampfbeginn vorausgesetzt. Reales Warten verursacht keinen Blutmangel.',
                'Tödliche Immunschwäche' => 'Oberflächenkontakt wird in Spielstunden gerechnet; die Standardgrenze von 100 Runden umfasst nur 5 Minuten.',
                'Nachtsicht' => 'Keine zusätzliche Zahlenwirkung bei ausreichender Arenabeleuchtung.',
                'Kiemen' => 'Keine zusätzliche Zahlenwirkung in der trockenen Arena.',
                'Verwundbarkeit' => 'Bei jedem Schadensauslöser entscheidet die Leitung, ob die dokumentierte Verwundbarkeit zutrifft (SL-16).',
                'Anfälligkeit gegen Wahnsinn', 'Ehrenkodex', 'Abergläubisch' => 'Erzählerischer Auslöser: über eine kreative Handlung mit Begründung der AG-Leitung vorlegen. Kein pauschaler Zahlenabzug.',
                default => 'Keine automatische Kampfzahlenwirkung. Passende erzählerische Anwendungen über eine kreative Handlung der AG-Leitung vorlegen.',
            };
        }

        return $rows;
    }
}
