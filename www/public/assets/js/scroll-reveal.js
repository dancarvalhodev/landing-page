// Revela as seções aos poucos conforme entram na tela.
// A transição em si vem do CSS (regra global em app.css); aqui só escondemos e revelamos.
// As classes que escondem a seção só são aplicadas aqui: sem suporte a
// IntersectionObserver (ou se o script não rodar), as seções ficam visíveis normalmente.
if ('IntersectionObserver' in window) {
    const HIDDEN_CLASSES = ['motion-safe:opacity-0', 'motion-safe:translate-y-6'];

    const sections = document.querySelectorAll('[data-reveal]');

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.remove(...HIDDEN_CLASSES);
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.2 });

    sections.forEach((section) => {
        section.classList.add(...HIDDEN_CLASSES);
        observer.observe(section);
    });
}
