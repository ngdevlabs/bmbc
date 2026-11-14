(() => {
    const country = document.querySelector('#signup-country');
    if (!country) return;
    const states = document.querySelector('#signup-state-us');
    const other = document.querySelector('#signup-state-other');
    function updateState() {
        const isUS = country.value === 'US';
        states.hidden = !isUS; states.disabled = !isUS; states.required = isUS;
        other.hidden = isUS; other.disabled = isUS; other.required = !isUS;
        document.querySelector('#signup-state-label').htmlFor = isUS ? states.id : other.id;
    }
    country.addEventListener('change', updateState); updateState();
    const password = document.querySelector('#signup-password');
    const confirm = document.querySelector('#signup-confirm-password');
    function validatePassword() { confirm.setCustomValidity(confirm.value && confirm.value !== password.value ? 'The passwords do not match.' : ''); }
    password.addEventListener('input', validatePassword); confirm.addEventListener('input', validatePassword);
})();
