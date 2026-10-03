import { useCallback, useSyncExternalStore } from 'react';

const STORAGE_KEY = 'nexora-theme';

function readTheme() {
    return document.documentElement.dataset.theme === 'light' ? 'light' : 'dark';
}

function subscribe(callback) {
    const observer = new MutationObserver(callback);
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-theme'],
    });
    return () => observer.disconnect();
}

/**
 * Current theme ('dark' | 'light') from <html data-theme>, which app.blade.php sets before first
 * paint. setTheme updates the attribute and remembers the choice locally; callers persist it to
 * the user's profile.
 */
export function useTheme() {
    const theme = useSyncExternalStore(subscribe, readTheme, () => 'dark');

    const setTheme = useCallback((next) => {
        document.documentElement.dataset.theme = next;
        try {
            localStorage.setItem(STORAGE_KEY, next);
        } catch {
            // Storage can be unavailable (private mode); the attribute still applies.
        }
    }, []);

    return { theme, setTheme };
}
