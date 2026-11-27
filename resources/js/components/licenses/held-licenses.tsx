import { Link } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export interface HeldLicense {
    seat_id: string;
    license_id: string;
    name: string;
    checked_out_at: string | null;
}

/** The license seats a user or an asset holds. */
export function HeldLicenses({ licenses, empty }: { licenses: HeldLicense[]; empty: string }) {
    return (
        <Card>
            <CardHeader className="pb-2"><CardTitle className="text-base">Licenses ({licenses.length})</CardTitle></CardHeader>
            <CardContent>
                {licenses.length === 0 ? (
                    <p className="text-sm text-muted-foreground">{empty}</p>
                ) : (
                    <ul className="divide-y text-sm">
                        {licenses.map((l) => (
                            <li key={l.seat_id} className="flex items-center justify-between py-2">
                                <Link href={`/app/licenses/${l.license_id}`} className="font-medium hover:underline">{l.name}</Link>
                                <span className="text-xs text-muted-foreground">{l.checked_out_at ? `since ${l.checked_out_at}` : ''}</span>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
