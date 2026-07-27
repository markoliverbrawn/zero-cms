document.addEventListener('DOMContentLoaded', () => {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme !== null) document.body.setAttribute('data-theme', savedTheme);
    const themeLinks = document.querySelectorAll('[data-set-theme]');
    themeLinks.forEach(link => {
        link.addEventListener('click', () => {
            const targetTheme = link.getAttribute('data-set-theme');
            document.body.setAttribute('data-theme', targetTheme);
            localStorage.setItem('theme', targetTheme);
        });
    });
});
