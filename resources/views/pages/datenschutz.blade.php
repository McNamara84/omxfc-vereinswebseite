<x-app-layout title="Datenschutzerklärung – Offizieller MADDRAX Fanclub e. V." description="Wie der Verein personenbezogene Daten von Mitgliedsanträgen verarbeitet und schützt.">
    <x-public-page class="space-y-8">
        <x-ui.page-header
            eyebrow="Personenbezogene Daten im Verein"
            title="Datenschutz"
            description="Diese Datenschutzerklärung erläutert, wie der OMXFC personenbezogene Daten aus Mitgliedsanträgen verarbeitet, speichert und schützt."
        >
            <x-slot:actions>
                <div class="flex flex-wrap gap-2">
                    <span class="badge badge-primary badge-outline rounded-full px-3 py-3">DSGVO-konformer Überblick</span>
                    <span class="badge badge-outline rounded-full px-3 py-3">Mitgliedsanträge & Verwaltung</span>
                    <a href="{{ route('impressum') }}" wire:navigate class="btn btn-ghost btn-sm rounded-full bg-base-100/75">Zum Impressum</a>
                </div>
            </x-slot:actions>
        </x-ui.page-header>

        <section class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_minmax(19rem,0.8fr)] xl:items-start">
            <x-ui.panel title="Datenschutzerklärung für Mitgliedsanträge" description="Die folgenden Abschnitte beschreiben Verantwortlichkeit, Verarbeitungszwecke, Rechtsgrundlagen und Betroffenenrechte.">
                <div class="space-y-4">
                    <section class="rounded-[1.5rem] border border-base-content/10 bg-base-100/72 p-5 sm:p-6">
                        <h2 class="text-xl font-semibold mb-2">Verantwortlicher</h2>
                        <p>Offizieller MADDRAX Fanclub e. V.<br>
                            Guido-Seeber-Weg 12<br>
                            14480 Potsdam<br>
                            <a href="mailto:omxfc.vorstand@gmail.com" class="link link-primary">omxfc.vorstand@gmail.com</a>
                        </p>
                    </section>

                    <section class="rounded-[1.5rem] border border-base-content/10 bg-base-100/72 p-5 sm:p-6">
                        <h2 class="text-xl font-semibold mb-2">Zweck der Verarbeitung</h2>
                        <p>Wir erheben, verarbeiten und speichern personenbezogene Daten, die du uns im Rahmen des Mitgliedschaftsantrags zur Verfügung stellst. Diese Daten verwenden wir ausschließlich zur Bearbeitung deines Antrags, zur Mitgliederverwaltung sowie zur Kommunikation mit dir im Rahmen der Mitgliedschaft.</p>
                        <p class="mt-2">Die erhobenen Daten sind:</p>
                        <ul class="list-disc ml-6 mt-2 space-y-1">
                            <li>Vorname</li>
                            <li>Nachname</li>
                            <li>Straße</li>
                            <li>Hausnummer</li>
                            <li>Postleitzahl</li>
                            <li>Stadt</li>
                            <li>Land</li>
                            <li>Mailadresse</li>
                        </ul>
                    </section>

                    <section class="rounded-[1.5rem] border border-base-content/10 bg-base-100/72 p-5 sm:p-6">
                        <h2 class="text-xl font-semibold mb-2">Rechtsgrundlage der Verarbeitung</h2>
                        <p>Die Rechtsgrundlage für die Verarbeitung der Daten ergibt sich aus Art. 6 Abs. 1 lit. b DSGVO (Verarbeitung zur Erfüllung eines Vertrags oder vorvertraglicher Maßnahmen auf Anfrage der betroffenen Person). Die Mitgliedschaft im Verein gilt dabei als vertragliches Verhältnis.</p>
                    </section>

                    <section class="rounded-[1.5rem] border border-base-content/10 bg-base-100/72 p-5 sm:p-6">
                        <h2 class="text-xl font-semibold mb-2">Empfänger der personenbezogenen Daten</h2>
                        <p>Die Daten werden in unserer internen Datenbank gespeichert. Zusätzlich erfolgt die Übermittlung der erhobenen Daten per E-Mail ausschließlich an die Mitglieder des Vorstands des Vereins zur Bearbeitung des Antrags.</p>
                        <p class="mt-2">Eine Weitergabe an Dritte erfolgt nicht, außer es besteht eine gesetzliche Verpflichtung oder die betroffene Person hat explizit eingewilligt.</p>
                    </section>

                    <section class="rounded-[1.5rem] border border-base-content/10 bg-base-100/72 p-5 sm:p-6">
                        <h2 class="text-xl font-semibold mb-2">Speicherdauer der personenbezogenen Daten</h2>
                        <p>Wir speichern deine Daten nur so lange, wie es für die oben genannten Zwecke notwendig ist. Im Falle einer Ablehnung des Mitgliedschaftsantrags werden die Daten spätestens nach 2 Monaten gelöscht. Im Falle einer erfolgreichen Mitgliedschaft speichern wir deine Daten bis zur Beendigung deiner Mitgliedschaft und im Anschluss solange, wie gesetzliche Aufbewahrungspflichten bestehen (z.B. steuerrechtliche Vorgaben, in der Regel 10 Jahre).</p>
                    </section>

                    <section class="rounded-[1.5rem] border border-base-content/10 bg-base-100/72 p-5 sm:p-6">
                        <h2 class="text-xl font-semibold mb-2">Deine Rechte gemäß DSGVO</h2>
                        <p>Du hast das Recht:</p>
                        <ul class="list-disc ml-6 mt-2 space-y-1">
                            <li>Auskunft über deine gespeicherten Daten zu verlangen (Art. 15 DSGVO).</li>
                            <li>die Berichtigung fehlerhafter Daten zu verlangen (Art. 16 DSGVO).</li>
                            <li>die Löschung deiner Daten zu verlangen, sofern keine gesetzlichen oder vertraglichen Verpflichtungen dagegen sprechen (Art. 17 DSGVO).</li>
                            <li>die Einschränkung der Verarbeitung zu verlangen (Art. 18 DSGVO).</li>
                            <li>auf Datenübertragbarkeit, sofern du uns deine Daten bereitgestellt hast (Art. 20 DSGVO).</li>
                            <li>der Verarbeitung deiner Daten zu widersprechen, sofern diese auf Grundlage eines berechtigten Interesses erfolgt (Art. 21 DSGVO).</li>
                        </ul>
                        <p class="mt-2">Zur Wahrnehmung deiner Rechte kannst du dich jederzeit an den oben angegebenen Verantwortlichen wenden.</p>
                    </section>

                    <section class="rounded-[1.5rem] border border-base-content/10 bg-base-100/72 p-5 sm:p-6">
                        <h2 class="text-xl font-semibold mb-2">Beschwerderecht bei einer Aufsichtsbehörde</h2>
                        <p>Du hast gemäß Art. 77 DSGVO das Recht, dich bei einer Datenschutz-Aufsichtsbehörde über die Verarbeitung deiner personenbezogenen Daten durch uns zu beschweren. In der Regel kannst du dich hierfür an die Aufsichtsbehörde deines üblichen Aufenthaltsortes oder unseres Vereinssitzes wenden.</p>
                    </section>

                    <section class="rounded-[1.5rem] border border-base-content/10 bg-base-100/72 p-5 sm:p-6">
                        <h2 class="text-xl font-semibold mb-2">Datensicherheit</h2>
                        <p>Zum Schutz deiner personenbezogenen Daten setzen wir technische und organisatorische Maßnahmen ein, die dem aktuellen Stand der Technik entsprechen. Dazu gehört beispielsweise die Verwendung von Verschlüsselungstechniken (z. B. SSL-Verschlüsselung bei der Übertragung der Daten über die Webseite), regelmäßige Sicherheitsupdates sowie Zugriffskontrollen und Zugriffsberechtigungen.</p>
                    </section>
                </div>
            </x-ui.panel>

            <div class="space-y-6 xl:sticky xl:top-6">
                <x-ui.panel title="Kontakt zum Verantwortlichen" description="Für Datenschutzanfragen, Auskunftsersuchen oder Berichtigungen ist der Vorstand die erste Anlaufstelle.">
                    <div class="space-y-4 text-sm leading-relaxed sm:text-base">
                        <div class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">
                            <p class="font-semibold">Offizieller MADDRAX Fanclub e. V.</p>
                            <p class="mt-2">Guido-Seeber-Weg 12<br>14480 Potsdam</p>
                        </div>

                        <div class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">
                            <p><a href="mailto:omxfc.vorstand@gmail.com" class="link link-primary">E-Mail schreiben</a></p>
                        </div>
                    </div>
                </x-ui.panel>

                <x-ui.panel title="Kurzüberblick" description="Die Verarbeitung ist auf Mitgliedsanträge und Vereinsverwaltung begrenzt und basiert auf einem klar benannten Rechtsgrund.">
                    <ul class="grid gap-3 text-sm leading-relaxed text-base-content/76 sm:text-base">
                        <li class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">Rechtsgrundlage: Art. 6 Abs. 1 lit. b DSGVO.</li>
                        <li class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">Weitergabe nur im notwendigen Vereinskontext oder bei gesetzlicher Pflicht.</li>
                        <li class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">Betroffenenrechte und Beschwerdemöglichkeiten sind vollständig benannt.</li>
                    </ul>
                </x-ui.panel>
            </div>
        </section>
    </x-public-page>
</x-app-layout>
