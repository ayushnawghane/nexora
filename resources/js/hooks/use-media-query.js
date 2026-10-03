import { useCallback, useSyncExternalStore } from 'react';

/** Live result of a CSS media query, e.g. useMediaQuery('(min-width: 1024px)'). */
export function useMediaQuery(query) {
    const subscribe = useCallback(
        (callback) => {
            const mql = window.matchMedia(query);
            mql.addEventListener('change', callback);
            return () => mql.removeEventListener('change', callback);
        },
        [query],
    );

    return useSyncExternalStore(
        subscribe,
        () => window.matchMedia(query).matches,
        () => true,
    );
}
