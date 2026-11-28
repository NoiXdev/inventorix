import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/text-field';
import { DateField } from '@/components/form/date-field';
import { ComboboxField } from '@/components/form/combobox-field';
import { TextareaField } from '@/components/form/textarea-field';
import { SwitchField } from '@/components/form/switch-field';
import { MultiSelectField } from '@/components/form/multi-select-field';
import { FormError } from '@/components/form/form-error';
import { FormCard, FormCardContent, FormCardHeader } from '@/components/form/form-card';
import { PermissionMatrix, type CatalogueSection, type EffectivePermission, type PermissionMap } from '@/components/permissions/permission-matrix';

interface Initial {
    id: string; firstname: string; lastname: string; email: string | null; login_enabled: boolean;
    groups?: string[]; permissions?: PermissionMap;
    employee_num?: string | null; jobtitle?: string | null; department_id?: string | null; manager_id?: string | null;
    location_id?: string | null; phone?: string | null; mobile?: string | null;
    start_date?: string | null; end_date?: string | null; notes?: string | null;
}
export interface Option { value: string; label: string }
export interface GroupOption { value: string; label: string; privileged: boolean }
interface Props {
    initial?: Initial; submitUrl: string; method: 'post' | 'put'; isSelf?: boolean;
    groupOptions?: GroupOption[]; catalogue?: CatalogueSection[];
    /** The actor may assign groups and permissions (admin). Without it neither field is sent. */
    canAssign?: boolean;
    /** The actor may touch superuser/admin (superuser). */
    canAssignPrivileged?: boolean;
    effective?: Record<string, EffectivePermission>;
    departmentOptions?: Option[]; locationOptions?: Option[]; managerOptions?: Option[];
}

export function UserForm({ initial, submitUrl, method, isSelf, groupOptions = [], catalogue = [], canAssign = false, canAssignPrivileged = false, effective, departmentOptions = [], locationOptions = [], managerOptions = [] }: Props) {
    const form = useForm({
        firstname: initial?.firstname ?? '',
        lastname: initial?.lastname ?? '',
        email: initial?.email ?? '',
        login_enabled: initial?.login_enabled ?? false,
        employee_num: initial?.employee_num ?? '',
        jobtitle: initial?.jobtitle ?? '',
        department_id: initial?.department_id ?? '',
        manager_id: initial?.manager_id ?? '',
        location_id: initial?.location_id ?? '',
        phone: initial?.phone ?? '',
        mobile: initial?.mobile ?? '',
        start_date: initial?.start_date ?? '',
        end_date: initial?.end_date ?? '',
        notes: initial?.notes ?? '',
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
                <FormCardHeader title="Profile" />
                <FormCardContent className="md:grid-cols-2">
                    <TextField id="employee_num" label="Employee number" value={form.data.employee_num} onChange={(v) => form.setData('employee_num', v)} error={form.errors.employee_num} />
                    <TextField id="jobtitle" label="Job title" value={form.data.jobtitle} onChange={(v) => form.setData('jobtitle', v)} error={form.errors.jobtitle} />
                    <ComboboxField id="department_id" label="Department" nullable options={departmentOptions} value={form.data.department_id} onChange={(v) => form.setData('department_id', v)} error={form.errors.department_id} />
                    <ComboboxField id="manager_id" label="Manager" nullable options={managerOptions} value={form.data.manager_id} onChange={(v) => form.setData('manager_id', v)} error={form.errors.manager_id} />
                    <ComboboxField id="location_id" label="Location" nullable options={locationOptions} value={form.data.location_id} onChange={(v) => form.setData('location_id', v)} error={form.errors.location_id} />
                    <TextField id="phone" label="Phone" value={form.data.phone} onChange={(v) => form.setData('phone', v)} error={form.errors.phone} />
                    <TextField id="mobile" label="Mobile" value={form.data.mobile} onChange={(v) => form.setData('mobile', v)} error={form.errors.mobile} />
                    <DateField id="start_date" label="Start date" value={form.data.start_date} onChange={(v) => form.setData('start_date', v)} error={form.errors.start_date} />
                    <DateField id="end_date" label="End date" value={form.data.end_date} onChange={(v) => form.setData('end_date', v)} error={form.errors.end_date} />
                    <div className="md:col-span-2">
                        <TextareaField id="notes" label="Notes" rows={4} value={form.data.notes} onChange={(v) => form.setData('notes', v)} error={form.errors.notes} />
                    </div>
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
                        <FormCardHeader title="Permissions" description="Inherit uses the groups; Grant and Deny override them for this user. Deny overrides groups, except for users with Admin or Superuser." />
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
