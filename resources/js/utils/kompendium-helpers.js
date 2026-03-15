/**
 * Zeigt oder verbirgt den Phrasen-Hinweis basierend auf der Server-Response.
 *
 * @param {HTMLElement|null} $phraseHint  - Das Hint-Container-Element
 * @param {HTMLElement|null} $phraseHintText - Das Textfeld im Hint
 * @param {object} json - Die Server-Response mit isPhraseSearch und searchInfo
 */
export function updatePhraseHint($phraseHint, $phraseHintText, json) {
    if (!$phraseHint || !$phraseHintText) return;

    if (json && json.isPhraseSearch && json.searchInfo) {
        const phrases = Array.isArray(json.searchInfo.phrases) ? json.searchInfo.phrases : [];
        const terms = Array.isArray(json.searchInfo.terms) ? json.searchInfo.terms : [];
        const parts = [];
        phrases.forEach(p => parts.push(`\u201E${p}\u201C`));
        terms.forEach(t => parts.push(`\u201E${t}\u201C`));

        $phraseHintText.textContent = `Phrasensuche aktiv: Nur exakte Treffer f\u00FCr ${parts.join(' + ')}`;
        $phraseHint.classList.remove('hidden');
    } else {
        $phraseHint.classList.add('hidden');
    }
}
