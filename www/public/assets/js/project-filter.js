// Mostra só os projetos da categoria escolhida; "Todos" mostra tudo de novo.
const ACTIVE_CLASSES = ['bg-accent-soft', 'text-accent'];
const INACTIVE_CLASSES = ['text-muted', 'ring-1', 'ring-line', 'ring-inset'];

const filterBar = document.querySelector('[data-project-filter]');

if (filterBar) {
    const cards = document.querySelectorAll('[data-category]');

    filterBar.addEventListener('click', (event) => {
        const button = event.target.closest('[data-filter]');

        if (!button) {
            return; // clique fora de um botão, ex: espaço vazio da barra
        }

        // Marca o botão clicado como ativo; os outros voltam ao estilo normal.
        filterBar.querySelectorAll('[data-filter]').forEach((candidate) => {
            const isActive = candidate === button;

            candidate.setAttribute('aria-pressed', String(isActive));
            candidate.classList.remove(...ACTIVE_CLASSES, ...INACTIVE_CLASSES);
            candidate.classList.add(...(isActive ? ACTIVE_CLASSES : INACTIVE_CLASSES));
        });

        // "all" mostra tudo; qualquer outra categoria esconde quem não bate.
        const filter = button.dataset.filter;

        cards.forEach((card) => {
            card.hidden = filter !== 'all' && card.dataset.category !== filter;
        });
    });
}
