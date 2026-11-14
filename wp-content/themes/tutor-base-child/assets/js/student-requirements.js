(() => {
    if (typeof bmbRequirements === 'undefined') return;
    const config = bmbRequirements;
    const classes = new Map(config.classes.map(item => [String(item.id), item]));
    const paths = new Map();
    const notebookPaths = new Map();
    const path = href => { try { return decodeURIComponent(new URL(href, location.href).pathname).replace(/\/$/, ''); } catch { return ''; } };
    config.classes.forEach(item => {
        paths.set(path(item.url), item.id);
        item.quizzes.forEach(quiz => paths.set(path(quiz.url), quiz.id));
        if (item.notebook_url) notebookPaths.set(path(item.notebook_url), item.id);
    });
    const current = () => document.querySelector('[data-bmb-selected]')?.dataset.bmbSelected || String(paths.get(path(location.href)) || '');
    const classFor = id => classes.get(String(id)) || config.classes.find(item => item.quizzes.some(quiz => quiz.id === Number(id)));
    let toast, fadeTimer, removeTimer;
    function notify(title, messages) {
        clearTimeout(fadeTimer); clearTimeout(removeTimer);
        if (!toast) { toast = document.createElement('div'); toast.className = 'bmb-requirement-notice'; toast.setAttribute('role', 'status'); toast.setAttribute('aria-live', 'polite'); toast.setAttribute('aria-atomic', 'true'); document.body.appendChild(toast); }
        toast.replaceChildren();
        const heading = document.createElement('strong'); heading.textContent = title; toast.appendChild(heading);
        const list = document.createElement('ul'); messages.forEach(message => { const li = document.createElement('li'); li.textContent = message; list.appendChild(li); }); toast.appendChild(list);
        toast.hidden = false; toast.classList.remove('bmb-notice-fading');
        fadeTimer = setTimeout(() => { toast.classList.add('bmb-notice-fading'); removeTimer = setTimeout(() => { toast.hidden = true; }, 250); }, 5000);
    }
    function update(state) {
        const item = classes.get(String(state.lesson_id)); if (item) item.state = state;
        document.querySelectorAll('[data-bmb-notebook-status]').forEach(el => {
            if (el.dataset.bmbNotebookStatus === String(state.lesson_id)) el.textContent = state.notebook_downloaded ? '✓ Notebook download recorded. You can download it again anytime.' : 'Required: download this class notebook before taking the quiz.';
        });
        document.querySelectorAll('[data-bmb-notebook-check]').forEach(el => { if (el.dataset.bmbNotebookCheck === String(state.lesson_id)) el.checked = state.notebook_downloaded; });
        document.querySelectorAll('[data-bmb-notebook]').forEach(el => { if (el.dataset.bmbNotebook === String(state.lesson_id)) el.dataset.downloaded = String(state.notebook_downloaded); });
        document.dispatchEvent(new CustomEvent('bmb:requirements', {detail: state}));
    }
    async function request(mode, id) {
        const response = await fetch(config.ajaxUrl, {method: 'POST', credentials: 'same-origin', body: new URLSearchParams({action: 'bmb_student_requirements', nonce: config.nonce, mode, post_id: String(id)})});
        const result = await response.json().catch(() => null);
        if (!response.ok || !result?.success) throw Error(result?.data?.message || 'Progress could not be checked. Please try again.');
        return result.data;
    }
    function scan() {
        document.querySelectorAll('a[href]').forEach(a => {
            const id = notebookPaths.get(path(a.getAttribute('href')));
            if (id) { a.dataset.bmbNotebook = String(id); a.classList.add('bmb-notebook-download'); a.setAttribute('download', ''); }
        });
        const item = classFor(current());
        if (item) document.querySelectorAll('.tutor-tabs-nav button').forEach(button => {
            if (/Download Class Notebook/.test(button.textContent) && !button.dataset.bmbNotebook) {
                button.dataset.bmbNotebook = String(item.id); button.classList.add('bmb-notebook-download'); button.setAttribute('title', 'Download Class Notebook (PDF)');
            }
        });
        classes.forEach(item => {
            document.querySelectorAll(`[data-bmb-notebook="${item.id}"]`).forEach(el => { const value = String(item.state.notebook_downloaded); if (el.dataset.downloaded !== value) el.dataset.downloaded = value; });
        });
    }
    let pending = false;
    window.addEventListener('click', async event => {
        const el = event.target.closest('a, button'); if (!el || el.classList.contains('bmb-notebook-transfer')) return;
        const notebook = el.dataset.bmbNotebook;
        if (notebook) {
            event.preventDefault();
            if (pending) return;
            pending = true;
            try {
                const result = await request('download', notebook); update(result.state);
                const link = document.createElement('a'); link.className = 'bmb-notebook-transfer'; link.href = result.download_url; link.download = ''; link.hidden = true; document.body.appendChild(link); link.click(); link.remove();
            } catch (error) { notify('Notebook download', [error.message]); }
            finally { pending = false; }
            return;
        }
        if (el.tagName !== 'A' || el.origin !== location.origin) return;
        const url = new URL(el.href); let id = paths.get(path(url.href));
        if (path(url.href) === path(config.dashboardUrl) && url.searchParams.has('class')) id = Number(url.searchParams.get('class'));
        if (!id || (el.classList.contains('active') && el.closest('.tutor-learning-sidebar'))) return;
        event.preventDefault(); event.stopImmediatePropagation();
        if (pending) return;
        pending = true;
        try {
            const result = await request('navigate', id); update(result.state);
            if (!result.allowed) { notify('Before proceeding: ' + result.state.title, result.state.missing); return; }
            if (event.ctrlKey || event.metaKey || el.target === '_blank') window.open(el.href, '_blank', 'noopener');
            else location.assign(el.href);
        } catch (error) { notify('Unable to continue', [error.message]); }
        finally { pending = false; }
    }, true);
    document.addEventListener('bmb:video-progress', event => {
        const item = classes.get(String(event.detail.lesson_id)); if (item) item.state = {...item.state, ...event.detail};
    });
    document.addEventListener('visibilitychange', async () => {
        if (document.hidden) return;
        const item = classFor(current());
        if (item) { try { const result = await request('state', item.id); update(result.state); } catch { /* The next action retries with a visible error. */ } }
    });
    scan();
    let scheduled = false;
    new MutationObserver(() => { if (!scheduled) { scheduled = true; requestAnimationFrame(() => { scheduled = false; scan(); }); } }).observe(document.body, {childList: true, subtree: true});
    if (config.initialNotice) notify('Before proceeding: ' + config.initialNotice.title, config.initialNotice.missing);
})();
