const RACE_DESCRIPTIONS = {
    Barbar: 'Im 26. Jahrhundert besteht die Zivilisation zum größten Teil aus Barbaren. Sie leben in unterschiedlichen Kulturen, beispielsweise als Seefahrer (die Disuuslachter), Nomaden (die Wandernden Völker) oder Ruinenbewohner (die Loords von Landán). Die zeichnen sich durch Zähigkeit, Wildheit und Kampflust aus, sind zumeist primitiv und leben in Clans. Ehre und Mut werden hoch geschätzt. Technologisch bewegen sich die meisten Barbaren zwischen der späten Steinzeit und dem frühen Mittelalter.',
    Guul: 'Guule sind bedauernswerte Mutationen des Homo Sapiens. Sie sind dürr, fast zwei Meter groß und völlig unbehaart. Ihre langen knochigen Arme enden in Krallen. Die verhornten Füße laufen an den Fersen in einem fingerdicken Stachel aus. Aus dem Maul tropft weißlicher Schleim, was ihr abstoßendes Äußeres zusätzlich verstärkt. Guule sind meist nur mit einem Lendenschurz bekleidet. Sie ernähren sich von Aas und Gebeinen, die sie u.a. aus Gräbern holen.',
    Hydrit: 'Hydriten, von den Menschen oft Fischmenschen genannt, sind ein friedliebendes und altes Volk. Sie leben in geheimen Unterseestädten, sind amphibisch, kultiviert und verfügen über fortgeschrittene biogenetische Technologien. Der Genuss von Fleisch verwandelt Hydriten in gefährliche Bestien, weshalb sie sich meist vegetarisch ernähren.',
    Nosfera: 'Nosfera sind mumienartige Erscheinungen mit pergamentartiger Haut und einer knochig dürren Gestalt. Durch eine seltene Form der Sichelzellenanämie benötigen sie stetig frisches Blut und werden deshalb von den meisten Völkern gefürchtet und verhasst. Viele Nosfera verfügen über erstaunlich starke psychische Fähigkeiten.',
    Taratze: 'Taratzen sind mutierte, auf über zwei Meter angewachsene Ratten zwischen tierischer und menschlicher Intelligenz. Sie haben ein drahtiges Fell, spitze Ohren, rote Farbschattierungen und gelten durch ihre raubtierhaften Sinne, ihre Robustheit und ihre Tarnfähigkeit als gefürchtete Gegner. Nur wenige haben die friedlichen Kontaktversuche anderer Rassen gesucht.',
    Wulfane: 'Wulfanen sind von langen, überwiegend dunklen Körperhaaren bedeckt und haben wolfsähnliche Züge. Ihr Gesicht wird durch Hasenscharte und spitze, lange Zähne geprägt. Sie sind durchschnittlich intelligent, verfügen über einen ausgeprägten Geruchssinn und leben nach einem strengen Ehrenkodex bevorzugt in alten Ruinenstädten.',
    Techno: 'Technos sind Nachfahren der Menschen, die den Kometeneinschlag in Bunkern überlebt und eine neue Zivilisation aufgebaut haben. Aufgrund ihres technologischen Fortschritts gelten sie vielen Barbaren als Götter oder Dämonen. Sie leiden jedoch an einer tödlichen Immunschwäche und können die Außenwelt nur mit Schutzanzug betreten.',
    Präkristofluu: 'Präkristofluu sind Menschen aus dem 21. Jahrhundert, die durch Gefrierkammern oder mysteriöse Zeitphänomene in das 26. Jahrhundert gelangt sind. Ihr technisches Wissen und ihre Kenntnisse der Vergangenheit gleichen ihre geringe Anpassungsfähigkeit aus.'
};

const CULTURE_DESCRIPTIONS = {
    Landbewohner: 'Landbewohner bewirtschaften den Boden und versuchen als Bauern und Viehzüchter ihren Lebensunterhalt zu verdienen. Die meisten sind einfache Menschen, die Ruhe und Frieden suchen, nicht viel von der Welt wissen und einfache Landgötter anbeten. Aberglauben ist weit verbreitet.',
    Stadtbewohner: 'Stadtbewohner versuchen in der dunklen Zukunft der Erde neues Leben erblühen zu lassen. Dazu haben sie sich in neu erbauten Siedlungen (zuweilen auf Ruinen aus der Zeit vor dem Kometen) angesiedelt und leben als Händler, Handwerker und Bauern. Die Mauern ihrer Siedlungen schützen sie vor den Gefahren der Wildnis. Ihre Siedlungen sind somit Lichter der Hoffnung in der Dunkelheit.',
    Meeresbewohner: 'Meeresbewohner sind Hydriten aus großen, seit langem verborgenen Unterseestädten. Ihre Gesellschaft ist streng hierarchisch organisiert, technisch und biotechnologisch weit fortgeschritten und meidet den Kontakt zu Oberflächenbewohnern.',
    Bunkermensch: 'Bunkermenschen sind Nachfahren jener Menschen, die die Katastrophe in Bunkern überlebten. Sie verfügen über Technik der alten Welt, leiden aber durch Isolation an einer fatalen Immunschwäche und begegnen der Oberfläche meist nur in Schutzanzügen.',
    'Mensch des 21. Jahrhunderts': 'Diese Kultur muss von allen Präkristofluu ausgewählt werden. Menschen des 21. Jahrhunderts sind auf verworrenen Pfaden in das 26. Jahrhundert gelangt; ihre Bandbreite ist nahezu unerschöpflich.',
    Nomade: 'Nomaden folgen den Routen ihrer Nutztiere durch die Jahreszeiten. Sie sind wehrhaft, überleben in unwirtlicher Natur und werden von sesshaften Völkern oft misstrauisch betrachtet.',
    Ruinenbewohner: 'Ruinenbewohner leben in den Resten der Städte aus der Zeit vor Kristoflus als Banditen, Jäger und Sammler. Ihre Gesellschaften sind primitiv und von Brutalität und Not geprägt.',
    Untergrundbewohner: 'Untergrundbewohner leben in Höhlen und alten Stollen, um sich vor den Gefahren der Oberfläche zu schützen. Sie arbeiten überwiegend als Bergleute, Jäger und Sammler. Häufig haben sie seltsame Rituale und clanartige Strukturen von großer Starrheit entwickelt.',
    'Volk der 13 Inseln': 'Das Volk der 13 Inseln lebt in Schweden, Dänemark und Finnland. Es besteht vor allem aus Jägern, Bauern und Fischern. Frauen dieser Kultur besitzen die Gabe des Lauschens.',
    'Disuuslachter (Nordmann)': 'Disuuslachter sind missgestaltete und grausame Nordmänner, die vom Weltrat im Rahmen des Viking-Projekts eingesetzt werden. Sie verfügen über dampfgetriebene Kriegsschiffe und Kanonen, opfern sich furchtlos im Kampf gegen Bunkerzivilisationen und verbergen ihre entstellten Gesichter unter Lederrüstungen.'
};

