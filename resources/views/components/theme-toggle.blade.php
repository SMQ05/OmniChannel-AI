<button
    type="button"
    x-data="themeToggle"
    @click="toggle()"
    class="theme-toggle inline-flex items-center gap-2 rounded-full px-3 py-2 text-sm font-medium transition-colors"
    :aria-pressed="isDark.toString()"
    :title="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
>
    <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-[var(--brand-soft)] text-[var(--brand-strong)]">
        <svg x-show="!isDark" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3v2.25m0 13.5V21m9-9h-2.25M5.25 12H3m15.114 6.364-1.591-1.591M7.477 7.477 5.886 5.886m12.228 0-1.591 1.591M7.477 16.523l-1.591 1.591M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
        </svg>
        <svg x-show="isDark" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:none;">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 12.79A9 9 0 0 1 11.21 3c0 .34-.03.68-.08 1.01A9 9 0 1 0 20 12.87c.34-.05.68-.08 1-.08Z"/>
        </svg>
    </span>
    <span x-text="isDark ? 'Dark' : 'Light'"></span>
</button>
