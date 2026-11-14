(() => {
    const input = document.querySelector('[data-ele_name="kirki-login"] input[name="password"]');
    if (!input || input.parentElement.querySelector('.bmb-password-toggle')) return;

    input.parentElement.classList.add('bmb-password-field');
    if (!input.id) input.id = 'bmb-login-password';

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'bmb-password-toggle';
    button.setAttribute('aria-controls', input.id);
    const eye = '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>';
    const slash = '<path d="m3 3 18 18"/>';

    function updateButton(visible) {
        button.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
        button.setAttribute('aria-pressed', String(visible));
        button.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + eye + (visible ? slash : '') + '</svg>';
    }

    updateButton(false);
    button.addEventListener('click', () => {
        const visible = input.type === 'password';
        input.type = visible ? 'text' : 'password';
        updateButton(visible);
    });
    input.parentElement.appendChild(button);
})();
