import { Link, router } from '@inertiajs/react';
import { LogIn } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useCan } from '@/hooks/use-can';

export interface InstalledComponent {
    id: string;
    component_id: string;
    name: string;
    serial: string | null;
    qty: number;
    checked_out_at: string | null;
    note: string | null;
}

/** The components installed in an asset, with check-in of the whole quantity. */
export function InstalledComponents({ components, empty }: { components: InstalledComponent[]; empty: string }) {
    const can = useCan();
    const checkin = (c: InstalledComponent) => {
        if (confirm(`Check in ${c.qty} × ${c.name}?`)) {
            router.post(`/app/component-assets/${c.id}/checkin`, { qty: c.qty }, { preserveScroll: true });
        }
    };

    return (
        <Card>
            <CardHeader className="pb-2"><CardTitle className="text-base">Components ({components.length})</CardTitle></CardHeader>
            <CardContent>
                {components.length === 0 ? (
                    <p className="text-sm text-muted-foreground">{empty}</p>
                ) : (
                    <ul className="divide-y text-sm">
                        {components.map((c) => (
                            <li key={c.id} className="flex items-center justify-between gap-2 py-2">
                                <span>
                                    <Link href={`/app/components/${c.component_id}`} className="font-medium hover:underline">{c.name}</Link>
                                    <span className="text-muted-foreground"> ×{c.qty}</span>
                                    {c.serial && <span className="text-muted-foreground"> · {c.serial}</span>}
                                </span>
                                <span className="flex items-center gap-2 text-xs text-muted-foreground">
                                    {c.checked_out_at ? `since ${c.checked_out_at}` : ''}
                                    {can('components.checkin') && (
                                        <Button size="sm" variant="ghost" onClick={() => checkin(c)}><LogIn className="h-4 w-4" /> Check in</Button>
                                    )}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
