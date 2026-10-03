import { usePage } from '@inertiajs/react';
import { useCallback } from 'react';

/** Returns can(permission): true when the signed-in user holds that permission. */
export function usePermissions() {
    const permissions = usePage().props.auth?.permissions;

    return useCallback(
        (permission) => !permission || (permissions ?? []).includes(permission),
        [permissions],
    );
}

/** Filters the navigation config down to items the user can open and whose routes exist. */
export function filterNavigation(groups, can) {
    const allowed = (item) => can(item.permission) && (!item.route || route().has(item.route));

    return groups
        .map((group) => ({
            ...group,
            items: group.items
                .map((item) => (item.items ? { ...item, items: item.items.filter(allowed) } : item))
                .filter((item) => (item.items ? item.items.length > 0 : allowed(item))),
        }))
        .filter((group) => group.items.length > 0);
}

export function isActive(item) {
    if (item.route && route().current(item.route)) return true;
    if (item.route && route().current(`${item.route.replace(/\.index$/, '')}.*`)) return true;
    return Boolean(item.items?.some(isActive));
}
