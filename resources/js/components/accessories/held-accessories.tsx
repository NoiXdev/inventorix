import { Link } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export interface HeldAccessory {
    checkout_id: string;
    accessory_id: string;
    name: string;
    checked_out_at: string | null;
}

/** The accessories a user or an asset holds; several units of one accessory are grouped. */
export function HeldAccessories({ accessories, empty }: { accessories: HeldAccessory[]; empty: string }) {
    const grouped = Object.values(accessories.reduce<Record<string, { id: string; name: string; count: number; since: string | null }>>((acc, a) => {
        const g = acc[a.accessory_id] ?? { id: a.accessory_id, name: a.name, count: 0, since: a.checked_out_at };
        g.count += 1;
        if (a.checked_out_at && (!g.since || a.checked_out_at < g.since)) g.since = a.checked_out_at;
        acc[a.accessory_id] = g;
        return acc;
    }, {}));

    return (
        <Card>
            <CardHeader className="pb-2"><CardTitle className="text-base">Accessories ({accessories.length})</CardTitle></CardHeader>
            <CardContent>
                {grouped.length === 0 ? (
                    <p className="text-sm text-muted-foreground">{empty}</p>
                ) : (
                    <ul className="divide-y text-sm">
                        {grouped.map((g) => (
                            <li key={g.id} className="flex items-center justify-between py-2">
                                <span>
                                    <Link href={`/app/accessories/${g.id}`} className="font-medium hover:underline">{g.name}</Link>
                                    {g.count > 1 && <span className="text-muted-foreground"> ×{g.count}</span>}
                                </span>
                                <span className="text-xs text-muted-foreground">{g.since ? `since ${g.since}` : ''}</span>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
