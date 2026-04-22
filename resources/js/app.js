import Alpine from 'alpinejs';

const themeKey = 'kynex-theme';

const applyTheme = (theme) => {
    const resolved = theme === 'dark' ? 'dark' : 'light';
    document.documentElement.classList.toggle('dark', resolved === 'dark');
    document.documentElement.dataset.theme = resolved;
};

window.KynexTheme = {
    current() {
        return localStorage.getItem(themeKey) || 'light';
    },
    set(theme) {
        localStorage.setItem(themeKey, theme);
        applyTheme(theme);
        window.dispatchEvent(new CustomEvent('kynex-theme-changed', {
            detail: { theme },
        }));
    },
    toggle() {
        this.set(this.current() === 'dark' ? 'light' : 'dark');
    },
};

applyTheme(window.KynexTheme.current());

window.Alpine = Alpine;
Alpine.data('themeToggle', () => ({
    isDark: window.KynexTheme.current() === 'dark',
    init() {
        window.addEventListener('kynex-theme-changed', (event) => {
            this.isDark = event.detail.theme === 'dark';
        });
    },
    toggle() {
        window.KynexTheme.toggle();
    },
}));

Alpine.data('appShell', () => ({
    mobileMenuOpen: false,
    lastFocusedElement: null,
    init() {
        this.$watch('mobileMenuOpen', (open) => {
            document.body.classList.toggle('overflow-hidden', open);

            if (open) {
                this.lastFocusedElement = document.activeElement;

                this.$nextTick(() => {
                    const firstTarget = this.$refs.mobileCloseButton
                        ?? this.$refs.mobileDrawer?.querySelector('a, button, [tabindex]:not([tabindex="-1"])');

                    firstTarget?.focus();
                });

                return;
            }

            this.$nextTick(() => {
                this.lastFocusedElement?.focus?.();
            });
        });
    },
    openMobileMenu() {
        this.mobileMenuOpen = true;
    },
    closeMobileMenu() {
        this.mobileMenuOpen = false;
    },
    handleKeydown(event) {
        if (event.key !== 'Tab' || !this.mobileMenuOpen || !this.$refs.mobileDrawer) {
            return;
        }

        const focusable = this.$refs.mobileDrawer.querySelectorAll(
            'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
        );

        if (focusable.length === 0) {
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    },
}));

Alpine.start();
