document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-password-toggle]');

    if (! toggle) {
        return;
    }

    const wrapper = toggle.closest('.password-input-wrap');
    const input = wrapper?.querySelector('[data-password-input]');
    const openIcon = toggle.querySelector('[data-eye-open]');
    const closedIcon = toggle.querySelector('[data-eye-closed]');

    if (! input) {
        return;
    }

    const showingPassword = input.type === 'text';
    input.type = showingPassword ? 'password' : 'text';
    toggle.setAttribute('aria-label', showingPassword ? 'Show password' : 'Hide password');
    openIcon?.classList.toggle('hidden', ! showingPassword);
    closedIcon?.classList.toggle('hidden', showingPassword);
});
