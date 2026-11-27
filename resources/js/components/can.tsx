import type { ReactNode } from 'react';
import { useCan } from '@/hooks/use-can';

/** Renders its children only when the user holds `permission`. */
export function Can({ permission, children }: { permission: string; children: ReactNode }) {
    return useCan()(permission) ? <>{children}</> : null;
}
