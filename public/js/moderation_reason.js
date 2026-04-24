/**
 * public/js/moderation_reason.js
 * -------------------------------------------------------
 * Propage le champ "raison" vers les hidden inputs des
 * formulaires de modération avant soumission.
 *
 * Chaque formulaire `.moderation-form` a un attribut
 * `data-reason-source` qui pointe vers l'id de l'input
 * texte contenant la raison saisie par le modérateur.
 *
 * Inclure dans moderation/dashboard.html.twig :
 *   <script src="{{ asset('js/moderation_reason.js') }}" defer></script>
 */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.moderation-form').forEach(form => {
        form.addEventListener('submit', () => {
            const sourceId = form.dataset.reasonSource;
            const sourceInput = sourceId ? document.getElementById(sourceId) : null;
            const reasonField = form.querySelector('.reason-field');

            if (sourceInput && reasonField) {
                reasonField.value = sourceInput.value.trim();
            }
        });
    });
});
