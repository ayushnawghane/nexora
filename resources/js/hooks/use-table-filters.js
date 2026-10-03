import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

const withoutEmpty = (filters) =>
    Object.fromEntries(
        Object.entries(filters).filter(([, v]) => v !== '' && v !== null && v !== undefined),
    );

/**
 * Keeps list filters in the URL (spatie/laravel-query-builder `filter[...]` format) so results are
 * shareable and survive refresh. Text filters are debounced; changing any filter resets the page.
 */
export function useTableFilters(initial, { sort, debounce = 300 } = {}) {
    const [filters, setFilters] = useState(initial);
    const first = useRef(true);
    const active = withoutEmpty(filters);

    useEffect(() => {
        if (first.current) {
            first.current = false;
            return undefined;
        }
        const timer = setTimeout(() => {
            router.get(
                window.location.pathname,
                { filter: withoutEmpty(filters), sort },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, debounce);
        return () => clearTimeout(timer);
        // Only a filter change should trigger a request; sort is read at fire time.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [filters]);

    const setFilter = (key, value) => setFilters((current) => ({ ...current, [key]: value }));

    return { filters, setFilter, query: { filter: active } };
}
