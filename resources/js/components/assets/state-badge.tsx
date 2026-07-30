import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

const STATE_BADGE: Record<string, string> = {
    'new': 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    'sold': 'bg-orange-100 text-orange-800 dark:bg-orange-950 dark:text-orange-300',
    'storage': 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300',
    'lend': 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300',
    'defect': 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
    'under-repair': 'bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-300',
    'need-repair': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-950 dark:text-yellow-300',
    'in-use': 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300',
};

export function StateBadge({ state, label }: { state: string; label: string | null }) {
    return <Badge variant="secondary" className={cn('border-0', STATE_BADGE[state] ?? '')}>{label ?? state}</Badge>;
}
