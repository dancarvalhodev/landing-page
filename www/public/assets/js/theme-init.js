// Aplica uma escolha salva antes do primeiro desenho, pra não piscar o tema errado.
// Sem escolha salva, o CSS decide sozinho pelo tema do sistema (@media prefers-color-scheme).
const theme = localStorage.getItem('theme');

if (theme === 'dark' || theme === 'light') {
    document.documentElement.classList.add(theme);
}
