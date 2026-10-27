import { Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { StateBadge } from '@/components/assets/state-badge';

interface AssetRow { id: string; model_name: string | null; serial_number: string | null; state: string; state_label: string | null; place_name: string | null }
interface Props {
    user: { id: string; name: string | null; firstname: string; lastname: string; email: string | null; login_enabled: boolean; assets_count: number };
    assets: AssetRow[];
}

export default function UserShow({ user, assets }: Props) {
    return (
        <AppLayout title={user.name ?? 'User'} breadcrumbs={[{ label: 'Users', href: '/app/users' }, { label: user.name ?? 'User' }]}>
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">{user.name}</h1>
                    {user.email && <p className="text-sm text-muted-foreground">{user.email}</p>}
                    <Badge className="mt-1" variant={user.login_enabled ? 'default' : 'secondary'}>
                        {user.login_enabled ? 'Login enabled' : 'No login'}
                    </Badge>
                </div>
                <div className="flex gap-2">
                    <Button asChild variant="outline"><Link href={`/app/users/${user.id}/edit`}>Edit</Link></Button>
                    <Button variant="outline" onClick={() => { if (confirm('Delete this user?')) router.delete(`/app/users/${user.id}`); }}>Delete</Button>
                </div>
            </div>

            <Card>
                <CardHeader className="pb-2"><CardTitle className="text-base">Owned assets ({user.assets_count})</CardTitle></CardHeader>
                <CardContent>
                    {assets.length === 0 ? (
                        <p className="text-sm text-muted-foreground">This user owns no assets.</p>
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
