import { format, parseISO } from 'date-fns';

const toDate = (value) => (value instanceof Date ? value : parseISO(value));

/** 03 Oct 2026 */
export function formatDate(value) {
    return value ? format(toDate(value), 'dd MMM yyyy') : '';
}

/** 03 Oct 2026, 14:05 */
export function formatDateTime(value) {
    return value ? format(toDate(value), 'dd MMM yyyy, HH:mm') : '';
}

const inr = new Intl.NumberFormat('en-IN', {
    style: 'currency',
    currency: 'INR',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

/**
 * ₹1,23,45,678.90 — money arrives from the server as a decimal string and is formatted, never
 * re-calculated, in the browser.
 */
export function formatMoney(value) {
    if (value === null || value === undefined || value === '') return '';
    return inr.format(Number(value));
}
