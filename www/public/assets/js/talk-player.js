// Troca a capa da palestra pelo player do YouTube no clique; sem JS, o link abre o vídeo no YouTube.
document.querySelectorAll('[data-talk-player]').forEach((cover) => {
    cover.addEventListener('click', (event) => {
        event.preventDefault();

        const player = document.createElement('iframe');

        player.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(cover.dataset.talkPlayer) + '?autoplay=1';
        player.title = cover.dataset.title;
        player.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
        player.referrerPolicy = 'strict-origin-when-cross-origin';
        player.allowFullscreen = true;
        player.className = 'aspect-video w-full rounded-box';

        cover.replaceWith(player);
    });
});
