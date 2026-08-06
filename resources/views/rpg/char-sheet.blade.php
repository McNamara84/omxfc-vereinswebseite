<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Charakterbogen</title>
    <style>
        @page { size: A4 portrait; margin: 4mm; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; width: 100%; height: 100%; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 7.6pt; line-height: 1.13; color: #111; }
        .sheet { width: 202mm; height: 289mm; overflow: hidden; page-break-after: avoid; page-break-inside: avoid; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        td, th { vertical-align: top; }
        .header { height: 25mm; }
        .logo-cell { width: 54mm; padding: 1.5mm 4mm 1mm 1mm; }
        .logo { width: 48mm; height: 20.4mm; object-fit: contain; }
        .meta-cell { padding: 1mm 0 0 4mm; }
        .meta-table th { width: 29mm; height: 6.2mm; padding: 0 1.5mm 0.7mm 0; vertical-align: bottom; font-size: 9pt; text-align: left; }
        .meta-table td { height: 6.2mm; padding: 0 1.5mm 0.7mm; border-bottom: 0.35mm solid #111; vertical-align: bottom; font-size: 8.4pt; white-space: nowrap; overflow: hidden; }
        .traits { height: 19mm; padding: 0 1mm; }
        .trait-table th { width: 28mm; height: 4.6mm; padding: 0 1mm 0.5mm 0; vertical-align: bottom; font-size: 8.3pt; text-align: left; white-space: nowrap; }
        .trait-table td { height: 4.6mm; padding: 0 1mm 0.5mm; border-bottom: 0.25mm solid #111; vertical-align: bottom; overflow: hidden; white-space: nowrap; }
        .core { height: 92mm; margin-top: 1mm; }
        .values-cell { width: 112mm; padding-right: 4mm; }
        .portrait-cell { width: 90mm; }
        .section-title { margin: 0 0 1mm; font-size: 8.8pt; font-weight: 700; }
        .attributes { height: 28mm; }
        .attribute-column { width: 50%; padding-right: 4mm; }
        .value-list { width: 46mm; table-layout: auto; }
        .value-list td { height: 4mm; vertical-align: middle; font-size: 8.1pt; }
        .value-list .value-name { width: 36mm; padding-right: 1mm; }
        .value-list .value-box { width: 9mm; padding-top: 0.3mm; border: 0.45mm solid #111; text-align: center; font-weight: 700; }
        .skills { margin-top: 2mm; height: 60mm; }
        .skill-column { width: 50%; padding-right: 3mm; }
        .skill-list { width: 46mm; table-layout: auto; }
        .skill-list td { height: 4mm; vertical-align: middle; font-size: 8pt; }
        .skill-list .skill-name { width: 36mm; padding-right: 1mm; }
        .skill-list .skill-value { width: 9mm; padding-top: 0.3mm; border: 0.35mm solid #111; text-align: center; font-weight: 700; }
        .portrait-frame { height: 92mm; border: 0.45mm double #111; padding: 1mm; overflow: hidden; }
        .portrait-image { width: 100%; height: 65mm; object-fit: cover; display: block; }
        .portrait-placeholder { width: 100%; height: 36mm; padding-top: 29mm; text-align: center; color: #666; font-size: 8pt; }
        .description { height: 18mm; margin-top: 1mm; padding: 1mm; border-top: 0.25mm solid #777; overflow: hidden; font-size: 6.7pt; line-height: 1.18; }
        .description strong { font-size: 7pt; }
        .specializations { height: 9mm; margin-top: 1mm; padding: 1mm; border-bottom: 0.35mm solid #111; overflow: hidden; }
        .specializations strong { font-size: 8pt; }
        .combat-summary { height: 17mm; padding-top: 2mm; }
        .combat-summary td { padding-right: 1.4mm; }
        .combat-box { height: 13mm; border: 0.3mm solid #111; padding: 1mm; overflow: hidden; }
        .combat-label { display: block; font-size: 7.3pt; font-weight: 700; }
        .combat-value { display: block; margin-top: 1mm; font-size: 9.4pt; font-weight: 700; }
        .combat-formula { display: block; margin-top: 0.4mm; font-size: 5.4pt; color: #333; white-space: nowrap; }
        .weapon-section { height: 52mm; overflow: hidden; }
        .weapon-table { border: 0.35mm solid #111; }
        .weapon-table th { height: 4.8mm; padding: 0.7mm 0.6mm; border-bottom: 0.25mm solid #111; background: #eee; font-size: 6.5pt; text-align: left; }
        .weapon-table td { height: 4.4mm; padding: 0.35mm 0.6mm; border-bottom: 0.2mm solid #777; font-size: 6.2pt; overflow: hidden; }
        .weapon-name { font-weight: 700; }
        .weapon-alt { display: block; font-size: 5.2pt; font-weight: 400; color: #333; white-space: nowrap; }
        .armor-section { height: 25mm; margin-top: 1mm; overflow: hidden; }
        .armor-table { border: 0.35mm solid #111; }
        .armor-table th { height: 3.6mm; padding: 0.3mm 0.6mm; background: #eee; border-bottom: 0.25mm solid #111; font-size: 5.8pt; text-align: left; }
        .armor-table td { height: 2mm; padding: 0.15mm 0.7mm; border-bottom: 0.18mm solid #777; font-size: 5.4pt; }
        .active { font-weight: 700; }
        .bottom { height: 37mm; margin-top: 1.5mm; }
        .wounds-cell { width: 77mm; padding-right: 3mm; }
        .equipment-cell { width: 125mm; }
        .bottom-box { height: 36mm; border: 0.45mm solid #111; padding: 1.2mm; overflow: hidden; }
        .bottom-title { margin: 0 0 1mm; font-size: 8.5pt; font-weight: 700; }
        .wound-row { height: 7mm; border-bottom: 0.2mm solid #aaa; padding-top: 1mm; }
        .wound-name { display: inline-block; width: 20mm; font-size: 7.6pt; }
        .wound-mod { display: inline-block; width: 47mm; font-size: 6.3pt; text-align: right; }
        .equipment-text { height: 15mm; overflow: hidden; font-size: 6.6pt; line-height: 1.2; }
        .equipment-sub { margin-top: 1mm; font-size: 6pt; line-height: 1.2; }
        .notes { height: 9mm; margin-top: 1mm; padding-top: 0.7mm; border-top: 0.2mm solid #aaa; overflow: hidden; font-size: 5.8pt; }
        .situational { height: 7mm; margin-top: 0.8mm; overflow: hidden; font-size: 5.6pt; font-style: italic; }
        .muted { color: #555; }
    </style>
</head>
<body>
@php
    if (! isset($sheet) || ! is_array($sheet)) {
        $fallbackPayload = [
            'character' => $character ?? [],
            'attributes' => $attributes ?? [],
            'skills' => $skills ?? [],
            'advantages' => $advantages ?? [],
            'disadvantages' => $disadvantages ?? [],
            'advantage_details' => $advantage_details ?? [],
            'disadvantage_details' => $disadvantage_details ?? [],
            'advantage_counts' => $advantage_counts ?? [],
            'trainings' => $trainings ?? [],
            'equipment' => $equipment ?? [],
            'portrait' => $portrait ?? null,
        ];
        $fallbackCombat = (new \App\Services\RpgCharacterCombatCalculator())->calculate($fallbackPayload);
        $sheet = (new \App\Services\RpgCharacterSheetPresenter())->present($fallbackPayload, $fallbackCombat);
    }

    $logoPath = public_path('images/rpg/maddrax-bastei-logo.jpg');
    $logo = is_file($logoPath) ? 'data:image/jpeg;base64,'.base64_encode(file_get_contents($logoPath)) : null;
    $signed = static fn (int $value): string => $value >= 0 ? '+'.$value : (string) $value;
    $diceModifier = static fn (int $value): string => $value > 0 ? '1W6+'.$value : ($value < 0 ? '1W6'.$value : '1W6');
    $combat = $sheet['combat'] ?? [];
    $defense = $combat['defense'] ?? [];
    $initiative = $combat['initiative'] ?? [];
    $movement = $combat['movement'] ?? [];
    $weaponBlankRows = max(0, 7 - max(1, count($sheet['weapons'])));
    $armorBlankRows = max(0, 6 - max(1, count($sheet['armor'])));
@endphp
<div class="sheet">
    <table class="header">
        <tr>
            <td class="logo-cell">
                @if($logo)
                    <img class="logo" src="{{ $logo }}" alt="Maddrax">
                @else
                    <div class="section-title">MADDRAX</div>
                @endif
            </td>
            <td class="meta-cell">
                <table class="meta-table">
                    <tr><th>Name</th><td>{{ $sheet['character_name'] }}</td></tr>
                    <tr><th>Spieler</th><td>{{ $sheet['player_name'] }}</td></tr>
                    <tr><th>Rasse &amp; Kultur</th><td>{{ $sheet['race_culture'] }}@if($sheet['gender']) · {{ $sheet['gender'] }}@endif</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="traits">
        <table class="trait-table">
            <tr><th>Vorteile</th><td>{{ $sheet['advantages'] }}</td></tr>
            <tr><th>Nachteile</th><td>{{ $sheet['disadvantages'] }}</td></tr>
            <tr><th>Ausbildung</th><td>{{ $sheet['trainings'] }}</td></tr>
            <tr><th>Beruf</th><td>{{ $sheet['professions'] }}</td></tr>
        </table>
    </div>

    <table class="core">
        <tr>
            <td class="values-cell">
                <div class="attributes">
                    <div class="section-title">Attribute</div>
                    <table>
                        <tr>
                            @foreach(array_chunk($sheet['attributes'], 4) as $attributeColumn)
                                <td class="attribute-column">
                                    <table class="value-list">
                                        <colgroup><col style="width: 36mm"><col style="width: 9mm"></colgroup>
                                        @foreach($attributeColumn as $attribute)
                                            <tr><td class="value-name">{{ $attribute['label'] }}</td><td class="value-box">{{ $attribute['value'] }}</td></tr>
                                        @endforeach
                                    </table>
                                </td>
                            @endforeach
                        </tr>
                    </table>
                </div>
                <div class="skills">
                    <div class="section-title">Fertigkeiten</div>
                    <table>
                        <tr>
                            @foreach($sheet['skill_columns'] as $skillColumn)
                                <td class="skill-column">
                                    <table class="skill-list">
                                        <colgroup><col style="width: 36mm"><col style="width: 9mm"></colgroup>
                                        @foreach($skillColumn as $skill)
                                            <tr><td class="skill-name">{{ $skill['name'] }}</td><td class="skill-value">{{ $skill['value'] }}</td></tr>
                                        @endforeach
                                    </table>
                                </td>
                            @endforeach
                        </tr>
                    </table>
                </div>
            </td>
            <td class="portrait-cell">
                <div class="portrait-frame">
                    @if($sheet['portrait'])
                        <img class="portrait-image" src="{{ $sheet['portrait'] }}" alt="Porträt">
                    @else
                        <div class="portrait-placeholder">Porträt / Symbol</div>
                    @endif
                    <div class="description"><strong>Kurzbeschreibung:</strong> {{ $sheet['description'] }}</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="specializations"><strong>Spezialisierungen:</strong> {{ $sheet['specializations'] }}</div>

    <table class="combat-summary">
        <tr>
            <td style="width: 14%"><div class="combat-box"><span class="combat-label">Parade</span><span class="combat-value">{{ $signed((int) ($defense['parade'] ?? 0)) }}</span><span class="combat-formula">NK + GE + Boni</span></div></td>
            <td style="width: 14%"><div class="combat-box"><span class="combat-label">Ausweichen</span><span class="combat-value">{{ $signed((int) ($defense['dodge'] ?? 0)) }}</span><span class="combat-formula">Athl. + GE + Boni</span></div></td>
            <td style="width: 16%"><div class="combat-box"><span class="combat-label">Schadensred.</span><span class="combat-value">{{ $signed((int) ($defense['damage_reduction'] ?? 0)) }}</span><span class="combat-formula">RO + SF + Boni</span></div></td>
            <td style="width: 30%"><div class="combat-box"><span class="combat-label">Initiative</span><span class="combat-value" style="font-size: 7.5pt">NK {{ $diceModifier((int) ($initiative['Nahkampf'] ?? 0)) }} · FK {{ $diceModifier((int) ($initiative['Fernkampf'] ?? 0)) }} · FW {{ $diceModifier((int) ($initiative['Feuerwaffen'] ?? 0)) }}</span><span class="combat-formula">1W6 + Kampffertigkeit + WA</span></div></td>
            <td style="width: 13%"><div class="combat-box"><span class="combat-label">Bewegung</span><span class="combat-value">{{ $movement['base'] ?? 4 }} m</span><span class="combat-formula">4 + GE + Schnell</span></div></td>
            <td style="width: 13%; padding-right: 0"><div class="combat-box"><span class="combat-label">Rennen</span><span class="combat-value">{{ $movement['run'] ?? 16 }} m</span><span class="combat-formula">Bewegung × 4</span></div></td>
        </tr>
    </table>

    <div class="weapon-section">
        <div class="section-title">Waffen</div>
        <table class="weapon-table">
            <thead>
                <tr>
                    <th style="width: 27%">Waffe</th>
                    <th style="width: 9%">Angriff</th>
                    <th style="width: 17%">Schaden</th>
                    <th style="width: 6%">P</th>
                    <th style="width: 15%">Typ</th>
                    <th style="width: 6%">FR</th>
                    <th style="width: 9%">RI</th>
                    <th style="width: 11%">Max.</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sheet['weapons'] as $weapon)
                    <tr>
                        <td><span class="weapon-name">@if(($weapon['quantity'] ?? 1) > 1){{ $weapon['quantity'] }}× @endif{{ $weapon['name'] }}</span>@if($weapon['alternate_text'])<span class="weapon-alt">{{ $weapon['alternate_text'] }}</span>@endif</td>
                        <td>{{ $weapon['attack_text'] }} <span class="muted">{{ $weapon['attack_attribute'] }}</span></td>
                        <td>{{ $weapon['damage_text'] }}@if(($weapon['core_range_damage_bonus'] ?? 0) > 0)<span class="weapon-alt">+1 Kern</span>@endif</td>
                        <td>{{ $signed((int) ($weapon['precision'] ?? 0)) }}</td>
                        <td>{{ $weapon['type'] }}</td>
                        <td>{{ $weapon['fire_rate'] }}</td>
                        <td>{{ $weapon['range_increment'] }}</td>
                        <td>{{ $weapon['max_range'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">Keine Waffe gewählt</td></tr>
                @endforelse
                @for($row = 0; $row < $weaponBlankRows; $row++)
                    <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
                @endfor
            </tbody>
        </table>
    </div>

    <div class="armor-section">
        <div class="section-title">Rüstung / Schild</div>
        <table class="armor-table">
            <thead><tr><th style="width: 48%">Gegenstand</th><th style="width: 13%">SF</th><th style="width: 18%">BM</th><th style="width: 21%">Status</th></tr></thead>
            <tbody>
                @forelse($sheet['armor'] as $armor)
                    <tr class="{{ $armor['active'] ? 'active' : '' }}">
                        <td>@if(($armor['quantity'] ?? 1) > 1){{ $armor['quantity'] }}× @endif{{ $armor['name'] }}</td>
                        <td>{{ $armor['kind'] === 'armor' ? $signed((int) $armor['protection']) : '—' }}</td>
                        <td>{{ $armor['kind'] === 'armor' ? $signed((int) $armor['movement_modifier']) : '—' }}</td>
                        <td>{{ $armor['active'] ? ($armor['kind'] === 'armor' ? 'getragen' : 'verwendet (+1)') : '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">Keine Rüstung und kein Schild gewählt</td></tr>
                @endforelse
                @for($row = 0; $row < $armorBlankRows; $row++)
                    <tr><td>&nbsp;</td><td></td><td></td><td></td></tr>
                @endfor
            </tbody>
        </table>
    </div>

    <table class="bottom">
        <tr>
            <td class="wounds-cell">
                <div class="bottom-box">
                    <div class="bottom-title">Aktuelle Wunden</div>
                    <div class="wound-row"><span class="wound-name">leicht</span><span class="wound-mod">Verletzungsmodifikator 0</span></div>
                    <div class="wound-row"><span class="wound-name">mittel</span><span class="wound-mod">Verletzungsmodifikator 1</span></div>
                    <div class="wound-row"><span class="wound-name">schwer</span><span class="wound-mod">Verletzungsmodifikator 2</span></div>
                </div>
            </td>
            <td class="equipment-cell">
                <div class="bottom-box">
                    <div class="bottom-title">Wichtige Ausrüstung</div>
                    <div class="equipment-text">{{ $sheet['equipment'] }}</div>
                    @if($sheet['ammunition'])<div class="equipment-sub"><strong>Munition:</strong> {{ $sheet['ammunition'] }}</div>@endif
                    @if($sheet['notes'])<div class="notes"><strong>Notizen:</strong> {{ $sheet['notes'] }}</div>@endif
                    @if(! empty($combat['situational_notes']))<div class="situational">{{ implode(' · ', $combat['situational_notes']) }}</div>@endif
                </div>
            </td>
        </tr>
    </table>
</div>
</body>
</html>
