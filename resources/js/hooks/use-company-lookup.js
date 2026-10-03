import axios from 'axios';
import { useState } from 'react';

/**
 * Calls the CIN / GSTIN / PAN registry lookup. Results only pre-fill the form; nothing is saved
 * until the user submits it. Returns { data, existing } or throws an Error with a user-facing message.
 */
export function useCompanyLookup() {
    const [loading, setLoading] = useState(null);

    const lookup = async (type, value) => {
        setLoading(type);
        try {
            const response = await axios.get(route('companies.lookup', type), {
                params: { value },
            });
            return response.data;
        } catch (error) {
            const message =
                error.response?.status === 429
                    ? 'Too many lookups in a short time. Wait a minute and try again.'
                    : (error.response?.data?.message ??
                      'The lookup failed. Enter the details by hand, or try again.');
            throw new Error(message, { cause: error });
        } finally {
            setLoading(null);
        }
    };

    return { lookup, loading };
}
