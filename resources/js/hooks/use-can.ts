import { usePage } from '@inertiajs/react';
import type { PageProps } from '@/types';

/**
 * Returns a predicate over the signed-in user's permission map (`auth.can`).
 * A missing key, a missing prop or the guest shape (an empty array) is "no".
 */
export function useCan(): (permission: string) => boolean {
    const can = usePage<PageProps>().props.auth?.can;
    return (permission) => !!can && !Array.isArray(can) && typeof can === 'object' && can[permission] === true;
}
