(() => {
    const storageKey = 'dci-vault-theme';
    const root = document.documentElement;
    let savedTheme = null;
    try { savedTheme = localStorage.getItem(storageKey); } catch (error) {}
    const initialTheme = savedTheme === 'dark' || savedTheme === 'light'
        ? savedTheme
        : (window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

    const applyTheme = theme => {
        root.dataset.theme = theme;
        const button = document.getElementById('themeToggle');
        if (!button) return;
        const isDark = theme === 'dark';
        button.classList.toggle('theme-toggle-dark', isDark);
        button.setAttribute('aria-pressed', String(isDark));
        button.setAttribute('aria-label', `Switch to ${isDark ? 'light' : 'dark'} mode`);
        button.title = `Switch to ${isDark ? 'light' : 'dark'} mode`;
        button.innerHTML = `<i class="bi bi-${isDark ? 'sun' : 'moon-stars'}-fill" aria-hidden="true"></i><span>${isDark ? 'Light' : 'Dark'}</span>`;
    };

    applyTheme(initialTheme);
    document.addEventListener('DOMContentLoaded', () => {
        const button = document.createElement('button');
        button.type = 'button';
        button.id = 'themeToggle';
        const header = document.querySelector('.main-header');
        const profile = header?.querySelector('.user-profile');
        button.className = `theme-toggle btn${header ? '' : ' theme-toggle-floating'}`;
        button.setAttribute('aria-pressed', String(initialTheme === 'dark'));
        if (profile) header.insertBefore(button, profile.nextSibling);
        else if (header) header.append(button);
        else document.body.append(button);
        applyTheme(root.dataset.theme);
        button.addEventListener('click', () => {
            const theme = root.dataset.theme === 'dark' ? 'light' : 'dark';
            try { localStorage.setItem(storageKey, theme); } catch (error) {}
            applyTheme(theme);
        });
    });
})();
