import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';

export interface HistoryChange { field: string; label: string; old: string | null; new: string | null; }
export interface HistoryEntry {
    id: number; event: string; event_label: string; causer_name: string;
    created_at: string | null; changes: HistoryChange[];
}

const EVENT_VARIANT: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    created: 'default', updated: 'secondary', deleted: 'destructive',
};

export function AssetHistory({ history }: { history: HistoryEntry[] }) {
    if (history.length === 0) {
        return <p className="text-sm text-muted-foreground">No history yet.</p>;
    }

    return (
        <div className="space-y-3">
            {history.map((e) => (
                <Card key={e.id}>
                    <CardContent className="py-4">
                        <div className="flex items-center gap-3 text-sm">
                            <Badge variant={EVENT_VARIANT[e.event] ?? 'outline'}>{e.event_label}</Badge>
                            <span className="font-medium">{e.causer_name}</span>
                            <span className="text-xs text-muted-foreground">{e.created_at ?? ''}</span>
                        </div>
                        {e.changes.length > 0 && (
                            <ul className="mt-2 space-y-1 text-sm">
                                {e.changes.map((c) => (
                                    <li key={c.field} className="text-muted-foreground">
                                        <span className="font-medium text-foreground">{c.label}</span>
                                        {': '}
                                        <span>{c.old ?? '—'}</span>
                                        <span className="mx-1">→</span>
                                        <span className="text-foreground">{c.new ?? '—'}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}
