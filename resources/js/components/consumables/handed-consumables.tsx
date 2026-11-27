import { Link } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export interface HandedConsumable {
    id: string;
    consumable_id: string;
    name: string;
    qty: number;
    checked_out_at: string | null;
}

/** What a user was handed, newest first. */
export function HandedConsumables({ consumables }: { consumables: HandedConsumable[] }) {
    return (
        <Card>
            <CardHeader className="pb-2"><CardTitle className="text-base">Consumables ({consumables.reduce((n, c) => n + c.qty, 0)})</CardTitle></CardHeader>
            <CardContent>
                {consumables.length === 0 ? (
                    <p className="text-sm text-muted-foreground">No consumables were handed to this user.</p>
                ) : (
                    <ul className="divide-y text-sm">
                        {consumables.map((c) => (
                            <li key={c.id} className="flex items-center justify-between py-2">
                                <span>
                                    <Link href={`/app/consumables/${c.consumable_id}`} className="font-medium hover:underline">{c.name}</Link>
                                    {c.qty > 1 && <span className="text-muted-foreground"> ×{c.qty}</span>}
                                </span>
                                <span className="text-xs text-muted-foreground">{c.checked_out_at ?? ''}</span>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
