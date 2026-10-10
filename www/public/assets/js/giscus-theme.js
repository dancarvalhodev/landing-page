// Mantém o tema dos comentários (Giscus) em sincronia com o tema do site.
// O Giscus aceita uma URL de CSS no lugar do nome de um tema pronto; usamos as folhas de estilo
// com as cores da marca.
// As URLs vêm do template (data-light e data-dark), já com o ?v= que fura o cache de um ano.
const themeUrls = document.currentScript.dataset;

const currentTheme = () => {
    const html = document.documentElement;
    const isDark = html.classList.contains('dark')
        || (!html.classList.contains('light') && window.matchMedia('(prefers-color-scheme: dark)').matches);

    // O Giscus (https) não consegue baixar um CSS de http://localhost; lá valem os temas prontos.
    if (window.location.protocol !== 'https:') {
        return isDark ? 'dark' : 'light';
    }

    return isDark ? themeUrls.dark : themeUrls.light;
};

const sendGiscusTheme = () => {
    const iframe = document.querySelector('iframe.giscus-frame');

    if (!iframe) {
        return;
    }

    iframe.contentWindow.postMessage({ giscus: { setConfig: { theme: currentTheme() } } }, 'https://giscus.app');
};

// Carrega o Giscus já com o tema certo: o client.js lê as configurações (data-*) da própria tag.
// Se o tema só chegasse depois, por mensagem, os comentários podiam ficar com as cores padrão.
const container = document.querySelector('.giscus');

if (container) {
    const loader = document.createElement('script');

    Object.assign(loader.dataset, container.dataset);
    loader.dataset.theme = currentTheme();
    loader.src = 'https://giscus.app/client.js';
    loader.crossOrigin = 'anonymous';
    loader.async = true;

    document.body.append(loader);
}

// Reenvia o tema a cada mensagem do Giscus (a altura do iframe, por exemplo): cobre a troca de
// tema feita antes de o iframe estar pronto e o recarregamento do iframe ao sair da conta.
window.addEventListener('message', (event) => {
    if (event.origin === 'https://giscus.app' && event.data?.giscus) {
        sendGiscusTheme();
    }
});

// Reage à troca de tema não importa quem mudou a classe (botão, ou o próprio sistema).
new MutationObserver(sendGiscusTheme).observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['class'],
});

window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', sendGiscusTheme);
