import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

export type StatusType = 'deployable' | 'pending' | 'undeployable' | 'archived';

export interface AssetStatus { id: string; name: string | null; type: StatusType | string }

const TYPE_BADGE: Record<string, string> = {
    deployable: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300',
    pending: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    undeployable: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
    archived: 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300',
};

export const STATUS_TYPE_LABELS: Record<StatusType, string> = {
    deployable: 'Einsatzbereit',
    pending: 'Ausstehend',
    undeployable: 'Nicht einsatzbereit',
    archived: 'Archiviert',
};

/** A status label, colored by its type. */
export function StatusBadge({ status }: { status: AssetStatus | null }) {
    if (!status) return <span className="text-muted-foreground">—</span>;

    return (
        <Badge variant="secondary" className={cn('border-0', TYPE_BADGE[status.type] ?? '')}
            title={STATUS_TYPE_LABELS[status.type as StatusType]}>
            {status.name ?? '—'}
        </Badge>
    );
}
