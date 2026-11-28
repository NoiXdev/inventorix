import { Link, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { SelectField } from '@/components/form/select-field';
import { ComboboxField } from '@/components/form/combobox-field';
import { UserPicker } from '@/components/form/user-picker';
import { FormError } from '@/components/form/form-error';
import { HandedConsumables, type HandedConsumable } from '@/components/consumables/handed-consumables';
import type { HeldLicense } from '@/components/licenses/held-licenses';
import type { HeldAccessory } from '@/components/accessories/held-accessories';

type Option = { value: string; label: string };
type Action = 'checkin' | 'transfer' | 'keep';
type ItemType = 'asset' | 'license_seat' | 'accessory_checkout';
type Module = 'assets' | 'licenses' | 'accessories';
type Ability = { checkin: boolean; transfer: boolean };

export interface OffboardAsset {
    id: string; name: string; asset_tag: string | null; model_name: string | null; serial: string | null;
    status_name: string | null; location_name: string | null;
}

interface Props {
    user: { id: string; name: string | null; email: string | null; login_enabled: boolean; department_name: string | null };
    assets: OffboardAsset[];
    /** Null when the viewer may not see them. */
    licenses: HeldLicense[] | null;
    accessories: HeldAccessory[] | null;
    consumables: HandedConsumable[] | null;
    /** What the viewer may do per module; the server checks it again. */
    abilities: Record<Module, Ability>;
    canDisableLogin: boolean;
    transferOptions: Option[];
    userCreateUrl: string;
    statusOptions: Option[];
    locationOptions: Option[];
    checkinStatusId: string;
}

interface Row { key: string; type: ItemType; id: string; module: Module; name: string; cells: React.ReactNode[] }

const ACTION_LABELS: Record<Action, string> = { checkin: 'Check in', transfer: 'Transfer', keep: 'Keep' };

/** The actions the viewer may choose for a row of this module; Keep is always possible. */
const allowed = (ability: Ability): Action[] => [
    ...(ability.checkin ? (['checkin'] as const) : []),
    ...(ability.transfer ? (['transfer'] as const) : []),
    'keep',
];

export default function UserOffboard({
    user, assets, licenses, accessories, consumables, abilities, canDisableLogin,
    transferOptions, userCreateUrl, statusOptions, locationOptions, checkinStatusId,
}: Props) {
    const name = user.name ?? user.email ?? 'User';
    const sections: { title: string; module: Module; empty: string; headers: string[]; rows: Row[] }[] = [
        {
            title: 'Assets', module: 'assets', empty: 'No assets are checked out to this user.', headers: ['Asset', 'Model', 'Serial', 'Status'],
            rows: assets.map((a) => ({ key: `asset:${a.id}`, type: 'asset', id: a.id, module: 'assets', name: a.name,
                cells: [a.name, a.model_name ?? '—', a.serial ?? '—', a.status_name ?? '—'] })),
        },
        ...(licenses ? [{
            title: 'Licenses', module: 'licenses' as const, empty: 'No license seats are checked out to this user.', headers: ['License', 'Checked out'],
            rows: licenses.map((l): Row => ({ key: `license_seat:${l.seat_id}`, type: 'license_seat', id: l.seat_id, module: 'licenses', name: l.name,
                cells: [l.name, l.checked_out_at ?? '—'] })),
        }] : []),
        ...(accessories ? [{
            title: 'Accessories', module: 'accessories' as const, empty: 'No accessories are checked out to this user.', headers: ['Accessory', 'Checked out'],
            rows: accessories.map((c): Row => ({ key: `accessory_checkout:${c.checkout_id}`, type: 'accessory_checkout', id: c.checkout_id, module: 'accessories', name: c.name,
                cells: [c.name, c.checked_out_at ?? '—'] })),
        }] : []),
    ];
    const allRows = sections.flatMap((s) => s.rows);

    const form = useForm({
        actions: Object.fromEntries(allRows.map((r) => [r.key, allowed(abilities[r.module])[0]])) as Record<string, Action>,
        transfer_to: '',
        status_id: statusOptions.some((s) => s.value === checkinStatusId) ? checkinStatusId : '',
        location_id: '',
        note: '',
        disable_login: false,
    });
    const actions = form.data.actions;
    const setAction = (key: string, action: Action) => form.setData('actions', { ...actions, [key]: action });
    const setAll = (rows: Row[], action: Action) => form.setData('actions', {
        ...actions,
        ...Object.fromEntries(rows.filter((r) => allowed(abilities[r.module]).includes(action)).map((r) => [r.key, action])),
    });

    const anyTransfer = allRows.some((r) => actions[r.key] === 'transfer');
    const anyAssetCheckin = allRows.some((r) => r.type === 'asset' && actions[r.key] === 'checkin');
    const chosen = allRows.filter((r) => actions[r.key] !== 'keep');

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.transform((d) => ({
            items: allRows.filter((r) => d.actions[r.key] !== 'keep').map((r) => ({ type: r.type, id: r.id, action: d.actions[r.key] })),
            transfer_to: anyTransfer ? d.transfer_to : null,
            status_id: d.status_id || null,
            location_id: d.location_id || null,
            note: d.note,
            disable_login: canDisableLogin && d.disable_login,
        }));
        form.post(`/app/users/${user.id}/offboard`);
    };

    const errors = form.errors as Record<string, string | undefined>;

    return (
        <AppLayout title={`Offboarding ${name}`} breadcrumbs={[{ label: 'Users', href: '/app/users' }, { label: name, href: `/app/users/${user.id}` }, { label: 'Offboarding' }]}>
            <div className="mb-6">
                <h1 className="text-2xl font-semibold">Offboarding {name}</h1>
                <p className="text-sm text-muted-foreground">{user.department_name ? `Department: ${user.department_name}` : 'No department'}</p>
            </div>

            <form onSubmit={submit} className="grid gap-6 lg:grid-cols-[3fr_2fr]">
                <div className="space-y-4">
                    <FormError message={errors.items} />
                    {sections.map((section) => {
                        const choices = allowed(abilities[section.module]);
                        return (
                            <Card key={section.module}>
                                <CardHeader className="flex flex-row items-center justify-between gap-2 pb-2">
                                    <CardTitle className="text-base">{section.title} ({section.rows.length})</CardTitle>
                                    {section.rows.length > 0 && (
                                        <div className="flex flex-wrap gap-1">
                                            {choices.map((a) => (
                                                <Button key={a} type="button" size="sm" variant="outline" onClick={() => setAll(section.rows, a)}>
                                                    Set all to {ACTION_LABELS[a].toLowerCase()}
                                                </Button>
                                            ))}
                                        </div>
                                    )}
                                </CardHeader>
                                <CardContent>
                                    {section.rows.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">{section.empty}</p>
                                    ) : (
                                        <Table>
                                            <TableHeader>
                                                <TableRow>{section.headers.map((h) => <TableHead key={h}>{h}</TableHead>)}<TableHead>Action</TableHead></TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {section.rows.map((r) => (
                                                    <TableRow key={r.key}>
                                                        {r.cells.map((c, i) => <TableCell key={i}>{c}</TableCell>)}
                                                        <TableCell>
                                                            <div role="radiogroup" aria-label={`Action for ${r.name}`} className="flex flex-wrap gap-3 text-sm">
                                                                {choices.map((a) => (
                                                                    <label key={a} className="flex items-center gap-1">
                                                                        <input type="radio" name={`action-${r.key}`} value={a}
                                                                            checked={actions[r.key] === a} onChange={() => setAction(r.key, a)} />
                                                                        {ACTION_LABELS[a]}
                                                                    </label>
                                                                ))}
                                                            </div>
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </Table>
                                    )}
                                </CardContent>
                            </Card>
                        );
                    })}
                    {consumables && <HandedConsumables consumables={consumables} />}
                </div>

                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle>Offboard</CardTitle>
                        <CardDescription>Everything is done together. If one item fails, nothing is changed.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {anyTransfer && (
                            <UserPicker id="transfer_to" label="Transfer to" options={transferOptions} createUrl={userCreateUrl}
                                value={form.data.transfer_to} onChange={(v) => form.setData('transfer_to', v)} error={errors.transfer_to} />
                        )}
                        {anyAssetCheckin && (
                            <>
                                <div>
                                    <SelectField id="status_id" label="Status of checked-in assets" nullable options={statusOptions}
                                        value={form.data.status_id} onChange={(v) => form.setData('status_id', v)} error={errors.status_id} />
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {statusOptions.some((s) => s.value === checkinStatusId) && 'Pre-set to the default check-in status. '}
                                        Empty: each asset keeps its current status.
                                    </p>
                                </div>
                                <div>
                                    <ComboboxField id="location_id" label="Location of checked-in assets" nullable options={locationOptions}
                                        value={form.data.location_id} onChange={(v) => form.setData('location_id', v)} error={errors.location_id} />
                                    <p className="mt-1 text-xs text-muted-foreground">Empty: each asset goes to its default location, else stays where it is.</p>
                                </div>
                            </>
                        )}
                        <div className="space-y-2">
                            <Label htmlFor="note">Note</Label>
                            <Textarea id="note" value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} />
                            <p className="text-xs text-muted-foreground">Recorded on every item as “Offboarding of {name}”, followed by this note.</p>
                            <FormError message={errors.note} />
                        </div>
                        {canDisableLogin && (
                            <div className="space-y-1">
                                <div className="flex items-center gap-2">
                                    <Checkbox id="disable_login" checked={form.data.disable_login}
                                        onCheckedChange={(v) => form.setData('disable_login', v === true)} />
                                    <Label htmlFor="disable_login">Disable login</Label>
                                </div>
                                <FormError message={errors.disable_login} />
                            </div>
                        )}
                        <div className="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="ghost" asChild><Link href={`/app/users/${user.id}`}>Cancel</Link></Button>
                            <Button type="submit" disabled={form.processing || (chosen.length === 0 && !form.data.disable_login)}>
                                Offboard{chosen.length > 0 ? ` (${chosen.length})` : ''}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </form>
        </AppLayout>
    );
}
