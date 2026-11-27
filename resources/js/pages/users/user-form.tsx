import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/text-field';
import { SwitchField } from '@/components/form/switch-field';
import { MultiSelectField } from '@/components/form/multi-select-field';
import { FormError } from '@/components/form/form-error';
import { FormCard, FormCardContent, FormCardHeader } from '@/components/form/form-card';
import { PermissionMatrix, type CatalogueSection, type EffectivePermission, type PermissionMap } from '@/components/permissions/permission-matrix';

interface Initial {
    id: string; firstname: string; lastname: string; email: string | null; login_enabled: boolean;
    groups?: string[]; permissions?: PermissionMap;
}
export interface GroupOption { value: string; label: string; privileged: boolean }
interface Props {
    initial?: Initial; submitUrl: string; method: 'post' | 'put'; isSelf?: boolean;
    groupOptions?: GroupOption[]; catalogue?: CatalogueSection[];
    /** The actor may assign groups and permissions (admin). Without it neither field is sent. */
    canAssign?: boolean;
    /** The actor may touch superuser/admin (superuser). */
    canAssignPrivileged?: boolean;
    effective?: Record<string, EffectivePermission>;
}

export function UserForm({ initial, submitUrl, method, isSelf, groupOptions = [], catalogue = [], canAssign = false, canAssignPrivileged = false, effective }: Props) {
    const form = useForm({
        firstname: initial?.firstname ?? '',
        lastname: initial?.lastname ?? '',
        email: initial?.email ?? '',
        login_enabled: initial?.login_enabled ?? false,
        // The server prohibits these fields for actors who may not assign, so they are not part of the payload then.
        ...(canAssign ? { groups: initial?.groups ?? [], permissions: (initial?.permissions ?? {}) as PermissionMap } : {}),
    });
    const submit = (e: React.FormEvent) => { e.preventDefault(); form.submit(method, submitUrl); };
    const data = form.data as typeof form.data & { groups?: string[]; permissions?: PermissionMap };
    const errors = form.errors as Record<string, string | undefined>;
    const permissionError = Object.entries(errors).find(([k]) => k === 'permissions' || k.startsWith('permissions.'))?.[1];
    const groupError = Object.entries(errors).find(([k]) => k === 'groups' || k.startsWith('groups.'))?.[1];

    return (
        <form onSubmit={submit} className="space-y-6">
            <FormCard>
                <FormCardHeader title="General" />
                <FormCardContent className="md:grid-cols-2">
                    <TextField id="firstname" label="First name" required autoFocus value={form.data.firstname} onChange={(v) => form.setData('firstname', v)} error={form.errors.firstname} />
                    <TextField id="lastname" label="Last name" required value={form.data.lastname} onChange={(v) => form.setData('lastname', v)} error={form.errors.lastname} />
                    <TextField id="email" label="Email" value={form.data.email} onChange={(v) => form.setData('email', v)} error={form.errors.email} />
                </FormCardContent>
            </FormCard>

            <FormCard>
                <FormCardHeader title="Login access" />
                <FormCardContent>
                    <SwitchField id="login_enabled" label="Login enabled" checked={form.data.login_enabled}
                        onChange={(v) => form.setData('login_enabled', v)} error={form.errors.login_enabled}
                        description={isSelf ? 'You cannot disable your own login.' : 'Without login, this user is only an asset owner.'} />
                </FormCardContent>
            </FormCard>

            {canAssign && (
                <>
                    <FormCard>
                        <FormCardHeader title="Groups" description="The user receives the permissions of every group they belong to." />
                        <FormCardContent>
                            <div>
                                <MultiSelectField id="groups" label="Member of"
                                    options={groupOptions.filter((g) => canAssignPrivileged || !g.privileged)}
                                    value={data.groups ?? []} onChange={(v) => form.setData('groups' as never, v as never)} />
                                <FormError message={groupError} />
                            </div>
                        </FormCardContent>
                    </FormCard>

                    <FormCard>
                        <FormCardHeader title="Permissions" description="Inherit uses the groups; Grant and Deny override them for this user. Deny always wins." />
                        <FormCardContent>
                            <div>
                                <PermissionMatrix catalogue={catalogue} mode="tristate" value={data.permissions ?? {}}
                                    onChange={(v) => form.setData('permissions' as never, v as never)}
                                    effective={effective} hidePrivileged={!canAssignPrivileged} />
                                <FormError message={permissionError} />
                            </div>
                        </FormCardContent>
                    </FormCard>
                </>
            )}

            <div className="flex gap-2">
                <Button type="submit" disabled={form.processing}>Save</Button>
                <Button type="button" variant="ghost" onClick={() => history.back()}>Cancel</Button>
            </div>
        </form>
    );
}
