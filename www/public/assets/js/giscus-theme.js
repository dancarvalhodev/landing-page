// Mantém o tema dos comentários (Giscus) em sincronia com o tema do site.
// O Giscus aceita uma URL de CSS no lugar do nome de um tema pronto; usamos as folhas de estilo
// com as cores da marca (precisa ser https:// — não funciona em http://localhost, só após o deploy).
const themeUrl = (name) => `${window.location.origin}/assets/css/giscus-${name}.css`;

const currentTheme = () => {
    const html = document.documentElement;
    const isDark = html.classList.contains('dark')
        || (!html.classList.contains('light') && window.matchMedia('(prefers-color-scheme: dark)').matches);

    return themeUrl(isDark ? 'dark' : 'light');
};

const sendGiscusTheme = () => {
    const iframe = document.querySelector('iframe.giscus-frame');

    if (!iframe) {
        return;
    }

    iframe.contentWindow.postMessage({ giscus: { setConfig: { theme: currentTheme() } } }, 'https://giscus.app');
};

// Giscus avisa quando terminou de carregar; manda o tema certo assim que ele aparecer.
window.addEventListener('message', (event) => {
    if (event.origin === 'https://giscus.app' && event.data?.giscus?.discussion) {
        sendGiscusTheme();
    }
});

// Reage à troca de tema não importa quem mudou a classe (botão, ou o próprio sistema).
new MutationObserver(sendGiscusTheme).observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['class'],
});

window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', sendGiscusTheme);
