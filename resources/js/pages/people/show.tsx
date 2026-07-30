import { Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { StateBadge } from '@/components/assets/state-badge';

interface AssetRow { id: string; model_name: string | null; serial_number: string | null; state: string; state_label: string | null; place_name: string | null }
interface Props {
    person: { id: string; name: string; firstname: string; lastname: string; email: string | null; assets_count: number };
    assets: AssetRow[];
}

export default function PersonShow({ person, assets }: Props) {
    return (
        <AppLayout title={person.name} breadcrumbs={[{ label: 'People', href: '/app/people' }, { label: person.name }]}>
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">{person.name}</h1>
                    {person.email && <p className="text-sm text-muted-foreground">{person.email}</p>}
                </div>
                <div className="flex gap-2">
                    <Button asChild variant="outline"><Link href={`/app/people/${person.id}/edit`}>Edit</Link></Button>
                    <Button variant="outline" onClick={() => { if (confirm('Delete this person?')) router.delete(`/app/people/${person.id}`); }}>Delete</Button>
                </div>
            </div>

            <Card>
                <CardHeader className="pb-2"><CardTitle className="text-base">Owned assets ({person.assets_count})</CardTitle></CardHeader>
                <CardContent>
                    {assets.length === 0 ? (
                        <p className="text-sm text-muted-foreground">This person owns no assets.</p>
                    ) : (
                        <Table>
                            <TableHeader><TableRow><TableHead>Model</TableHead><TableHead>Serial</TableHead><TableHead>State</TableHead><TableHead>Place</TableHead></TableRow></TableHeader>
                            <TableBody>
                                {assets.map((a) => (
                                    <TableRow key={a.id} className="cursor-pointer" onClick={() => router.visit(`/app/assets/${a.id}`)}>
                                        <TableCell>{a.model_name ?? '—'}</TableCell>
                                        <TableCell>{a.serial_number ?? '—'}</TableCell>
                                        <TableCell><StateBadge state={a.state} label={a.state_label} /></TableCell>
                                        <TableCell>{a.place_name ?? '—'}</TableCell>
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
