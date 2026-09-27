<div class="space-y-4">
    <p x-show="!data.checks.length">Keine Proben in dieser Ansicht.</p>
    <template x-for="check in data.checks" :key="check.id">
        <article class="rounded-xl border border-base-300 p-4 space-y-3" :data-check-id="check.id">
            <div class="flex flex-wrap justify-between gap-2">
                <a class="link font-semibold whitespace-pre-wrap" :href="check.url" x-text="check.description"></a>
                <span class="badge badge-outline" x-text="check.visibility === 'hidden' ? 'Verdeckt' : 'Offen'"></span>
            </div>
            <p class="text-sm"><span x-text="check.created_at"></span> · <span x-text="check.mode === 'opposed' ? 'Widerstandsprobe' : 'Gegen Schwierigkeit'"></span></p>
            <p class="font-medium" x-text="check.status_label"></p>
            <template x-if="check.difficulty !== undefined && check.difficulty !== null"><p>Schwierigkeit: <span x-text="check.difficulty"></span></p></template>
            <template x-for="participant in check.participants" :key="participant.id">
                <section class="rounded-lg bg-base-200 p-3 space-y-2">
                    <h3 class="font-semibold" x-text="participant.character_name"></h3>
                    <template x-if="participant.attribute"><p><span x-text="participant.skill_name ? participant.skill_name + ' + ' : ''"></span><span x-text="participant.attribute"></span></p></template>
                    <template x-if="participant.base_total !== undefined">
                        <div class="text-sm">
                            <p>Stand bei Anforderung: <span x-text="participant.check_type === 'attribute' ? '3 × ' + participant.attribute_value : participant.skill_value + ' + ' + participant.attribute_value"></span> + Modifikatoren <span x-text="participant.modifier_total"></span> = <span x-text="participant.base_total"></span></p>
                            <template x-for="(modifier, index) in participant.modifiers" :key="index"><p><span x-text="modifier.description"></span>: <span x-text="modifier.value"></span></p></template>
                        </div>
                    </template>
                    <template x-if="participant.dice">
                        <div>
                            <p>Würfel: <span x-text="participant.dice.join(' + ')"></span> · Gesamtergebnis: <strong x-text="participant.total"></strong></p>
                            <p x-text="resultLabel(participant.result_kind, check.status)"></p>
                            <template x-if="participant.margin !== undefined && participant.margin !== null"><p x-text="participant.margin >= 0 ? 'Erfolgspunkte: ' + participant.margin : 'Fehlende Punkte: ' + Math.abs(participant.margin)"></p></template>
                        </div>
                    </template>
                    <button x-show="participant.can_roll" type="button" class="btn btn-primary" :disabled="busy" @click="act(participant.roll_url)" x-text="check.visibility === 'hidden' ? 'Verdeckte Probe würfeln' : 'Probe würfeln'"></button>
                    <p x-show="participant.rolled && !participant.dice" class="text-sm">Gewürfelt. Das Ergebnis wurde der Spielleitung übermittelt.</p>
                    <p x-show="participant.rolled === false && !participant.can_roll" class="text-sm">Wurf noch nicht erfolgt.</p>
                </section>
            </template>
            <p x-text="outcome(check)" class="font-semibold"></p>
            <p x-show="check.executable === false && check.can_cancel" class="text-warning">Ein Teilnehmer ist nicht mehr zum Würfeln berechtigt. Bitte die Probe stornieren.</p>
            <p x-show="check.cancellation_reason" class="whitespace-pre-wrap" x-text="check.cancellation_reason"></p>
            <p x-show="check.resolution_reason" class="whitespace-pre-wrap" x-text="check.resolution_reason"></p>
            <div class="flex flex-wrap gap-2">
                <button x-show="check.can_cancel" type="button" class="btn btn-outline btn-sm" @click="actionId = check.id; reason = ''" :disabled="busy">Probe stornieren / entscheiden</button>
            </div>
            <form x-show="actionId === check.id && check.can_cancel" @submit.prevent="act(check.cancel_url, { reason })" class="space-y-3">
                <label class="fieldset">Begründung (bei Stornierung für Spieler sichtbar)<textarea class="textarea w-full" required maxlength="2000" x-model="reason" :disabled="busy"></textarea></label>
                <template x-if="check.can_resolve">
                    <label class="fieldset">Ausgang des Gleichstands<select class="select w-full" x-model="resolution" :disabled="busy"><option value="neutral">Unentschieden / anderer Ausgang</option><option value="side_1">Seite 1 gewinnt</option><option value="side_2">Seite 2 gewinnt</option></select></label>
                </template>
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-outline" :disabled="busy">Stornierung bestätigen</button>
                    <button x-show="check.can_resolve" type="button" class="btn btn-primary" :disabled="busy || !reason.trim()" @click="act(check.resolve_url, { reason, resolution })">Gleichstand entscheiden</button>
                    <button type="button" class="btn btn-ghost" @click="actionId = null" :disabled="busy">Schließen</button>
                </div>
            </form>
        </article>
    </template>
</div>