const CULTURE_NAMES = Object.keys(CULTURE_DESCRIPTIONS);
const shortDescription = (value, maxLength = 180) => {
    const text = String(value || '').replace(/\s+/g, ' ').trim();
    if (!text) return '';

    const firstSentence = text.match(/^.*?[.!?](\s|$)/)?.[0]?.trim();
    const candidate = firstSentence && firstSentence.length <= maxLength ? firstSentence : text;

    return candidate.length <= maxLength
        ? candidate
        : `${candidate.slice(0, maxLength - 3).trim()}...`;
};
const ATTRIBUTE_IDS = ['st', 'ge', 'ro', 'wi', 'wa', 'in', 'au'];
const ATTRIBUTE_RULE_METADATA = {
    st: {
        id: 'st',
        label: 'St\u00e4rke (ST)',
        short: 'ST',
        name: 'St\u00e4rke',
        description: 'St\u00e4rke beschreibt die rohe physische Muskelkraft einer Figur.',
        valueLabels: {
            '-2': 'greisenhaft',
            '-1': 'kindlich',
            0: 'durchschnittlich',
            1: 'm\u00e4chtig',
            2: 'titanisch',
        },
    },
    ge: {
        id: 'ge',
        label: 'Geschicklichkeit (GE)',
        short: 'GE',
        name: 'Geschicklichkeit',
        description: 'Geschicklichkeit beschreibt Schnellkraft, Beweglichkeit und Reflexe eines Charakters.',
        valueLabels: {
            '-2': 'verkr\u00fcppelt',
            '-1': 'tollpatschig',
            0: 'durchschnittlich',
            1: 'katzenhaft',
            2: 'blitzschnell',
        },
    },
    ro: {
        id: 'ro',
        label: 'Robustheit (RO)',
        short: 'RO',
        name: 'Robustheit',
        description: 'Robustheit steht f\u00fcr Gesundheit, Widerstandskraft, Ausdauer und Resistenz gegen Gifte und Krankheiten.',
        valueLabels: {
            '-2': 'desolat',
            '-1': 'krank',
            0: 'gesund',
            1: 'z\u00e4h',
            2: 'stahlhart',
        },
    },
    wi: {
        id: 'wi',
        label: 'Willenskraft (WI)',
        short: 'WI',
        name: 'Willenskraft',
        description: 'Willenskraft steht f\u00fcr mentale St\u00e4rke, geistige Belastbarkeit und Konzentrationsf\u00e4higkeit.',
        valueLabels: {
            '-2': 'gebrochen',
            '-1': 'beeinflussbar',
            0: 'durchschnittlich',
            1: 'stur',
            2: 'unbeugsam',
        },
    },
    wa: {
        id: 'wa',
        label: 'Wahrnehmung (WA)',
        short: 'WA',
        name: 'Wahrnehmung',
        description: 'Wahrnehmung repr\u00e4sentiert die f\u00fcnf Sinne sowie die allgemeine Aufgewecktheit.',
        valueLabels: {
            '-2': 'blind',
            '-1': 'unvorsichtig',
            0: 'durchschnittlich',
            1: 'wachsam',
            2: 'Adlerauge',
        },
    },
    in: {
        id: 'in',
        label: 'Intelligenz (IN)',
        short: 'IN',
        name: 'Intelligenz',
        description: 'Intelligenz umfasst das geistige Potenzial einer Figur, nicht ihre erlernte Bildung.',
        valueLabels: {
            '-2': 'idiotisch',
            '-1': 'dumm',
            0: 'normal',
            1: 'klug',
            2: 'brilliant',
        },
    },
    au: {
        id: 'au',
        label: 'Auftreten (AU)',
        short: 'AU',
        name: 'Auftreten',
        description: 'Auftreten repr\u00e4sentiert Ausstrahlung, Charisma und Aussehen einer Figur.',
        valueLabels: {
            '-2': 'grauenvoll',
            '-1': 'h\u00e4sslich',
            0: 'durchschnittlich',
            1: 'aufregend',
            2: 'atemberaubend',
        },
    },
};
const RACE_RULE_SUMMARIES = {
    Barbar: {
        name: 'Barbar',
        description: RACE_DESCRIPTIONS.Barbar,
        attributes: 'Ein Attribut nach Wahl +1',
        skills: 'Überleben +1, Intuition +1, Nahkampf oder Fernkampf +1',
        advantages: 'Keine rassenbedingten Pflichtvorteile',
        disadvantages: 'Keine rassenbedingten Pflichtnachteile',
        note: 'Der Attributsbonus wird kostenlos vergeben und separat gewählt.',
    },
    Guul: {
        name: 'Guul',
        description: RACE_DESCRIPTIONS.Guul,
        attributes: 'AU -1',
        skills: 'Heimlichkeit +2, Intuition +1, Natürliche Waffen +1',
        advantages: 'Natürliche Waffen',
        disadvantages: 'Primitiv, Gejagt',
        note: 'Natürliche Waffen steht für den Fersendorn.',
    },
    Hydrit: {
        name: 'Hydrit',
        description: RACE_DESCRIPTIONS.Hydrit,
        attributes: 'Keine Attributsmodifikatoren',
        skills: 'Athletik +2, Bildung +1, Natürliche Waffen +1',
        advantages: 'Kiemen, Natürliche Waffen',
        disadvantages: 'Anfälligkeit gegen Wahnsinn',
        note: 'Natürliche Waffen steht für Klauen.',
    },
    Nosfera: {
        name: 'Nosfera',
        description: RACE_DESCRIPTIONS.Nosfera,
        attributes: 'GE +1, AU -1',
        skills: 'Intuition +2, Heimlichkeit +2',
        advantages: 'Nachtsicht',
        disadvantages: 'Blutdurst, Lichtscheu, Gejagt',
    },
    Taratze: {
        name: 'Taratze',
        description: RACE_DESCRIPTIONS.Taratze,
        attributes: 'ST +1, WA +1, IN -1, AU -1',
        skills: 'Intuition +2, Heimlichkeit +1, Überleben +1',
        advantages: 'Keine rassenbedingten Pflichtvorteile',
        disadvantages: 'Auffällig, Primitiv, Gejagt',
    },
    Wulfane: {
        name: 'Wulfane',
        description: RACE_DESCRIPTIONS.Wulfane,
        attributes: 'RO +1, AU -1',
        skills: 'Intuition +1, Nahkampf +1',
        advantages: 'Keine rassenbedingten Pflichtvorteile',
        disadvantages: 'Ehrenkodex',
    },
    Techno: {
        name: 'Techno',
        description: RACE_DESCRIPTIONS.Techno,
        attributes: 'ST -1, RO -1, IN +1',
        skills: 'Bildung +3 sowie 12 Punkte in Fahren, Feuerwaffen, Heiler, Pilot, Techniker, Wissenschaftler',
        advantages: 'High-Tech-Ausrüstung',
        disadvantages: 'Tödliche Immunschwäche',
    },
    Präkristofluu: {
        name: 'Präkristofluu',
        description: RACE_DESCRIPTIONS.Präkristofluu,
        attributes: 'Keine Attributsmodifikatoren',
        skills: 'Beruf +3 sowie 12 Punkte in Bildung, Fahren, Feuerwaffen, Pilot, Techniker, Wissenschaftler',
        advantages: 'High-Tech-Ausrüstung',
        disadvantages: 'Keine rassenbedingten Pflichtnachteile',
    },
};
const specialRuleConfig = () => (typeof window === 'undefined' ? {} : (window.rpgCharEditorRules || {}));
const ADVANTAGE_RULE_METADATA = {
    "Anführer": { w66: "11-12", ranges: [[11, 12]], description: "Natürlicher Anführer; +2 auf Proben, um Leute zu befehligen oder zu überzeugen." },
    "Gestaltwandler": { w66: "13", ranges: [[13, 13]], description: "Kann Gestalt und Stimme verändern; zählt bei der Erschaffung wie drei Vorteile." },
    "Gesteigertes Attribut": { w66: "14-24", ranges: [[14, 24]], detailPlaceholder: "Attribut notieren", description: "+1 auf ein Attribut nach Wahl; ein bereits erhöhtes Attribut darf nur einmal gewählt werden." },
    "Gesteigerter Sinn": { w66: "25-26", ranges: [[25, 26]], detailPlaceholder: "Sinn notieren", description: "+3 auf Wahrnehmungsproben mit einem gewählten Sinn." },
    "High-Tech-Ausrüstung": { w66: "31-32", ranges: [[31, 32]], description: "Besitzt vier High-Tech-Gegenstände; SL-Zustimmung erforderlich." },
    "Kampfreflexe": { w66: "33-34", ranges: [[33, 34]], description: "+2 Bonus auf alle Ausweichen-Proben." },
    "Kaltblütig": { w66: "35-36", ranges: [[35, 36]], description: "+1 Bonus auf alle Verteidigungswürfe." },
    "Kiemen": { w66: "41", ranges: [[41, 41]], description: "Kann beliebig lange unter Wasser atmen." },
    "Kind zweier Welten": { w66: "42", ranges: [[42, 42]], description: "Kann sowohl Bildung als auch Intuition lernen." },
    "Nachtsicht": { w66: "43-44", ranges: [[43, 44]], description: "Kann ohne Abzüge im Dunkeln sehen." },
    "Natürliche Waffen": { w66: "45", ranges: [[45, 45]], description: "+1 auf Nahkampf durch natürliche Waffen." },
    "Panzerung": { w66: "46", ranges: [[46, 46]], description: "Besitzt Schutzfaktor 1; mehrfach wählbar und additiv." },
    "Psychische Kraft": { w66: "51", ranges: [[51, 51]], description: "Erhält eine besondere Kraft." },
    "Psychisches Reservoir": { w66: "52", ranges: [[52, 52]], description: "Höchster psychischer FW zählt bei der PEP-Ermittlung doppelt." },
    "Regeneration": { w66: "53", ranges: [[53, 53]], description: "Heilt mit zehnfacher Geschwindigkeit." },
    "Scharfschütze": { w66: "54", ranges: [[54, 54]], description: "+1 auf Fernkampfangriffe und +1 Schaden in Kernschussreichweite." },
    "Schnell": { w66: "55-56", ranges: [[55, 56]], description: "+2 auf Grundbewegungsweite und +1 auf Initiative." },
    "Sprachbegabt": { w66: "61", ranges: [[61, 61]], description: "Kann Sprachen und Dialekte ohne Hilfe lernen und beherrscht bis zu drei pro Fertigkeitspunkt." },
    "Tiergefährte": { w66: "62-64", ranges: [[62, 64]], detailPlaceholder: "Tier und Besonderheit notieren", description: "Erhält mit SL-Zustimmung ein Tier als dauerhaften Begleiter." },
    "Zäh": { w66: "65-66", ranges: [[65, 66]], description: "Schutzfaktor +1 durch Zähigkeit und Heldentum; im Editor als kostenlose Pflichtregel aktiv." },
};
const DISADVANTAGE_RULE_METADATA = {
    "Abergläubisch": { w66: "11-16", ranges: [[11, 16]], detailPlaceholder: "Mindestens drei Eigenarten notieren", description: "Muss mindestens drei Eigenarten wählen, die das tägliche Handeln beeinflussen." },
    "Abhängige": { w66: "21", ranges: [[21, 21]], detailPlaceholder: "Person oder Familie notieren", description: "Muss ständig Verwandte oder Familie beschützen." },
    "Anfälligkeit gegen Wahnsinn": { w66: "22", ranges: [[22, 22]], description: "Wahnsinn tritt bei bestimmten Bedingungen ein; die Dauer des Anfalls wird vom SL bestimmt." },
    "Auffällig": { w66: "23-24", ranges: [[23, 24]], description: "Ist wegen ungewöhnlichen Aussehens oder Verhaltens leicht zu erkennen." },
    "Blutdurst": { w66: "25", ranges: [[25, 25]], description: "Benötigt alle 24 Stunden frisches Blut oder erleidet kumulative Abzüge." },
    "Ehrenkodex": { w66: "26-36", ranges: [[26, 36]], detailPlaceholder: "Kodex notieren", description: "Folgt einem definierenden Ehrenkodex, der das tägliche Handeln einschränkt." },
    "Feind": { w66: "41-44", ranges: [[41, 44]], detailPlaceholder: "Volk, Gruppe oder Person notieren", description: "Ist mit einem Volk oder einer mächtigen Person verfeindet." },
    "Gejagt": { w66: "45-46", ranges: [[45, 46]], detailPlaceholder: "Verfolger notieren", description: "Wird von fast allen Völkern gehasst und gejagt." },
    "Lichtscheu": { w66: "51", ranges: [[51, 51]], description: "Erleidet bei ungeschützter Haut unter Licht Abzüge auf alle Proben." },
    "Primitiv": { w66: "52-53", ranges: [[52, 53]], description: "Kann niemals Bildung lernen und keine technischen Gerätschaften benutzen." },
    "Taratzenfutter": { w66: "54-63", ranges: [[54, 63]], description: "Alle Schadenswürfe werden um 1 erhöht." },
    "Tödliche Immunschwäche": { w66: "64", ranges: [[64, 64]], description: "Ohne Schutzanzug treten nach Oberflächenkontakt regelmäßig schwere Symptome ein." },
    "Verpflichtung": { w66: "65", ranges: [[65, 65]], detailPlaceholder: "Organisation, Gruppe oder Person notieren", description: "Ist einer Organisation, Gruppe oder Person verpflichtet, die den Charakter beansprucht." },
    "Verwundbarkeit": { w66: "66", ranges: [[66, 66]], detailPlaceholder: "Mittel oder Quelle notieren", description: "Wird durch ein bestimmtes Mittel besonders schwer verwundet; Robustheit zählt nicht gegen Schaden." },
};

const listFromSpecialRuleConfig = (key, fallback = []) => {
    const config = specialRuleConfig();

    return Array.isArray(config[key]) ? config[key] : fallback;
};

const objectFromSpecialRuleConfig = (key) => {
    const value = specialRuleConfig()[key];

    return value && typeof value === 'object' && !Array.isArray(value) ? value : {};
};

const numericRuleCost = (value) => {
    const parsed = Number(value);

    return Number.isFinite(parsed) ? parsed : 1;
};

const defaultAttributeRuleConfig = () => ({
    baseMin: -1,
    baseMax: 1,
    absoluteMin: -2,
    absoluteMax: 2,
    creationPoints: 2,
    rollFormula: '2W6 + Attributswert x 3',
    attributes: ATTRIBUTE_IDS.map(id => ({ ...ATTRIBUTE_RULE_METADATA[id] })),
});

const attributeRuleConfig = () => {
    const config = objectFromSpecialRuleConfig('attributeRules');

    return Array.isArray(config.attributes) ? config : defaultAttributeRuleConfig();
};

const numericAttributeConfig = (key, fallback) => {
    const parsed = Number(attributeRuleConfig()[key]);

    return Number.isFinite(parsed) ? parsed : fallback;
};

const attributeRules = () => attributeRuleConfig().attributes;
const attributeOptions = () => attributeRules().map(rule => ({ id: rule.id, label: rule.label }));
const attributeRulesById = () => Object.fromEntries(attributeRules().map(rule => [rule.id, rule]));
const attributeCreationPoints = () => numericAttributeConfig('creationPoints', 2);
const attributeRollFormula = () => attributeRuleConfig().rollFormula || '2W6 + Attributswert x 3';

