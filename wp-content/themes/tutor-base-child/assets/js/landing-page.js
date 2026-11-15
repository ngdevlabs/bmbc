(() => {
    const dialogs = [...document.querySelectorAll('.bmb-landing-dialog')];
    if (!dialogs.length) return;
    const video = document.querySelector('#bmb-introduction-video');
    let trigger = null, resumeAt = 0;
    function setLoginDestination() {
        const forms = window.KirkiComponentLibrary?.form;
        if (forms) Object.values(forms).forEach(form => { if (form.name === 'kirki-login') form.successURL = bmbLanding.dashboardUrl; });
    }
    video?.addEventListener('loadedmetadata', () => { if (resumeAt > 0 && resumeAt < video.duration) video.currentTime = resumeAt; });
    document.addEventListener('click', event => {
        const open = event.target.closest('[data-bmb-dialog]');
        if (open) {
            const dialog = document.getElementById(open.dataset.bmbDialog);
            if (!dialog || typeof dialog.showModal !== 'function') return;
            event.preventDefault(); trigger = open;
            dialogs.filter(item => item.open && item !== dialog).forEach(item => item.close());
            setLoginDestination(); dialog.showModal(); document.body.classList.add('bmb-landing-modal-open');
            if (dialog.id === 'bmb-introduction-dialog' && video) {
                video.src = video.dataset.introductionSrc; video.load(); video.play().catch(() => {});
            } else {
                requestAnimationFrame(() => dialog.querySelector('input[name="email"]')?.focus());
            }
            return;
        }
        const close = event.target.closest('[data-bmb-close]');
        if (close) close.closest('dialog')?.close();
    });
    dialogs.forEach(dialog => {
        dialog.addEventListener('click', event => {
            if (event.target !== dialog) return;
            const rect = dialog.getBoundingClientRect();
            if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close();
        });
        dialog.addEventListener('close', () => {
            if (dialog.id === 'bmb-introduction-dialog' && video) {
                resumeAt = video.currentTime || 0; video.pause(); video.removeAttribute('src'); video.load();
            }
            if (dialog.id === 'bmb-login-dialog' && location.hash === '#login') history.replaceState(null, '', location.pathname + location.search);
            if (!dialogs.some(item => item.open)) document.body.classList.remove('bmb-landing-modal-open');
            trigger?.focus({preventScroll: true});
        });
    });
    function openLinkedLogin() {
        if (location.hash === '#login' && !document.querySelector('#bmb-login-dialog')?.open) document.querySelector('[data-bmb-dialog="bmb-login-dialog"]')?.click();
    }
    window.addEventListener('hashchange', openLinkedLogin);
    openLinkedLogin();
    document.addEventListener('submit', event => { if (event.target.matches('[data-ele_name="kirki-login"]')) setLoginDestination(); }, true);
})();
