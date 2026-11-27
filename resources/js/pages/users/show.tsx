import { Link, router } from '@inertiajs/react';
import { toast } from 'sonner';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useCan } from '@/hooks/use-can';
import { HeldLicenses, type HeldLicense } from '@/components/licenses/held-licenses';
import { StatusBadge, type AssetStatus } from '@/components/assets/status-badge';

interface AssetRow { id: string; name: string | null; asset_tag: string | null; model_name: string | null; serial: string | null; status: AssetStatus | null; location_name: string | null }
interface Props {
    user: { id: string; name: string | null; firstname: string; lastname: string; email: string | null; login_enabled: boolean; assets_count: number };
    assets: AssetRow[];
    /** Group names. */
    groups: string[];
    /** Granted permissions only matter; false entries are skipped. */
    effective: Record<string, boolean>;
    /** Whether the current user may edit or delete this user (UserPolicy::manage). */
    canManage?: boolean;
    /** License seats this user holds; null without licenses.view. */
    licenses?: HeldLicense[] | null;
}

// Group the granted permission keys by section (`assets.view` -> assets); keys without a dot are system flags.
function bySection(effective: Record<string, boolean>): [string, string[]][] {
    const sections = new Map<string, string[]>();
    for (const [key, granted] of Object.entries(effective)) {
        if (!granted) continue;
        const section = key.includes('.') ? key.split('.')[0] : 'system';
        sections.set(section, [...(sections.get(section) ?? []), key]);
    }
    return [...sections.entries()];
}

export default function UserShow({ user, assets, groups = [], effective = {}, canManage = false, licenses = null }: Props) {
    const can = useCan();
    const sections = bySection(effective);
    return (
        <AppLayout title={user.name ?? 'User'} breadcrumbs={[{ label: 'Users', href: '/app/users' }, { label: user.name ?? 'User' }]}>
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">{user.name ?? user.email ?? '—'}</h1>
                    {user.email && <p className="text-sm text-muted-foreground">{user.email}</p>}
                    <Badge className="mt-1" variant={user.login_enabled ? 'default' : 'secondary'}>
                        {user.login_enabled ? 'Login enabled' : 'No login'}
                    </Badge>
                </div>
                <div className="flex gap-2">
                    {can('users.edit') && canManage && <Button asChild variant="outline"><Link href={`/app/users/${user.id}/edit`}>Edit</Link></Button>}
                    {can('users.delete') && canManage && <Button variant="outline" onClick={() => { if (confirm('Delete this user?')) router.delete(`/app/users/${user.id}`, { onError: (errors) => toast.error(errors.user ?? 'Could not delete the user.') }); }}>Delete</Button>}
                </div>
            </div>

            <div className="mb-6 grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader className="pb-2"><CardTitle className="text-base">Groups</CardTitle></CardHeader>
                    <CardContent>
                        {groups.length === 0 ? (
                            <p className="text-sm text-muted-foreground">This user is not in any group.</p>
                        ) : (
                            <div className="flex flex-wrap gap-1">{groups.map((g) => <Badge key={g} variant="secondary">{g}</Badge>)}</div>
                        )}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="pb-2"><CardTitle className="text-base">Effective permissions</CardTitle></CardHeader>
                    <CardContent>
                        {sections.length === 0 ? (
                            <p className="text-sm text-muted-foreground">This user has no permissions.</p>
                        ) : (
                            <dl className="space-y-2">
                                {sections.map(([section, keys]) => (
                                    <div key={section} className="flex flex-wrap items-center gap-1">
                                        <dt className="w-28 text-xs font-medium uppercase tracking-wider text-muted-foreground">{section}</dt>
                                        <dd className="flex flex-wrap gap-1">{keys.map((k) => <Badge key={k} variant="outline">{k}</Badge>)}</dd>
                                    </div>
                                ))}
                            </dl>
                        )}
                    </CardContent>
                </Card>
            </div>

            {licenses && <div className="mb-4"><HeldLicenses licenses={licenses} empty="No license seats are checked out to this user." /></div>}

            <Card>
                <CardHeader className="pb-2"><CardTitle className="text-base">Assigned assets ({user.assets_count})</CardTitle></CardHeader>
                <CardContent>
                    {assets.length === 0 ? (
                        <p className="text-sm text-muted-foreground">No assets are assigned to this user.</p>
                    ) : (
                        <Table>
                            <TableHeader><TableRow><TableHead>Name</TableHead><TableHead>Model</TableHead><TableHead>Serial</TableHead><TableHead>Status</TableHead><TableHead>Location</TableHead></TableRow></TableHeader>
                            <TableBody>
                                {assets.map((a) => (
                                    <TableRow key={a.id} className="cursor-pointer" onClick={() => router.visit(`/app/assets/${a.id}`)}>
                                        <TableCell>{a.name ?? a.asset_tag ?? '—'}</TableCell>
                                        <TableCell>{a.model_name ?? '—'}</TableCell>
                                        <TableCell>{a.serial ?? '—'}</TableCell>
                                        <TableCell><StatusBadge status={a.status} /></TableCell>
                                        <TableCell>{a.location_name ?? '—'}</TableCell>
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