const SKILL_RULE_METADATA = {
    Athletik: { attributes: ['ST', 'GE', 'RO'], description: 'Klettern, Schwimmen, Laufen, Fitness und das Vermeiden von Angriffen.' },
    Beruf: { attributes: ['GE', 'IN', 'AU'], description: 'Pro Fertigkeitspunkt beherrscht der Charakter einen Beruf.', specializable: true, specializationLabel: 'Beruf notieren' },
    Bildung: { attributes: ['IN', 'WA'], description: 'Zivilisierte Ausbildung und Umgang mit technischen Gegenständen.', exclusiveWith: 'Intuition' },
    Diebeskunst: { attributes: ['GE', 'WA'], description: 'Taschendiebstahl, Schlösser, Diebesgut einschätzen und ähnliche Talente.' },
    Fahren: { attributes: ['GE', 'WA'], description: 'Tierisch oder technisch betriebene Fahrzeuge, technische Nutzung bildungsabhängig.' },
    Fernkampf: { attributes: ['GE', 'WA'], description: 'Muskelkraftbasierte Fernwaffen wie Speere, Schleudern, Bögen oder Armbrüste.' },
    Feuerwaffen: { attributes: ['GE', 'WA'], description: 'Schießpulverwaffen und Energiewaffen aller Art, abhängig vom Bildungswert.' },
    Handeln: { attributes: ['AU', 'IN'], description: 'Feilschen, Warenwerte, Geldwerte, Handelsrouten und ähnliche Kenntnisse.' },
    Heiler: { attributes: ['IN'], description: 'Verletzungen behandeln und Lebewesen vor dem Tod bewahren.' },
    Heimlichkeit: { attributes: ['GE'], description: 'Schleichen und Sich-Verbergen.' },
    Intuition: { attributes: ['WA'], description: 'Sechster Sinn der Barbaren, um Gefahren, Unheil und Hinterhalte zu erspüren.', exclusiveWith: 'Bildung' },
    Kunde: { attributes: ['IN', 'WA'], description: 'Nichtwissenschaftliche Fachkenntnisse in einem Gebiet.', specializable: true, specializationLabel: 'Gebiet notieren' },
    Nahkampf: { attributes: ['ST', 'GE'], description: 'Unbewaffneter Kampf und Nahkampfwaffen.' },
    Pilot: { attributes: ['GE', 'WA'], description: 'Umgang mit Fluggeräten, je nach Bildung vom Segelflieger bis zum Kampfjet.' },
    Reiten: { attributes: ['GE'], description: 'Gezähmte Reittiere steuern und wilde Reittiere zureiten.' },
    Sprachen: { attributes: ['IN'], description: 'Pro Fertigkeitspunkt eine Sprache oder einen Dialekt.', specializable: true, specializationLabel: 'Sprache oder Dialekt notieren' },
    Techniker: { attributes: ['IN', 'GE'], description: 'Technische Geräte bedienen, warten und reparieren.' },
    Unterhalten: { attributes: ['AU', 'IN', 'GE'], description: 'Erzählen, Tanzen, Singen, Musizieren, Gaukeln und ähnliche Gebiete.', specializable: true, specializationLabel: 'Unterhaltungsgebiet notieren' },
    Überleben: { attributes: ['RO', 'WA'], description: 'Orientierung und Versorgung in der Wildnis.' },
    Wissenschaftler: { attributes: ['IN'], description: 'Pro Fertigkeitspunkt eine Wissenschaft; der FW darf den Bildungswert nicht übersteigen.', specializable: true, specializationLabel: 'Wissenschaft notieren' },
};
const SPECIAL_SKILL_RULE_METADATA = {
    'Natürliche Waffen': { attributes: ['ST', 'GE'], description: 'Rassenbedingte Sonderregel für natürliche Angriffe; nicht frei als normale Fertigkeit wählbar.', restricted: true },
};
const DEFAULT_SKILL_SUGGESTIONS = [
    'Athletik', 'Beruf', 'Beruf: Bauer', 'Beruf: Bergmann', 'Beruf: Farmer', 'Beruf: Fischer', 'Beruf: Künstler', 'Beruf: Landwirt', 'Beruf: Seemann', 'Beruf: Viehzüchter',
    'Bildung', 'Diebeskunst', 'Fahren', 'Fernkampf', 'Feuerwaffen', 'Handeln', 'Heiler', 'Heimlichkeit', 'Intuition', 'Kunde', 'Kunde: Wetter', 'Nahkampf',
    'Pilot', 'Reiten', 'Sprachen', 'Techniker', 'Unterhalten', 'Überleben', 'Wissenschaftler',
];
const defaultSkillRuleConfig = () => ({
    baseMin: 0,
    baseMax: 4,
    creationPoints: 20,
    skills: Object.entries(SKILL_RULE_METADATA).map(([name, rule]) => ({ name, ...rule })),
    suggestions: DEFAULT_SKILL_SUGGESTIONS,
    specialSkills: Object.entries(SPECIAL_SKILL_RULE_METADATA).map(([name, rule]) => ({ name, ...rule })),
});
const skillRuleConfig = () => {
    const config = objectFromSpecialRuleConfig('skillRules');

    return Array.isArray(config.skills) ? config : defaultSkillRuleConfig();
};
const numericSkillConfig = (key, fallback) => {
    const parsed = Number(skillRuleConfig()[key]);

    return Number.isFinite(parsed) ? parsed : fallback;
};
const skillRules = () => skillRuleConfig().skills;
const specialSkillRules = () => Array.isArray(skillRuleConfig().specialSkills) ? skillRuleConfig().specialSkills : [];
const skillRulesByName = () => Object.fromEntries([...skillRules(), ...specialSkillRules()].map(rule => [rule.name, rule]));
const skillCreationPoints = () => numericSkillConfig('creationPoints', 20);
const skillMaxValue = () => numericSkillConfig('baseMax', 4);
const SKILL_NAME_ALIASES = {
    Ueberleben: 'Überleben',
    'Natuerliche Waffen': 'Natürliche Waffen',
    'Beruf: Kuenstler': 'Beruf: Künstler',
    'Beruf: Viehzuechter': 'Beruf: Viehzüchter',
    'Kunde: Kraeuter': 'Kunde: Kräuter',
};
const normalizeSkillName = (value) => {
    const normalized = String(value || '')
        .replace(/_/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .replace(/\s*:\s*/g, ': ');

    return SKILL_NAME_ALIASES[normalized] || normalized;
};
const skillBaseName = (name) => normalizeSkillName(name).split(':')[0].trim();
const skillSuggestions = () => Array.isArray(skillRuleConfig().suggestions) ? skillRuleConfig().suggestions : DEFAULT_SKILL_SUGGESTIONS;

const defaultEquipmentRuleConfig = () => ({
    limits: { items: 6, highTechItems: 4, maxQuantity: 20 },
    categories: {},
    clothing: [],
    items: [],
});
const equipmentRuleConfig = () => {
    const config = objectFromSpecialRuleConfig('equipmentRules');

    return Array.isArray(config.items) ? config : defaultEquipmentRuleConfig();
};
const equipmentItems = () => equipmentRuleConfig().items || [];
const equipmentItemsById = () => Object.fromEntries(equipmentItems().map(item => [item.id, item]));
const equipmentClothingOptions = () => equipmentRuleConfig().clothing || [];
const equipmentClothingById = () => Object.fromEntries(equipmentClothingOptions().map(item => [item.id, item]));
const equipmentCategoryLabels = () => equipmentRuleConfig().categories || {};
const equipmentLimit = () => Number(equipmentRuleConfig().limits?.items) || 6;
const highTechEquipmentLimit = () => Number(equipmentRuleConfig().limits?.highTechItems) || 4;
const equipmentQuantityMax = () => Number(equipmentRuleConfig().limits?.maxQuantity) || 20;
const buildAdvantageRules = () => {
    const costs = objectFromSpecialRuleConfig('advantageCosts');
    const repeatableAdvantages = new Set(listFromSpecialRuleConfig('repeatableAdvantages'));
    const detailRequiredAdvantages = new Set(listFromSpecialRuleConfig('advantageDetailRequired'));

    return listFromSpecialRuleConfig('advantages', Object.keys(ADVANTAGE_RULE_METADATA))
        .map((name) => ({
            name,
            ...(ADVANTAGE_RULE_METADATA[name] || {}),
            cost: numericRuleCost(costs[name]),
            repeatable: repeatableAdvantages.has(name),
            requiresDetail: detailRequiredAdvantages.has(name),
        }))
        .filter((rule) => Array.isArray(rule.ranges));
};

const buildDisadvantageRules = () => {
    const detailRequiredDisadvantages = new Set(listFromSpecialRuleConfig('disadvantageDetailRequired'));

    return listFromSpecialRuleConfig('disadvantages', Object.keys(DISADVANTAGE_RULE_METADATA))
        .map((name) => ({
            name,
            ...(DISADVANTAGE_RULE_METADATA[name] || {}),
            requiresDetail: detailRequiredDisadvantages.has(name),
        }))
        .filter((rule) => Array.isArray(rule.ranges));
};

const advantageRules = () => buildAdvantageRules();
const disadvantageRules = () => buildDisadvantageRules();
const advantageRulesByName = () => Object.fromEntries(advantageRules().map(rule => [rule.name, rule]));
const disadvantageRulesByName = () => Object.fromEntries(disadvantageRules().map(rule => [rule.name, rule]));
const PRAEKRISTOFLUU_RACE = 'Präkristofluu';
const NOSFERA_RACE = 'Nosfera';
const MENSCH_21_CULTURE = 'Mensch des 21. Jahrhunderts';
const TECHNO_SKILLS = ['Fahren', 'Feuerwaffen', 'Heiler', 'Pilot', 'Techniker', 'Wissenschaftler'];
const TECHNO_SKILL_POOL_POINTS = 12;
const BUNKERMENSCH_BONUS_SKILLS = ['Feuerwaffen', 'Pilot', 'Wissenschaftler'];
const PRAEKRISTOFLUU_SKILLS = ['Bildung', 'Fahren', 'Feuerwaffen', 'Pilot', 'Techniker', 'Wissenschaftler'];
const PRAEKRISTOFLUU_SKILL_POOL_POINTS = 12;
const MENSCH_21_BONUS_SKILLS = ['Bildung', 'Pilot', 'Techniker', 'Wissenschaftler'];
const LANDBEWOHNER_PROFESSION_SKILLS = ['Beruf: Viehzüchter', 'Beruf: Landwirt'];
const NOMADE_COMBAT_SKILLS = ['Nahkampf', 'Fernkampf'];
const NOMADE_MOVEMENT_SKILLS = ['Reiten', 'Athletik'];
const RUINENBEWOHNER_BONUS_SKILLS = ['Nahkampf', 'Fernkampf', 'Athletik', 'Kunde'];
const VOLK_DER_13_INSELN_CULTURE = 'Volk der 13 Inseln';
const DISUUSLACHTER_CULTURE = 'Disuuslachter (Nordmann)';
const BARBAR_ONLY_CULTURES = [VOLK_DER_13_INSELN_CULTURE, DISUUSLACHTER_CULTURE];
const VOLK_DER_13_INSELN_PROFESSION_SKILLS = ['Beruf: Bauer', 'Beruf: Fischer'];
const VOLK_DER_13_INSELN_REQUIRED_ADVANTAGE = 'Psychische Kraft';
const FEMALE_GENDER = 'weiblich';

function hydrateExistingCharEditors() {
    if (!window.Alpine
        || typeof window.Alpine.initTree !== 'function'
        || typeof window.Alpine.destroyTree !== 'function'
        || typeof window.Alpine.$data !== 'function') {
        return;
    }

    document.querySelectorAll('[x-data="charEditor"], [x-data^="charEditor("]').forEach((element) => {
        const scope = element._x_dataStack ? window.Alpine.$data(element) : null;
        const hasRegisteredState = scope
            && typeof scope.basicsFilled === 'function'
            && typeof scope.formValid === 'function'
            && Object.hasOwn(scope, 'advancedUnlocked');

        if (hasRegisteredState) {
            return;
        }

        if (element._x_dataStack) {
            window.Alpine.destroyTree(element);
        }

        window.Alpine.initTree(element);
    });
}

function registerCharEditor({ hydrateExisting = false } = {}) {
    if (!window.Alpine || typeof window.Alpine.data !== 'function') {
        return;
    }

    window.Alpine.data('charEditor', () => ({
    // Basic info
    playerName: '',
    characterName: '',
    gender: '',
    race: '',
    culture: '',
    description: '',
    descriptionUserEdited: false,
    portraitPreview: null,
    equipment: '',
    clothing: '',
    selectedEquipment: {},
    equipmentSearch: '',
    equipmentCategoryFilter: 'all',

    // Game constants
    base: { AP: attributeCreationPoints(), FP: skillCreationPoints(), maxFW: skillMaxValue(), freeAdvantages: 2 },

    // Race/culture state
    raceAPBonus: 0,
    raceGrants: {},
    cultureGrants: {},
    attributeOptions: attributeOptions(),
    barbarAttributeBonus: null,
    barbarCombatSkill: null,
    landbewohnerProfessionSkill: null,
    citySkill: null,
    seaProfessionSkill: null,
    seaKnowledgeOrCombatSkill: null,
    bunkermenschBonusSkill: null,
    mensch21FirstBonusSkill: null,
    mensch21SecondBonusSkill: null,
    nomadeCombatSkill: null,
    nomadeMovementSkill: null,
    ruinenbewohnerBonusSkill: null,
    volkDer13InselnProfessionSkill: null,
    technoSkillNames: TECHNO_SKILLS,
    technoSkillPoolPoints: TECHNO_SKILL_POOL_POINTS,
    technoSkillPoints: Object.fromEntries(TECHNO_SKILLS.map(name => [name, 2])),
    praekristofluuSkillNames: PRAEKRISTOFLUU_SKILLS,
    praekristofluuSkillPoolPoints: PRAEKRISTOFLUU_SKILL_POOL_POINTS,
    praekristofluuSkillPoints: Object.fromEntries(PRAEKRISTOFLUU_SKILLS.map(name => [name, 2])),
    raceCache: {},
    raceAttributeModifiers: {},
    raceLockedByBunkermenschCulture: false,
    raceInfoPreview: '',

    // Dynamic data
    attributes: { st: 0, ge: 0, ro: 0, wi: 0, wa: 0, in: 0, au: 0 },
    skills: [],
    skillUid: 0,
    selectedAdvantages: ['Zäh'],
    selectedDisadvantages: [],
    raceLocked: { advantages: [], disadvantages: [] },
    cultureLocked: { advantages: [], disadvantages: [] },
    cultureAutoSelectedAdvantages: [],
    advantageDetails: {},
    disadvantageDetails: {},
    advantageCounts: { Panzerung: 1 },
    lastRoll: null,

    // UI state
    advancedUnlocked: false,
    purchaseSlotIfNeeded: false,
    characterSlotSummary: typeof window === 'undefined' ? null : (window.rpgCharacterSlots || null),
    basicsFilled() {
        return Boolean(this.playerName.trim() && this.characterName.trim() && this.gender && this.race && this.culture);
    },

    attributeBaseMin() {
        return numericAttributeConfig('baseMin', -1);
    },

    attributeBaseMax() {
        return numericAttributeConfig('baseMax', 1);
    },

    attributeAbsoluteMin() {
        return numericAttributeConfig('absoluteMin', -2);
    },

    attributeAbsoluteMax() {
        return numericAttributeConfig('absoluteMax', 2);
    },

    attributeModifier(id) {
        return this.raceAttributeModifiers[id] || 0;
    },

    attributeCost(id, value = this.attributes[id]) {
        return Math.max(value - this.attributeModifier(id), 0);
    },

    getAttributeMin(id) {
        return Math.max(this.attributeAbsoluteMin(), this.attributeBaseMin() + this.attributeModifier(id));
    },

    getAttributeMax(id) {
        return Math.min(
            this.attributeAbsoluteMax(),
            Math.max(this.getAttributeMin(id), this.attributeBaseMax() + this.attributeModifier(id)),
        );
    },

    apUsed() {
        return ATTRIBUTE_IDS.reduce((sum, id) => sum + this.attributeCost(id), 0);
    },

    apRemaining() {
        return this.base.AP + this.raceAPBonus - this.apUsed();
    },

    fpUsed() {
        return this.skills.reduce((sum, skill) => {
            const grant = this.getGrant(skill.name);
            const start = grant ? grant.value : 0;
            if (grant && grant.type === 'exact') return sum;
            const diff = skill.value - start;
            return sum + Math.max(diff, 0);
        }, 0);
    },

    fpRemaining() {
        return this.base.FP - this.fpUsed();
    },

    lockedAdvantages() {
        return [...new Set([...this.raceLocked.advantages, ...this.cultureLocked.advantages])];
    },

    advantageRule(value) {
        return advantageRulesByName()[value] || null;
    },

    disadvantageRule(value) {
        return disadvantageRulesByName()[value] || null;
    },

    advantageRollLabel(value) {
        return this.advantageRule(value)?.w66 || '';
    },

    disadvantageRollLabel(value) {
        return this.disadvantageRule(value)?.w66 || '';
    },

    advantageTooltip(value) {
        const rule = this.advantageRule(value);
        if (!rule) return '';
        const parts = [`W66 ${rule.w66}`, rule.description];
        if (rule.cost > 1) parts.push(`Kosten: ${rule.cost} Vorteile`);
        if (rule.repeatable) parts.push('Mehrfach wählbar.');
        return parts.filter(Boolean).join(' · ');
    },

    disadvantageTooltip(value) {
        const rule = this.disadvantageRule(value);
        if (!rule) return '';
        return [`W66 ${rule.w66}`, rule.description].filter(Boolean).join(' · ');
    },

    skillRule(value) {
        const name = normalizeSkillName(value);
        if (!name) return null;

        const rules = skillRulesByName();

        return rules[name] || rules[skillBaseName(name)] || null;
    },

    skillTooltip(value) {
        const rule = this.skillRule(value);
        if (!rule) return '';

        const parts = [
            Array.isArray(rule.attributes) && rule.attributes.length ? `Attribute: ${rule.attributes.join(', ')}` : '',
            rule.description,
            rule.specializable ? `Spezialisierung: ${rule.specializationLabel || 'Details notieren'}` : '',
            rule.exclusiveWith ? `Nicht gemeinsam mit ${rule.exclusiveWith} über 0, außer mit Kind zweier Welten.` : '',
            rule.restricted ? 'Rassenbedingte Sonderregel; nicht frei wählbar.' : '',
        ];

        return parts.filter(Boolean).join(' · ');
    },

    skillSuggestions() {
        return skillSuggestions();
    },
    equipmentRules() {
        return equipmentRuleConfig();
    },

    equipmentItems() {
        return equipmentItems();
    },

    equipmentCategoryOptions() {
        return Object.entries(equipmentCategoryLabels()).map(([id, label]) => ({ id, label }));
    },

    equipmentCategoryLabel(value) {
        const key = typeof value === 'string' ? value : value?.category;
        return equipmentCategoryLabels()[key] || key || '';
    },

    clothingOptions() {
        return equipmentClothingOptions();
    },

    selectedClothing() {
        return equipmentClothingById()[this.clothing] || null;
    },

    equipmentItem(id) {
        return equipmentItemsById()[id] || null;
    },

    filteredEquipmentItems() {
        const search = String(this.equipmentSearch || '').trim().toLowerCase();

        return this.equipmentItems().filter((item) => {
            const categoryMatches = this.equipmentCategoryFilter === 'all' || item.category === this.equipmentCategoryFilter;
            const haystack = [item.name, item.summary, this.equipmentCategoryLabel(item)]
                .filter(Boolean)
                .join(' ')
                .toLowerCase();

            return categoryMatches && (!search || haystack.includes(search));
        });
    },

    equipmentQuantity(id) {
        const parsed = Number.parseInt(this.selectedEquipment[id], 10);
        return Number.isFinite(parsed) ? Math.max(0, parsed) : 0;
    },

    selectedEquipmentEntries() {
        return Object.entries(this.selectedEquipment)
            .map(([id, quantity]) => ({ id, quantity: Number.parseInt(quantity, 10) || 0, item: this.equipmentItem(id) }))
            .filter(entry => entry.item && entry.quantity > 0);
    },

    equipmentCountsTowardLimit(item) {
        return item?.countsTowardLimit !== false;
    },

    equipmentRequiresHighTechAdvantage(item) {
        return Boolean(item?.requiresHighTechAdvantage || item?.category === 'high_tech');
    },

    hasHighTechAdvantage() {
        return this.selectedAdvantages.includes('High-Tech-Ausrüstung');
    },

    equipmentCount() {
        return this.selectedEquipmentEntries().reduce((sum, entry) => (
            this.equipmentCountsTowardLimit(entry.item) ? sum + entry.quantity : sum
        ), 0);
    },

    equipmentLimit() {
        return equipmentLimit();
    },

    equipmentRemaining() {
        return this.equipmentLimit() - this.equipmentCount();
    },

    highTechEquipmentCount() {
        return this.selectedEquipmentEntries().reduce((sum, entry) => (
            this.equipmentRequiresHighTechAdvantage(entry.item) ? sum + entry.quantity : sum
        ), 0);
    },

    highTechEquipmentLimit() {
        return highTechEquipmentLimit();
    },

    highTechEquipmentRemaining() {
        return this.highTechEquipmentLimit() - this.highTechEquipmentCount();
    },

    maxEquipmentQuantity(item) {
        if (!item) return 0;
        if (this.equipmentRequiresHighTechAdvantage(item) && !this.hasHighTechAdvantage()) return 0;

        const current = this.equipmentQuantity(item.id);
        const countedCurrent = this.equipmentCountsTowardLimit(item) ? current : 0;
        const highTechCurrent = this.equipmentRequiresHighTechAdvantage(item) ? current : 0;
        const totalRoom = this.equipmentCountsTowardLimit(item)
            ? this.equipmentLimit() - (this.equipmentCount() - countedCurrent)
            : equipmentQuantityMax();
        const highTechRoom = this.equipmentRequiresHighTechAdvantage(item)
            ? this.highTechEquipmentLimit() - (this.highTechEquipmentCount() - highTechCurrent)
            : equipmentQuantityMax();

        return Math.max(0, Math.min(equipmentQuantityMax(), totalRoom, highTechRoom));
    },

    canIncrementEquipment(item) {
        return this.equipmentQuantity(item?.id) < this.maxEquipmentQuantity(item);
    },

    equipmentDisabledReason(item) {
        if (!item) return '';
        if (this.equipmentRequiresHighTechAdvantage(item) && !this.hasHighTechAdvantage()) {
            return 'Benötigt High-Tech-Ausrüstung';
        }
        if (!this.canIncrementEquipment(item)) {
            return 'Limit erreicht';
        }
        return '';
    },

    setEquipmentQuantity(id, value) {
        const item = this.equipmentItem(id);
        if (!item) return;

        const parsed = Number.parseInt(value, 10);
        const requested = Number.isFinite(parsed) ? Math.max(0, parsed) : 0;
        const quantity = Math.min(requested, this.maxEquipmentQuantity(item));
        const next = { ...this.selectedEquipment };

        if (quantity > 0) {
            next[id] = quantity;
        } else {
            delete next[id];
        }

        this.selectedEquipment = next;
    },

    incrementEquipment(id) {
        const item = this.equipmentItem(id);
        if (!item || !this.canIncrementEquipment(item)) return;

        this.setEquipmentQuantity(id, this.equipmentQuantity(id) + 1);
    },

    decrementEquipment(id) {
        this.setEquipmentQuantity(id, this.equipmentQuantity(id) - 1);
    },

    enforceEquipmentLimits() {
        this.selectedEquipmentEntries().forEach((entry) => {
            this.setEquipmentQuantity(entry.id, entry.quantity);
        });
    },

    includedAmmunition() {
        return this.selectedEquipmentEntries()
            .filter(entry => entry.item?.ammunition)
            .map((entry) => ({
                source: entry.item.name,
                quantity: entry.quantity * (Number(entry.item.ammunition.amount) || 0),
                unit: entry.item.ammunition.unit || '',
            }))
            .filter(entry => entry.quantity > 0 && entry.unit);
    },

    equipmentRuleLine(item) {
        if (!item) return '';
        const parts = [item.summary || ''];
        if (item.tw) parts.push(`TW ${item.tw}`);
        if (item.bucks) parts.push(`B ${item.bucks}`);
        return parts.filter(Boolean).join(' · ');
    },

    equipmentComplete() {
        return Boolean(this.selectedClothing())
            && this.equipmentCount() === this.equipmentLimit()
            && this.highTechEquipmentCount() <= this.highTechEquipmentLimit()
            && (this.hasHighTechAdvantage() || this.highTechEquipmentCount() === 0);
    },

    formatGrantList(grants) {
        return Object.entries(grants || {})
            .sort(([left], [right]) => left.localeCompare(right, 'de'))
            .map(([name, grant]) => `${name} ${grant.type === 'exact' ? '=' : '+'}${grant.value}`)
            .join(', ');
    },

    raceShortDescription() {
        return shortDescription(this.raceInfo()?.description);
    },

    cultureInfo() {
        if (!this.culture || !CULTURE_DESCRIPTIONS[this.culture]) return null;

        return {
            name: this.culture,
            description: CULTURE_DESCRIPTIONS[this.culture],
        };
    },

    cultureShortDescription() {
        return shortDescription(this.cultureInfo()?.description);
    },

    cultureInfoRows() {
        const info = this.cultureInfo();
        if (!info) return [];

        const grants = this.formatGrantList(this.cultureGrants);
        const advantages = this.cultureLocked.advantages.length
            ? this.cultureLocked.advantages.join(', ')
            : 'Keine kulturbedingten Pflichtvorteile';

        return [
            { label: 'Fertigkeiten', value: grants || 'Keine automatischen Kulturfertigkeiten' },
            { label: 'Vorteile', value: advantages },
        ];
    },

    selectionInfoAvailable() {
        return Boolean(this.raceInfo() || this.cultureInfo());
    },

    completionIssues() {
        const issues = [];

        if (this.apRemaining() !== 0) {
            issues.push(`Attribute: ${Math.abs(this.apRemaining())} Punkt${Math.abs(this.apRemaining()) === 1 ? '' : 'e'} ${this.apRemaining() > 0 ? 'offen' : 'zu viel'}`);
        }

        if (this.fpRemaining() !== 0) {
            issues.push(`Fertigkeiten: ${Math.abs(this.fpRemaining())} Punkt${Math.abs(this.fpRemaining()) === 1 ? '' : 'e'} ${this.fpRemaining() > 0 ? 'offen' : 'zu viel'}`);
        }

        if (!this.technoSkillPoolComplete()) {
            issues.push(`Techno-Rassenpunkte: ${this.technoPoolUsed()} / ${this.technoSkillPoolPoints}`);
        }

        if (!this.praekristofluuSkillPoolComplete()) {
            issues.push(`Präkristofluu-Rassenpunkte: ${this.praekristofluuPoolUsed()} / ${this.praekristofluuSkillPoolPoints}`);
        }

        if (this.selectedDisadvantages.length < this.chosenAdvantagesCount()) {
            issues.push(`Nachteile: ${this.selectedDisadvantages.length} / ${this.chosenAdvantagesCount()} gewählt`);
        }

        if (!this.requiredSpecialDetailsFilled()) {
            issues.push('Details für Besonderheiten fehlen');
        }

        if (!this.equipmentComplete()) {
            issues.push('Startausrüstung unvollständig');
        }

        return issues;
    },
    attributeRule(id) {
        return attributeRulesById()[id] || ATTRIBUTE_RULE_METADATA[id] || null;
    },

    attributeValueLabel(id, value = this.attributes[id]) {
        const labels = this.attributeRule(id)?.valueLabels || {};

        return labels[String(value)] || '';
    },

    attributeRangeLabel(id) {
        return `${this.getAttributeMin(id)} bis ${this.getAttributeMax(id)}`;
    },

    attributeTooltip(id) {
        const rule = this.attributeRule(id);
        if (!rule) return '';

        return [
            rule.description,
            `Probe: ${attributeRollFormula()}`,
            `Regelbereich aktuell: ${this.attributeRangeLabel(id)}`,
            this.attributeValueLabel(id) ? `Wertbedeutung: ${this.attributeValueLabel(id)}` : '',
        ].filter(Boolean).join(' \u00b7 ');
    },

    advantageLockLabel(value) {
        if (value === 'Zäh') return 'Pflicht';
        if (this.raceLocked.advantages.includes(value)) return 'Rasse';
        if (this.cultureLocked.advantages.includes(value)) return 'Kultur';
        return '';
    },

    disadvantageLockLabel(value) {
        return this.raceLocked.disadvantages.includes(value) ? 'Pflicht' : '';
    },

    advantageCount(value) {
        const rule = this.advantageRule(value);
        if (!rule?.repeatable) return 1;

        const parsed = Number.parseInt(this.advantageCounts[value], 10);
        return Number.isFinite(parsed) ? Math.max(1, parsed) : 1;
    },

    advantageIsRepeatable(value) {
        return Boolean(this.advantageRule(value)?.repeatable);
    },

    advantageCost(value) {
        if (!value || value === 'Zäh' || this.lockedAdvantages().includes(value)) return 0;

        const rule = this.advantageRule(value);
        const baseCost = rule?.cost ?? 1;
        return rule?.repeatable ? baseCost * this.advantageCount(value) : baseCost;
    },

    chosenAdvantagesCount() {
        return this.selectedAdvantages.reduce((sum, value) => sum + this.advantageCost(value), 0);
    },

    freeAdvantagePoints() {
        return this.base.freeAdvantages - this.chosenAdvantagesCount();
    },

    hasKindZweierWelten() {
        return this.selectedAdvantages.includes('Kind zweier Welten');
    },

    isAdvantageSelected(value) {
        return this.selectedAdvantages.includes(value);
    },

    isDisadvantageSelected(value) {
        return this.selectedDisadvantages.includes(value);
    },

    advantageRequiresDetail(value) {
        const rule = this.advantageRule(value);
        return this.isAdvantageSelected(value) && Boolean(rule?.requiresDetail) && !this.lockedAdvantages().includes(value);
    },

    disadvantageRequiresDetail(value) {
        const rule = this.disadvantageRule(value);
        return this.isDisadvantageSelected(value) && Boolean(rule?.requiresDetail) && !this.raceLocked.disadvantages.includes(value);
    },

    advantageDetailPlaceholder(value) {
        return this.advantageRule(value)?.detailPlaceholder || 'Details notieren';
    },

    disadvantageDetailPlaceholder(value) {
        return this.disadvantageRule(value)?.detailPlaceholder || 'Details notieren';
    },

    requiredSpecialDetailsFilled() {
        const missingAdvantageDetail = this.selectedAdvantages.some(value => this.advantageRequiresDetail(value)
            && !String(this.advantageDetails[value] || '').trim());
        const missingDisadvantageDetail = this.selectedDisadvantages.some(value => this.disadvantageRequiresDetail(value)
            && !String(this.disadvantageDetails[value] || '').trim());

        return !missingAdvantageDetail && !missingDisadvantageDetail;
    },

    formValid() {
        return this.apRemaining() === 0
            && this.fpRemaining() === 0
            && this.technoSkillPoolComplete()
            && this.praekristofluuSkillPoolComplete()
            && this.selectedDisadvantages.length >= this.chosenAdvantagesCount()
            && this.requiredSpecialDetailsFilled()
            && this.equipmentComplete();
    },

    shouldMirrorBaseFields() {
        return this.advancedUnlocked;
    },

    shouldSubmitPortraitPreview() {
        return this.advancedUnlocked && Boolean(this.portraitPreview);
    },

    hasFreeCharacterSlot() {
        const freeSlots = Number(this.characterSlotSummary?.free_slots);

        return !Number.isFinite(freeSlots) || freeSlots > 0;
    },

    characterSlotPurchaseMessage() {
        const cost = Number(this.characterSlotSummary?.slot_cost_baxx) || 5;

        return `Kein freier Speicher-Slot frei. Fuer ${cost} Baxx einen weiteren Slot kaufen und diesen Charakter speichern?`;
    },

    handleFormSubmit(event) {
        const submitter = event?.submitter || null;

        if (submitter?.id !== 'submit-button') {
            this.purchaseSlotIfNeeded = false;
            return true;
        }

        if (!this.formValid()) {
            event.preventDefault();
            this.purchaseSlotIfNeeded = false;
            return false;
        }

        if (this.hasFreeCharacterSlot()) {
            this.purchaseSlotIfNeeded = false;
            return true;
        }

        if (window.confirm(this.characterSlotPurchaseMessage())) {
            this.purchaseSlotIfNeeded = true;
            return true;
        }

        event.preventDefault();
        this.purchaseSlotIfNeeded = false;
        return false;
    },

    shouldMirrorSkillName(skill) {
        return Boolean(skill?.nameDisabled);
    },

    shouldMirrorSkillValue(skill) {
        if (!skill) return false;
        if (skill.valueDisabled) return true;

        return this.getGrant(skill.name)?.type === 'exact';
    },

    allUsedSkillNames() {
        const used = new Set([
            ...Object.keys(this.raceGrants),
            ...Object.keys(this.cultureGrants),
            ...this.skills.map(s => s.name).filter(Boolean),
        ]);
        if (!this.hasKindZweierWelten()) {
            if (this.raceGrants['Intuition']) used.add('Bildung');
            if (this.raceGrants['Bildung']) used.add('Intuition');
        }
        return used;
    },

    allowedCulturesForRace(race = this.race) {
        if (race === 'Hydrit') return ['Meeresbewohner'];
        if (race === 'Techno') return ['Bunkermensch'];
        if (race === PRAEKRISTOFLUU_RACE) return [MENSCH_21_CULTURE];

        return CULTURE_NAMES.filter(culture => culture !== 'Bunkermensch'
            && culture !== 'Meeresbewohner'
            && culture !== MENSCH_21_CULTURE
            && (race === 'Barbar' || !BARBAR_ONLY_CULTURES.includes(culture)));
    },

    isCultureSelectable(culture) {
        if (culture === 'Bunkermensch') {
            return this.race !== 'Hydrit' && this.race !== PRAEKRISTOFLUU_RACE;
        }

        if (this.race === 'Techno' && this.raceLockedByBunkermenschCulture) {
            return this.allowedCulturesForRace('').includes(culture);
        }

        return this.allowedCulturesForRace().includes(culture);
    },

    isRaceSelectable(race) {
        return this.culture !== 'Bunkermensch' || !this.raceLockedByBunkermenschCulture || race === 'Techno';
    },

    enforceCultureForRace() {
        const allowedCultures = this.allowedCulturesForRace();
        if (allowedCultures.includes(this.culture)) return false;
        if (!this.culture && allowedCultures.length !== 1) return false;

        const [defaultCulture = ''] = allowedCultures.length === 1 ? allowedCultures : [''];
        this.clearCulture();
        this.culture = defaultCulture;

        return true;
    },

    // --- Methods ---
    init() {
        this.$watch('race', () => this.handleRaceChange());
        this.$watch('culture', () => this.handleCultureChange());
        this.$watch('gender', () => this.handleGenderChange());
        this.$watch('selectedAdvantages', () => {
            this.enforceAdvantageLimit();
            this.enforceEquipmentLimits();
        });
    },

    clampAttribute(id) {
        let val = this.attributes[id];
        if (typeof val !== 'number' || isNaN(val)) val = this.attributeModifier(id) || 0;
        val = Math.max(this.getAttributeMin(id), Math.min(val, this.getAttributeMax(id)));

        // Check AP budget against paid values after race modifiers.
        const othersUsed = ATTRIBUTE_IDS.reduce((sum, otherId) => {
            if (otherId === id) return sum;
            return sum + this.attributeCost(otherId);
        }, 0);
        const availableForThis = Math.max(0, this.base.AP + this.raceAPBonus - othersUsed);
        const maxForThis = Math.min(this.getAttributeMax(id), this.attributeModifier(id) + availableForThis);
        val = Math.min(val, Math.max(this.getAttributeMin(id), maxForThis));

        this.attributes[id] = val;
    },

    handlePortraitUpload(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (e) => { this.portraitPreview = e.target.result; };
            reader.readAsDataURL(file);
        } else {
            this.portraitPreview = null;
        }
    },

    unlockAdvanced() {
        this.advancedUnlocked = true;
    },

    handleGenderChange() {
        this.refreshCultureLockedAdvantages();
    },

    updateDescription() {
        if (this.descriptionUserEdited) return;
        let text = RACE_DESCRIPTIONS[this.race] || '';
        if (CULTURE_DESCRIPTIONS[this.culture]) {
            text += (text ? '\n\n' : '') + CULTURE_DESCRIPTIONS[this.culture];
        }
        this.description = text;
    },

    raceInfo() {
        const raceName = this.raceInfoPreview || this.race;

        return RACE_RULE_SUMMARIES[raceName] || null;
    },

    raceInfoRows() {
        const info = this.raceInfo();
        if (!info) return [];

        return [
            { label: 'Attribute', value: info.attributes },
            { label: 'Fertigkeiten', value: info.skills },
            { label: 'Vorteile', value: info.advantages },
            { label: 'Nachteile', value: info.disadvantages },
            { label: 'Hinweis', value: info.note },
        ].filter(row => row.value);
    },

    setRaceInfoPreview(raceName) {
        this.raceInfoPreview = RACE_RULE_SUMMARIES[raceName] ? raceName : '';
    },

    clearRaceInfoPreview() {
        this.raceInfoPreview = '';
    },

    // --- Skill management ---
    createSkill(name = '', value = 0, overrides = {}) {
        this.skillUid += 1;

        return {
            uid: `skill-${this.skillUid}`,
            name,
            value,
            source: null,
            locked: false,
            nameDisabled: false,
            valueDisabled: false,
            badge: null,
            ...overrides,
        };
    },

    addSkill() {
        if (this.fpRemaining() <= 0) return;
        this.skills.push(this.createSkill());
    },

    removeSkill(index) {
        this.skills.splice(index, 1);
    },

    ensureSkill(name) {
        const existing = this.skills.find(s => s.name === name);
        if (existing) return existing;
        const skill = this.createSkill(name);
        this.skills.push(skill);
        return skill;
    },

    setFreeMin(name, value, source) {
        const grants = source === 'Rasse' ? this.raceGrants : this.cultureGrants;
        grants[name] = { type: 'min', value };
        const skill = this.ensureSkill(name);
        this.applyGrantToSkill(skill);
    },

    setFreeExact(name, value, source) {
        const grants = source === 'Rasse' ? this.raceGrants : this.cultureGrants;
        grants[name] = { type: 'exact', value };
        const skill = this.ensureSkill(name);
        this.applyGrantToSkill(skill);
    },

    applyGrantToSkill(skill) {
        const grant = this.getGrant(skill.name);
        if (!grant) return;

        const hasRaceGrant = Boolean(this.raceGrants[skill.name]);
        const hasCultureGrant = Boolean(this.cultureGrants[skill.name]);

        skill.nameDisabled = true;
        skill.locked = true;
        skill.badge = hasRaceGrant && hasCultureGrant ? 'Rasse/Kultur' : (hasRaceGrant ? 'Rasse' : 'Kultur');
        skill.valueDisabled = grant.type === 'exact';

        if (grant.type === 'exact') {
            skill.value = grant.value;
        } else if (skill.value < grant.value) {
            skill.value = grant.value;
        }
    },

    refreshGrantedSkill(name) {
        const skill = this.skills.find(s => s.name === name);
        if (!skill) return;

        const grant = this.getGrant(name);
        if (!grant) {
            this.skills = this.skills.filter(s => s !== skill);
            return;
        }

        this.applyGrantToSkill(skill);
    },

    getGrant(name) {
        const grants = [this.raceGrants[name], this.cultureGrants[name]].filter(Boolean);
        if (!grants.length) return null;

        return {
            type: grants.some(grant => grant.type === 'exact') ? 'exact' : 'min',
            value: Math.max(...grants.map(grant => grant.value)),
        };
    },

    getSkillMin(name) {
        const grant = this.getGrant(name);
        return grant ? grant.value : 0;
    },

    getSkillMax(name) {
        const grant = this.getGrant(name);
        if (grant && grant.type === 'exact') return grant.value;
        return this.base.maxFW;
    },

    isSkillDisabled(skill) {
        if (skill.valueDisabled) return true;
        const grant = this.getGrant(skill.name);
        if (grant && grant.type === 'exact') return true;
        // Education/Intuition exclusivity
        if (!this.hasKindZweierWelten()) {
            const intuitionGrant = !!this.raceGrants['Intuition'];
            const bildungGrant = !!this.raceGrants['Bildung'];
            if (intuitionGrant && skill.name === 'Bildung') return true;
            if (bildungGrant && skill.name === 'Intuition') return true;
            if (skill.name === 'Bildung') {
                const intuitionSkill = this.skills.find(s => s.name === 'Intuition');
                if (intuitionSkill && intuitionSkill.value >= 1) return true;
            }
            if (skill.name === 'Intuition' && !this.raceGrants['Intuition']) {
                const bildungSkill = this.skills.find(s => s.name === 'Bildung');
                if (bildungSkill && bildungSkill.value >= 1) return true;
            }
        }
        return false;
    },

    clampSkillValue(skill) {
        const min = this.getSkillMin(skill.name);
        const max = this.getSkillMax(skill.name);
        if (skill.value < min) skill.value = min;
        if (skill.value > max) skill.value = max;

        // Check FP budget
        const grant = this.getGrant(skill.name);
        if (!grant || grant.type === 'min') {
            const start = grant ? grant.value : 0;
            const diff = skill.value - start;
            const othersUsed = this.skills.reduce((sum, s) => {
                if (s === skill) return sum;
                const g = this.getGrant(s.name);
                if (g && g.type === 'exact') return sum;
                const st = g ? g.value : 0;
                return sum + Math.max(s.value - st, 0);
            }, 0);
            const maxForThis = Math.max(0, this.base.FP - othersUsed);
            if (diff > maxForThis) {
                skill.value = start + maxForThis;
            }
        }
    },

    isSkillNameUsed(name, currentIndex) {
        if (!name) return false;
        return this.allUsedSkillNames().has(name) && !this.skills.some((s, i) => i === currentIndex && s.name === name);
    },

    isSkillOptionDisabled(optionValue) {
        return this.allUsedSkillNames().has(optionValue);
    },

    resetTechnoSkillPoints(defaultValue = 0) {
        this.technoSkillPoints = Object.fromEntries(TECHNO_SKILLS.map(name => [name, defaultValue]));
    },

    technoPoolUsed() {
        return TECHNO_SKILLS.reduce((sum, name) => sum + (Number(this.technoSkillPoints[name]) || 0), 0);
    },

    technoSkillPoolComplete() {
        return this.race !== 'Techno' || this.technoPoolUsed() === this.technoSkillPoolPoints;
    },

    setTechnoSkillPoints(skillName, value) {
        if (!TECHNO_SKILLS.includes(skillName)) return;

        const parsedValue = Number.parseInt(value, 10);
        const normalizedValue = Number.isFinite(parsedValue)
            ? Math.max(0, Math.min(parsedValue, this.base.maxFW))
            : 0;

        this.technoSkillPoints[skillName] = normalizedValue;
        this.applyTechnoSkillGrant(skillName);
        this.refreshBunkermenschBonusGrant();
    },

    applyTechnoSkillGrant(skillName) {
        const value = this.race === 'Techno' ? (Number(this.technoSkillPoints[skillName]) || 0) : 0;

        if (value > 0) {
            this.raceGrants[skillName] = { type: 'min', value };
            const skill = this.ensureSkill(skillName);
            this.applyGrantToSkill(skill);
            return;
        }

        delete this.raceGrants[skillName];
        this.refreshGrantedSkill(skillName);
    },

    refreshAllTechnoSkillGrants() {
        TECHNO_SKILLS.forEach(name => this.applyTechnoSkillGrant(name));
        this.refreshBunkermenschBonusGrant();
    },

    resetPraekristofluuSkillPoints(defaultValue = 0) {
        this.praekristofluuSkillPoints = Object.fromEntries(PRAEKRISTOFLUU_SKILLS.map(name => [name, defaultValue]));
    },

    praekristofluuPoolUsed() {
        return PRAEKRISTOFLUU_SKILLS.reduce((sum, name) => sum + (Number(this.praekristofluuSkillPoints[name]) || 0), 0);
    },

    praekristofluuSkillPoolComplete() {
        return this.race !== PRAEKRISTOFLUU_RACE || this.praekristofluuPoolUsed() === this.praekristofluuSkillPoolPoints;
    },

    setPraekristofluuSkillPoints(skillName, value) {
        if (!PRAEKRISTOFLUU_SKILLS.includes(skillName)) return;

        const parsedValue = Number.parseInt(value, 10);
        const normalizedValue = Number.isFinite(parsedValue)
            ? Math.max(0, Math.min(parsedValue, this.base.maxFW))
            : 0;

        this.praekristofluuSkillPoints[skillName] = normalizedValue;
        this.applyPraekristofluuSkillGrant(skillName);
        this.refreshAllMensch21BonusGrants();
    },

    applyPraekristofluuSkillGrant(skillName) {
        const value = this.race === PRAEKRISTOFLUU_RACE ? (Number(this.praekristofluuSkillPoints[skillName]) || 0) : 0;

        if (value > 0) {
            this.raceGrants[skillName] = { type: 'min', value };
            const skill = this.ensureSkill(skillName);
            this.applyGrantToSkill(skill);
            return;
        }

        delete this.raceGrants[skillName];
        this.refreshGrantedSkill(skillName);
    },

    refreshAllPraekristofluuSkillGrants() {
        PRAEKRISTOFLUU_SKILLS.forEach(name => this.applyPraekristofluuSkillGrant(name));
        this.refreshAllMensch21BonusGrants();
    },

    setRaceAttributeModifiers(modifiers) {
        this.raceAttributeModifiers = { ...modifiers };

        Object.entries(modifiers).forEach(([id, modifier]) => {
            if (!ATTRIBUTE_IDS.includes(id)) return;
            const paidValue = Number.isFinite(Number(this.attributes[id])) ? Number(this.attributes[id]) : 0;
            const modifiedValue = paidValue + modifier;
            this.attributes[id] = Math.max(this.getAttributeMin(id), Math.min(modifiedValue, this.getAttributeMax(id)));
        });
    },

    clearRaceAttributeModifiers() {
        const previousModifiers = { ...this.raceAttributeModifiers };
        this.raceAttributeModifiers = {};

        Object.entries(previousModifiers).forEach(([id, modifier]) => {
            if (!ATTRIBUTE_IDS.includes(id)) return;
            const modifiedValue = Number.isFinite(Number(this.attributes[id])) ? Number(this.attributes[id]) : modifier;
            const paidValue = modifiedValue - modifier;
            this.attributes[id] = Math.max(this.attributeBaseMin(), Math.min(paidValue, this.attributeBaseMax()));
        });
    },

    // --- Race handling ---
    handleRaceChange() {
        const previousRace = this._prevRace || '';
        if (this.race === previousRace) return;

        if (!this.isRaceSelectable(this.race)) {
            this.race = 'Techno';
        } else if (this.culture !== 'Bunkermensch' || this.race !== 'Techno') {
            this.raceLockedByBunkermenschCulture = false;
        }

        if (this.race === previousRace) return;

        if (this.raceCache[this._prevRace]) {
            // Already cached by cacheRaceState below
        } else if (this._prevRace) {
            this.cacheRaceState(this._prevRace);
        }
        this.clearRace();
        if (this.race === 'Barbar') this.applyRaceBarbar();
        if (this.race === 'Guul') this.applyRaceGuul();
        if (this.race === 'Hydrit') this.applyRaceHydrit();
        if (this.race === NOSFERA_RACE) this.applyRaceNosfera();
        if (this.race === 'Taratze') this.applyRaceTaratze();
        if (this.race === 'Wulfane') this.applyRaceWulfane();
        if (this.race === 'Techno') this.applyRaceTechno();
        if (this.race === PRAEKRISTOFLUU_RACE) this.applyRacePraekristofluu();
        this.restoreRaceState(this.race);
        this.enforceCultureForRace();
        this._prevRace = this.race;
        this.updateDescription();
    },

    cacheRaceState(raceName) {
        if (!raceName) return;
        this.raceCache[raceName] = {
            attributes: { ...this.attributes },
            skills: this.skills.filter(s => this.raceGrants[s.name]).map(s => ({ name: s.name, value: s.value })),
            barbarAttributeBonus: this.barbarAttributeBonus,
            barbarCombatSkill: this.barbarCombatSkill,
            technoSkillPoints: { ...this.technoSkillPoints },
            praekristofluuSkillPoints: { ...this.praekristofluuSkillPoints },
        };
    },

    restoreRaceState(raceName) {
        const cache = this.raceCache[raceName];
        if (!cache) return;
        if (raceName === 'Barbar' && cache.barbarAttributeBonus) {
            this.setBarbarAttributeBonus(cache.barbarAttributeBonus);
        }
        ATTRIBUTE_IDS.forEach(id => {
            if (cache.attributes[id] !== undefined) {
                this.attributes[id] = Math.max(this.getAttributeMin(id), Math.min(cache.attributes[id], this.getAttributeMax(id)));
            }
        });
        cache.skills.forEach(cached => {
            const skill = this.skills.find(s => s.name === cached.name);
            if (skill) skill.value = cached.value;
        });
        if (raceName === 'Barbar' && cache.barbarCombatSkill) {
            this.setBarbarCombatSkill(cache.barbarCombatSkill);
        }
        if (raceName === 'Techno' && cache.technoSkillPoints) {
            this.technoSkillPoints = { ...this.technoSkillPoints, ...cache.technoSkillPoints };
            this.refreshAllTechnoSkillGrants();
        }
        if (raceName === PRAEKRISTOFLUU_RACE && cache.praekristofluuSkillPoints) {
            this.praekristofluuSkillPoints = { ...this.praekristofluuSkillPoints, ...cache.praekristofluuSkillPoints };
            this.refreshAllPraekristofluuSkillGrants();
        }
    },

    clearRace() {
        const previousRaceSkills = Object.keys(this.raceGrants);
        const previousLockedAdvantages = [...this.raceLocked.advantages];
        const previousLockedDisadvantages = [...this.raceLocked.disadvantages];

        this.raceAPBonus = 0;
        this.raceGrants = {};
        this.barbarAttributeBonus = null;
        this.barbarCombatSkill = null;
        this.resetTechnoSkillPoints(0);
        this.resetPraekristofluuSkillPoints(0);
        this.clearRaceAttributeModifiers();
        previousRaceSkills.forEach(name => this.refreshGrantedSkill(name));
        this.selectedAdvantages = this.selectedAdvantages.filter(value => value === 'Zäh' || !previousLockedAdvantages.includes(value));
        this.selectedDisadvantages = this.selectedDisadvantages.filter(value => !previousLockedDisadvantages.includes(value));
        this.raceLocked.advantages = [];
        this.raceLocked.disadvantages = [];
    },

    applyRaceBarbar() {
        this.setBarbarAttributeBonus('st');
        this.setFreeMin('Überleben', 1, 'Rasse');
        this.setFreeMin('Intuition', 1, 'Rasse');
        this.setBarbarCombatSkill('Nahkampf');
    },

    applyRaceGuul() {
        this.setRaceAttributeModifiers({ au: -1 });
        this.setFreeMin('Heimlichkeit', 2, 'Rasse');
        this.setFreeMin('Intuition', 1, 'Rasse');
        this.setFreeMin('Natürliche Waffen', 1, 'Rasse');
        this.raceLocked.advantages = ['Natürliche Waffen'];
        this.raceLocked.disadvantages = ['Primitiv', 'Gejagt'];
        this.selectedAdvantages = [...new Set([...this.selectedAdvantages, 'Natürliche Waffen'])];
        this.selectedDisadvantages = [...new Set([...this.selectedDisadvantages, 'Primitiv', 'Gejagt'])];
    },

    applyRaceHydrit() {
        this.setFreeMin('Athletik', 2, 'Rasse');
        this.setFreeMin('Bildung', 1, 'Rasse');
        this.setFreeMin('Natürliche Waffen', 1, 'Rasse');
        this.raceLocked.advantages = ['Kiemen', 'Natürliche Waffen'];
        this.raceLocked.disadvantages = ['Anfälligkeit gegen Wahnsinn'];
        this.selectedAdvantages = [...new Set([...this.selectedAdvantages, 'Kiemen', 'Natürliche Waffen'])];
        this.selectedDisadvantages = [...new Set([...this.selectedDisadvantages, 'Anfälligkeit gegen Wahnsinn'])];
    },

    applyRaceNosfera() {
        this.setRaceAttributeModifiers({ ge: 1, au: -1 });
        this.setFreeMin('Intuition', 2, 'Rasse');
        this.setFreeMin('Heimlichkeit', 2, 'Rasse');
        this.raceLocked.advantages = ['Nachtsicht'];
        this.raceLocked.disadvantages = ['Blutdurst', 'Lichtscheu', 'Gejagt'];
        this.selectedAdvantages = [...new Set([...this.selectedAdvantages, 'Nachtsicht'])];
        this.selectedDisadvantages = [...new Set([...this.selectedDisadvantages, 'Blutdurst', 'Lichtscheu', 'Gejagt'])];
    },

    applyRaceTaratze() {
        this.setRaceAttributeModifiers({ st: 1, wa: 1, in: -1, au: -1 });
        this.setFreeMin('Intuition', 2, 'Rasse');
        this.setFreeMin('Heimlichkeit', 1, 'Rasse');
        this.setFreeMin('Überleben', 1, 'Rasse');
        this.raceLocked.disadvantages = ['Auffällig', 'Primitiv', 'Gejagt'];
        this.selectedDisadvantages = [...new Set([...this.selectedDisadvantages, 'Auffällig', 'Primitiv', 'Gejagt'])];
    },

    applyRaceWulfane() {
        this.setRaceAttributeModifiers({ ro: 1, au: -1 });
        this.setFreeMin('Intuition', 1, 'Rasse');
        this.setFreeMin('Nahkampf', 1, 'Rasse');
        this.raceLocked.disadvantages = ['Ehrenkodex'];
        this.selectedDisadvantages = [...new Set([...this.selectedDisadvantages, 'Ehrenkodex'])];
    },

    applyRaceTechno() {
        this.setRaceAttributeModifiers({ st: -1, ro: -1, in: 1 });
        this.setFreeMin('Bildung', 3, 'Rasse');
        this.resetTechnoSkillPoints(2);
        this.refreshAllTechnoSkillGrants();
        this.raceLocked.advantages = ['High-Tech-Ausrüstung'];
        this.raceLocked.disadvantages = ['Tödliche Immunschwäche'];
        this.selectedAdvantages = [...new Set([...this.selectedAdvantages, 'High-Tech-Ausrüstung'])];
        this.selectedDisadvantages = [...new Set([...this.selectedDisadvantages, 'Tödliche Immunschwäche'])];
    },

    applyRacePraekristofluu() {
        this.setFreeMin('Beruf', 3, 'Rasse');
        this.resetPraekristofluuSkillPoints(2);
        this.refreshAllPraekristofluuSkillGrants();
        this.raceLocked.advantages = ['High-Tech-Ausrüstung'];
        this.selectedAdvantages = [...new Set([...this.selectedAdvantages, 'High-Tech-Ausrüstung'])];
    },

    setBarbarAttributeBonus(attributeId) {
        if (!ATTRIBUTE_IDS.includes(attributeId)) return;

        const currentBonusAttribute = Object.entries(this.raceAttributeModifiers)
            .find(([, modifier]) => modifier === 1)?.[0] || null;

        if (currentBonusAttribute === attributeId) {
            this.barbarAttributeBonus = attributeId;
            return;
        }

        if (currentBonusAttribute) {
            this.clearRaceAttributeModifiers();
        }

        this.barbarAttributeBonus = attributeId;
        this.setRaceAttributeModifiers({ [attributeId]: 1 });
    },

    setBarbarCombatSkill(skillName) {
        ['Nahkampf', 'Fernkampf']
            .filter(name => name !== skillName && this.raceGrants[name])
            .forEach(name => {
                delete this.raceGrants[name];
                this.refreshGrantedSkill(name);
            });

        this.barbarCombatSkill = skillName;
        this.raceGrants[skillName] = { type: 'min', value: 1 };
        const skill = this.ensureSkill(skillName);
        this.applyGrantToSkill(skill);
    },

    // --- Culture handling ---
    handleCultureChange() {
        if (!this.isCultureSelectable(this.culture)) {
            this.enforceCultureForRace();
            return;
        }

        if (this.culture === 'Bunkermensch') {
            this.ensureTechnoRaceForBunkermenschCulture();
        } else {
            this.releaseBunkermenschRaceLock();
        }

        this.clearCulture();
        if (this.culture === 'Landbewohner') this.applyCultureLandbewohner();
        if (this.culture === 'Stadtbewohner') this.applyCultureStadtbewohner();
        if (this.culture === 'Meeresbewohner') this.applyCultureMeeresbewohner();
        if (this.culture === 'Bunkermensch') this.applyCultureBunkermensch();
        if (this.culture === MENSCH_21_CULTURE) this.applyCultureMensch21();
        if (this.culture === 'Nomade') this.applyCultureNomade();
        if (this.culture === 'Ruinenbewohner') this.applyCultureRuinenbewohner();
        if (this.culture === 'Untergrundbewohner') this.applyCultureUntergrundbewohner();
        if (this.culture === VOLK_DER_13_INSELN_CULTURE) this.applyCultureVolkDer13Inseln();
        if (this.culture === DISUUSLACHTER_CULTURE) this.applyCultureDisuuslachter();
        this.updateDescription();
    },

    ensureTechnoRaceForBunkermenschCulture() {
        if (this.race === 'Techno') return;

        this.raceLockedByBunkermenschCulture = true;
        this.race = 'Techno';
        this.handleRaceChange();
    },

    releaseBunkermenschRaceLock() {
        if (!this.raceLockedByBunkermenschCulture) return;

        this.raceLockedByBunkermenschCulture = false;
        if (this.race !== 'Techno') return;

        this.race = '';
        this.handleRaceChange();
    },

    clearCulture() {
        const previousCultureSkills = Object.keys(this.cultureGrants);

        this.cultureGrants = {};
        this.landbewohnerProfessionSkill = null;
        this.citySkill = null;
        this.seaProfessionSkill = null;
        this.seaKnowledgeOrCombatSkill = null;
        this.bunkermenschBonusSkill = null;
        this.mensch21FirstBonusSkill = null;
        this.mensch21SecondBonusSkill = null;
        this.nomadeCombatSkill = null;
        this.nomadeMovementSkill = null;
        this.ruinenbewohnerBonusSkill = null;
        this.volkDer13InselnProfessionSkill = null;
        this.setCultureLockedAdvantages([]);
        previousCultureSkills.forEach(name => this.refreshGrantedSkill(name));
    },

    applyCultureLandbewohner() {
        this.setLandbewohnerProfessionSkill('Beruf: Viehzüchter');
        this.setFreeMin('Kunde: Wetter', 1, 'Kultur');
    },

    applyCultureStadtbewohner() {
        this.setCitySkill('Unterhalten');
        this.setFreeMin('Beruf', 1, 'Kultur');
        this.setFreeMin('Kunde', 1, 'Kultur');
    },

    applyCultureMeeresbewohner() {
        this.setFreeMin('Athletik', 1, 'Kultur');
        this.setSeaProfessionSkill('Beruf: Farmer');
        this.setSeaKnowledgeOrCombatSkill('Wissenschaftler');
    },

    applyCultureBunkermensch() {
        this.setFreeMin('Bildung', 1, 'Kultur');
        this.setFreeMin('Nahkampf', 1, 'Kultur');
        this.setBunkermenschBonusSkill('Feuerwaffen');
    },

    applyCultureMensch21() {
        this.setFreeMin('Beruf', 1, 'Kultur');
        this.setMensch21BonusSkills('Bildung', 'Pilot');
    },

    applyCultureNomade() {
        this.setFreeMin('\u00dcberleben', 1, 'Kultur');
        this.setNomadeCombatSkill('Nahkampf');
        this.setNomadeMovementSkill('Reiten');
    },

    applyCultureRuinenbewohner() {
        this.setFreeMin('Diebeskunst', 1, 'Kultur');
        this.setFreeMin('Heimlichkeit', 1, 'Kultur');
        this.setRuinenbewohnerBonusSkill('Nahkampf');
    },

    applyCultureUntergrundbewohner() {
        this.setFreeMin('Athletik', 1, 'Kultur');
        this.setFreeMin('Beruf: Bergmann', 1, 'Kultur');
        this.setFreeMin('\u00dcberleben', 1, 'Kultur');
    },

    applyCultureVolkDer13Inseln() {
        this.setFreeMin('Athletik', 1, 'Kultur');
        this.setFreeMin('\u00dcberleben', 1, 'Kultur');
        this.setVolkDer13InselnProfessionSkill('Beruf: Bauer');
        this.refreshCultureLockedAdvantages();
    },

    applyCultureDisuuslachter() {
        this.setFreeMin('Nahkampf', 1, 'Kultur');
        this.setFreeMin('Überleben', 1, 'Kultur');
        this.setFreeMin('Beruf: Seemann', 1, 'Kultur');
    },

    setLandbewohnerProfessionSkill(skillName) {
        this.setCultureChoiceSkill(skillName, LANDBEWOHNER_PROFESSION_SKILLS, 'landbewohnerProfessionSkill', 2);
    },

    setCitySkill(skillName) {
        ['Unterhalten', 'Sprachen']
            .filter(name => name !== skillName && this.cultureGrants[name])
            .forEach(name => {
                delete this.cultureGrants[name];
                this.refreshGrantedSkill(name);
            });

        this.citySkill = skillName;
        this.cultureGrants[skillName] = { type: 'min', value: 1 };
        const skill = this.ensureSkill(skillName);
        this.applyGrantToSkill(skill);
    },

    setSeaProfessionSkill(skillName) {
        ['Beruf: Farmer', 'Beruf: Künstler']
            .filter(name => name !== skillName && this.cultureGrants[name])
            .forEach(name => {
                delete this.cultureGrants[name];
                this.refreshGrantedSkill(name);
            });

        this.seaProfessionSkill = skillName;
        this.cultureGrants[skillName] = { type: 'min', value: 1 };
        const skill = this.ensureSkill(skillName);
        this.applyGrantToSkill(skill);
    },

    setSeaKnowledgeOrCombatSkill(skillName) {
        ['Wissenschaftler', 'Techniker', 'Nahkampf']
            .filter(name => name !== skillName && this.cultureGrants[name])
            .forEach(name => {
                delete this.cultureGrants[name];
                this.refreshGrantedSkill(name);
            });

        this.seaKnowledgeOrCombatSkill = skillName;
        this.cultureGrants[skillName] = { type: 'min', value: 1 };
        const skill = this.ensureSkill(skillName);
        this.applyGrantToSkill(skill);
    },

    setCultureChoiceSkill(skillName, optionNames, stateProperty, value = 1) {
        if (!optionNames.includes(skillName)) return;

        optionNames
            .filter(name => name !== skillName && this.cultureGrants[name])
            .forEach(name => {
                delete this.cultureGrants[name];
                this.refreshGrantedSkill(name);
            });

        this[stateProperty] = skillName;
        this.cultureGrants[skillName] = { type: 'min', value };
        const skill = this.ensureSkill(skillName);
        this.applyGrantToSkill(skill);
    },

    setNomadeCombatSkill(skillName) {
        this.setCultureChoiceSkill(skillName, NOMADE_COMBAT_SKILLS, 'nomadeCombatSkill');
    },

    setNomadeMovementSkill(skillName) {
        this.setCultureChoiceSkill(skillName, NOMADE_MOVEMENT_SKILLS, 'nomadeMovementSkill');
    },

    setRuinenbewohnerBonusSkill(skillName) {
        this.setCultureChoiceSkill(skillName, RUINENBEWOHNER_BONUS_SKILLS, 'ruinenbewohnerBonusSkill');
    },

    setVolkDer13InselnProfessionSkill(skillName) {
        this.setCultureChoiceSkill(skillName, VOLK_DER_13_INSELN_PROFESSION_SKILLS, 'volkDer13InselnProfessionSkill');
    },

    setBunkermenschBonusSkill(skillName) {
        if (!BUNKERMENSCH_BONUS_SKILLS.includes(skillName)) return;

        BUNKERMENSCH_BONUS_SKILLS
            .filter(name => name !== skillName && this.cultureGrants[name])
            .forEach(name => {
                delete this.cultureGrants[name];
                const grant = this.getGrant(name);
                const skill = this.skills.find(s => s.name === name);
                if (skill && grant && skill.value > grant.value) {
                    skill.value = grant.value;
                }
                this.refreshGrantedSkill(name);
            });

        this.bunkermenschBonusSkill = skillName;
        this.refreshBunkermenschBonusGrant();
    },

    refreshBunkermenschBonusGrant() {
        if (this.culture !== 'Bunkermensch' || !this.bunkermenschBonusSkill) return;

        const raceValue = Number(this.technoSkillPoints[this.bunkermenschBonusSkill]) || 0;
        const value = Math.min(this.base.maxFW, raceValue + 1);
        this.cultureGrants[this.bunkermenschBonusSkill] = { type: 'min', value };
        const skill = this.ensureSkill(this.bunkermenschBonusSkill);
        this.applyGrantToSkill(skill);
    },

    setMensch21FirstBonusSkill(skillName) {
        this.setMensch21BonusSkills(skillName, this.mensch21SecondBonusSkill);
    },

    setMensch21SecondBonusSkill(skillName) {
        this.setMensch21BonusSkills(this.mensch21FirstBonusSkill, skillName);
    },

    setMensch21BonusSkills(firstSkill, secondSkill) {
        const first = MENSCH_21_BONUS_SKILLS.includes(firstSkill)
            ? firstSkill
            : MENSCH_21_BONUS_SKILLS[0];
        let second = MENSCH_21_BONUS_SKILLS.includes(secondSkill)
            ? secondSkill
            : MENSCH_21_BONUS_SKILLS.find(name => name !== first);

        if (second === first) {
            second = MENSCH_21_BONUS_SKILLS.find(name => name !== first);
        }

        const nextSkills = new Set([first, second].filter(Boolean));
        MENSCH_21_BONUS_SKILLS
            .filter(name => !nextSkills.has(name) && this.cultureGrants[name])
            .forEach(name => {
                delete this.cultureGrants[name];
                const grant = this.getGrant(name);
                const skill = this.skills.find(s => s.name === name);
                if (skill && grant && skill.value > grant.value) {
                    skill.value = grant.value;
                }
                this.refreshGrantedSkill(name);
            });

        this.mensch21FirstBonusSkill = first;
        this.mensch21SecondBonusSkill = second;
        this.refreshAllMensch21BonusGrants();
    },

    refreshAllMensch21BonusGrants() {
        if (this.culture !== MENSCH_21_CULTURE) return;

        [...new Set([this.mensch21FirstBonusSkill, this.mensch21SecondBonusSkill].filter(Boolean))]
            .forEach(name => this.refreshMensch21BonusGrant(name));
    },

    refreshMensch21BonusGrant(skillName) {
        if (this.culture !== MENSCH_21_CULTURE || !MENSCH_21_BONUS_SKILLS.includes(skillName)) return;

        const raceValue = this.race === PRAEKRISTOFLUU_RACE
            ? (Number(this.praekristofluuSkillPoints[skillName]) || 0)
            : 0;
        const value = Math.min(this.base.maxFW, raceValue + 1);
        this.cultureGrants[skillName] = { type: 'min', value };
        const skill = this.ensureSkill(skillName);
        this.applyGrantToSkill(skill);
    },

    setCultureLockedAdvantages(advantages) {
        const nextAdvantages = [...new Set(advantages)];
        const previousAutoSelected = [...this.cultureAutoSelectedAdvantages];
        const autoSelectedToRemove = previousAutoSelected.filter(value => !nextAdvantages.includes(value));
        const lockedChanged = nextAdvantages.length !== this.cultureLocked.advantages.length
            || nextAdvantages.some((value, index) => value !== this.cultureLocked.advantages[index]);
        const missingAutoSelected = nextAdvantages.some(value => !this.selectedAdvantages.includes(value));

        if (!lockedChanged && !missingAutoSelected && autoSelectedToRemove.length === 0) {
            return;
        }

        const setSelectedAdvantages = (nextSelectedAdvantages) => {
            const changed = nextSelectedAdvantages.length !== this.selectedAdvantages.length
                || nextSelectedAdvantages.some((value, index) => value !== this.selectedAdvantages[index]);

            if (changed) {
                this.selectedAdvantages = nextSelectedAdvantages;
            }
        };

        setSelectedAdvantages(this.selectedAdvantages.filter(value => !autoSelectedToRemove.includes(value)));

        const newlyAutoSelected = nextAdvantages.filter(value => !this.selectedAdvantages.includes(value));
        setSelectedAdvantages([...new Set([...this.selectedAdvantages, ...newlyAutoSelected])]);
        this.cultureLocked.advantages = nextAdvantages;
        this.cultureAutoSelectedAdvantages = [
            ...previousAutoSelected.filter(value => nextAdvantages.includes(value) && this.selectedAdvantages.includes(value)),
            ...newlyAutoSelected,
        ];
        this.enforceAdvantageLimit();
    },

    refreshCultureLockedAdvantages() {
        const advantages = this.culture === VOLK_DER_13_INSELN_CULTURE && this.gender === FEMALE_GENDER
            ? [VOLK_DER_13_INSELN_REQUIRED_ADVANTAGE]
            : [];

        this.setCultureLockedAdvantages(advantages);
    },

    // --- Advantages / disadvantages ---
    enforceAdvantageLimit() {
        const max = this.base.freeAdvantages;
        const lockedAdvantages = this.lockedAdvantages();
        const locked = this.selectedAdvantages.filter(value => lockedAdvantages.includes(value));
        const chosen = this.selectedAdvantages.filter(value => value !== 'Zäh' && !lockedAdvantages.includes(value));
        const kept = [];
        let used = 0;

        chosen.forEach((value) => {
            const cost = this.advantageCost(value);
            if (used + cost <= max) {
                kept.push(value);
                used += cost;
            }
        });

        const nextSelectedAdvantages = [...new Set(['Zäh', ...locked, ...kept])];
        const changed = nextSelectedAdvantages.length !== this.selectedAdvantages.length
            || nextSelectedAdvantages.some((value, index) => value !== this.selectedAdvantages[index]);

        if (changed) {
            this.selectedAdvantages = nextSelectedAdvantages;
        }

        this.clampRepeatableAdvantageCounts();
    },

    clampRepeatableAdvantageCounts() {
        Object.entries(advantageRulesByName())
            .filter(([, rule]) => rule.repeatable)
            .forEach(([value]) => this.setAdvantageCount(value, this.advantageCount(value)));
    },

    setAdvantageCount(value, count) {
        const rule = this.advantageRule(value);
        if (!rule?.repeatable) return;

        const parsed = Number.parseInt(count, 10);
        const normalized = Number.isFinite(parsed) ? Math.max(1, parsed) : 1;
        const previous = this.advantageCount(value);
        this.advantageCounts[value] = normalized;

        if (!this.selectedAdvantages.includes(value)) return;

        const selectedWithoutThis = this.selectedAdvantages.filter(selected => selected !== value);
        const usedWithoutThis = selectedWithoutThis.reduce((sum, selected) => sum + this.advantageCost(selected), 0);
        const affordable = Math.max(1, this.base.freeAdvantages - usedWithoutThis);
        this.advantageCounts[value] = Math.min(normalized, affordable);

        if (this.advantageCounts[value] !== previous) {
            this.enforceAdvantageLimit();
        }
    },

    selectedDisabledAdvantages() {
        return this.selectedAdvantages.filter(value => this.isAdvantageDisabled(value));
    },

    selectedLockedDisadvantages() {
        return this.selectedDisadvantages.filter(value => this.isDisadvantageDisabled(value));
    },

    isAdvantageDisabled(value) {
        if (value === 'Zäh') return true;
        if (this.lockedAdvantages().includes(value)) return true;
        if (this.selectedAdvantages.includes(value)) return false;

        const rule = this.advantageRule(value);
        const cost = rule?.cost ?? 1;
        return this.chosenAdvantagesCount() + cost > this.base.freeAdvantages;
    },

    isDisadvantageDisabled(value) {
        return this.raceLocked.disadvantages.includes(value);
    },

    rollD6() {
        return Math.floor(Math.random() * 6) + 1;
    },

    rollW66() {
        const tens = this.rollD6();
        const ones = this.rollD6();

        return { tens, ones, value: tens * 10 + ones };
    },

    ruleForRoll(rules, value) {
        return rules.find(rule => rule.ranges.some(([start, end]) => value >= start && value <= end)) || null;
    },

    rollSpecial(type) {
        const roll = this.rollW66();
        const isAdvantage = type === 'advantage';
        const rule = this.ruleForRoll(isAdvantage ? advantageRules() : disadvantageRules(), roll.value);
        const result = {
            type,
            value: roll.value,
            dice: `${roll.tens}/${roll.ones}`,
            name: rule?.name || '',
            applied: false,
            message: '',
        };

        if (!rule) {
            result.message = 'Kein Tabelleneintrag gefunden.';
            this.lastRoll = result;
            return result;
        }

        result.applied = isAdvantage
            ? this.applyRolledAdvantage(rule)
            : this.applyRolledDisadvantage(rule);
        result.message = result.applied
            ? `${rule.name} wurde übernommen.`
            : `${rule.name} konnte nicht automatisch übernommen werden.`;
        this.lastRoll = result;
        return result;
    },

    applyRolledAdvantage(rule) {
        if (this.lockedAdvantages().includes(rule.name) || rule.name === 'Zäh') return false;

        if (rule.repeatable && this.selectedAdvantages.includes(rule.name)) {
            const previous = this.advantageCount(rule.name);
            this.setAdvantageCount(rule.name, previous + 1);
            return this.advantageCount(rule.name) > previous;
        }

        if (this.isAdvantageDisabled(rule.name)) return false;

        this.selectedAdvantages = [...new Set([...this.selectedAdvantages, rule.name])];
        this.enforceAdvantageLimit();
        return this.selectedAdvantages.includes(rule.name);
    },

    applyRolledDisadvantage(rule) {
        if (this.selectedDisadvantages.includes(rule.name)) return false;

        this.selectedDisadvantages = [...new Set([...this.selectedDisadvantages, rule.name])];
        return true;
    },
}));

    if (hydrateExisting) {
        hydrateExistingCharEditors();
    }
}

if (window.Alpine && typeof window.Alpine.data === 'function') {
    registerCharEditor({ hydrateExisting: true });
} else {
    document.addEventListener('alpine:init', () => registerCharEditor(), { once: true });
}
