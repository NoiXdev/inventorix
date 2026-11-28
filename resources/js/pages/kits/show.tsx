import { Link } from '@inertiajs/react';
import { PackageCheck } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useCan } from '@/hooks/use-can';
import { LINE_TYPES, type LineType } from './kit-form';

interface Line {
    id: string;
    type: LineType | null;
    item_id: string;
    name: string | null;
    qty: number;
    available: boolean;
    url: string | null;
}

interface Props {
    kit: { id: string; name: string; notes: string | null };
    lines: Line[];
}

export default function ShowKit({ kit, lines }: Props) {
    const can = useCan();
    const typeLabel = (type: LineType | null) => LINE_TYPES.find((t) => t.value === type)?.label ?? '—';

    return (
        <AppLayout title={kit.name} breadcrumbs={[{ label: 'Kits', href: '/app/kits' }, { label: kit.name }]}>
            <div className="mb-6 flex flex-wrap items-center justify-between gap-2">
                <h1 className="text-2xl font-semibold">{kit.name}</h1>
                <div className="flex gap-2">
                    {can('kits.checkout') && (
                        <Button asChild variant="outline"><Link href={`/app/kits/${kit.id}/checkout`}><PackageCheck className="h-4 w-4" /> Check out</Link></Button>
                    )}
                    {can('kits.edit') && <Button asChild><Link href={`/app/kits/${kit.id}/edit`}>Edit</Link></Button>}
                </div>
            </div>

            {kit.notes && <p className="mb-4 whitespace-pre-line text-sm text-muted-foreground">{kit.notes}</p>}

            <Card>
                <CardContent className="py-4">
                    {lines.length === 0 ? (
                        <p className="text-sm text-muted-foreground">The kit is empty.</p>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow><TableHead>Type</TableHead><TableHead>Item</TableHead><TableHead>Quantity</TableHead><TableHead>Status</TableHead></TableRow>
                            </TableHeader>
                            <TableBody>
                                {lines.map((l) => (
                                    <TableRow key={l.id}>
                                        <TableCell>{typeLabel(l.type)}</TableCell>
                                        <TableCell>{l.url ? <Link href={l.url} className="hover:underline">{l.name}</Link> : (l.name ?? '—')}</TableCell>
                                        <TableCell>{l.qty}</TableCell>
                                        <TableCell>
                                            <Badge variant={l.available ? 'secondary' : 'destructive'}>{l.available ? 'Available' : 'Deleted'}</Badge>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>
        </AppLayout>
    );
}
