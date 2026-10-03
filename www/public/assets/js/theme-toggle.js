// Alterna entre tema claro e escuro e lembra a escolha em localStorage.
const button = document.querySelector('[data-theme-toggle]');

if (button) {
    const html = document.documentElement;

    // Sem classe explícita (.dark/.light), o tema em uso é o do sistema operacional.
    const isDarkNow = () =>
        html.classList.contains('dark')
        || (!html.classList.contains('light') && window.matchMedia('(prefers-color-scheme: dark)').matches);

    const setIcon = () => {
        button.textContent = isDarkNow() ? '☀️' : '🌙';
    };

    setIcon();

    button.addEventListener('click', () => {
        const nextTheme = isDarkNow() ? 'light' : 'dark';

        html.classList.remove('dark', 'light');
        html.classList.add(nextTheme);
        setIcon();
        localStorage.setItem('theme', nextTheme);
    });
}
