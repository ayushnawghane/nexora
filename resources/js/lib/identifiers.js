/**
 * Client mirror of app/Support/IndianIdentifiers.php, so CIN/PAN/GSTIN mistakes show while typing.
 * The server re-checks everything; keep the two files in step.
 */

export const CIN_PATTERN = /^[LU][0-9]{5}[A-Z]{2}[0-9]{4}[A-Z]{3}[0-9]{6}$/;
export const LLPIN_PATTERN = /^[A-Z]{3}-[0-9]{4}$/;
export const PAN_PATTERN = /^[A-Z]{3}[ABCEFGHJLPT][A-Z][0-9]{4}[A-Z]$/;
export const GSTIN_PATTERN = /^[0-9]{2}[A-Z]{3}[ABCEFGHJLPT][A-Z][0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/;

const CHARSET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

/** Uppercase and strip whitespace, as the server does before validating. */
export function normaliseIdentifier(value) {
    return (value ?? '').replace(/\s+/g, '').toUpperCase();
}

export function gstinCheckCharacter(first14) {
    let sum = 0;
    for (let i = 0; i < 14; i++) {
        const product = CHARSET.indexOf(first14[i]) * (i % 2 === 0 ? 1 : 2);
        sum += Math.floor(product / 36) + (product % 36);
    }
    return CHARSET[(36 - (sum % 36)) % 36];
}

/** Holder-type letters a PAN may carry for each entity type (4th character). */
const PAN_HOLDER_TYPES = { company: ['C'], llp: ['F', 'E'], other: [] };

/** Returns an error message for a CIN/LLPIN, or null when it looks right (or is still empty). */
export function cinError(value, entityType) {
    if (!value) return null;
    if (entityType === 'llp') {
        return LLPIN_PATTERN.test(value) ? null : 'An LLPIN looks like AAB-1234.';
    }
    return CIN_PATTERN.test(value) ? null : 'A CIN is 21 characters, like U65990MH2010PTC123456.';
}

export function panError(value, entityType) {
    if (!value) return null;
    if (!PAN_PATTERN.test(value)) return 'A PAN is 10 characters, like AAACB1234C.';
    const allowed = PAN_HOLDER_TYPES[entityType] ?? [];
    if (allowed.length && !allowed.includes(value[3])) {
        return entityType === 'company'
            ? 'A company PAN has "C" as its 4th character.'
            : 'An LLP PAN has "F" or "E" as its 4th character.';
    }
    return null;
}

/** Checks format, check digit and (when given) that the GSTIN was issued under the company's PAN. */
export function gstinError(value, pan) {
    if (!value) return null;
    if (value.length !== 15) return 'A GSTIN is 15 characters.';
    if (!GSTIN_PATTERN.test(value)) return 'A GSTIN looks like 27AAACB1234C1Z5.';
    if (gstinCheckCharacter(value.slice(0, 14)) !== value[14]) {
        return 'This GSTIN fails its check-digit test. Check it for typing mistakes.';
    }
    if (pan && value.slice(2, 12) !== pan) {
        return `This GSTIN belongs to PAN ${value.slice(2, 12)}, not the company's PAN ${pan}.`;
    }
    return null;
}

/** What a CIN tells us: listed (L/U prefix), year and MCA ownership code. */
export function describeCin(cin) {
    if (!CIN_PATTERN.test(cin ?? '')) return null;
    return { listed: cin[0] === 'L', year: Number(cin.slice(8, 12)), ownership: cin.slice(12, 15) };
}
