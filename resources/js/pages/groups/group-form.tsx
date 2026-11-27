import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/text-field';
import { TextareaField } from '@/components/form/textarea-field';
import { FormCard, FormCardContent, FormCardHeader } from '@/components/form/form-card';
import { FormError } from '@/components/form/form-error';
import { PermissionMatrix, type CatalogueSection } from '@/components/permissions/permission-matrix';
import type { Group } from './types';

interface Props { initial?: Group; catalogue: CatalogueSection[]; submitUrl: string; method: 'post' | 'put'; }

export function GroupForm({ initial, catalogue, submitUrl, method }: Props) {
    const form = useForm({
        name: initial?.name ?? '',
        notes: initial?.notes ?? '',
        permissions: (initial?.permissions ?? {}) as Record<string, 1 | -1>,
    });
    const submit = (e: React.FormEvent) => { e.preventDefault(); form.submit(method, submitUrl); };
    const permissionError = Object.entries(form.errors).find(([k]) => k === 'permissions' || k.startsWith('permissions.'))?.[1];
    return (
        <form onSubmit={submit} className="space-y-6">
            <FormCard>
                <FormCardHeader title="General" />
                <FormCardContent>
                    <TextField id="name" label="Name" required autoFocus
                        value={form.data.name} onChange={(v) => form.setData('name', v)} error={form.errors.name} />
                    <TextareaField id="notes" label="Notes" rows={3}
                        value={form.data.notes} onChange={(v) => form.setData('notes', v)} error={form.errors.notes} />
                </FormCardContent>
            </FormCard>
            <FormCard>
                <FormCardHeader title="Permissions" description="Members of this group receive everything checked here." />
                <FormCardContent>
                    <div className="md:col-span-2">
                        <PermissionMatrix catalogue={catalogue} mode="grant" value={form.data.permissions}
                            onChange={(v) => form.setData('permissions', v)} />
                        <FormError message={permissionError} />
                    </div>
                </FormCardContent>
            </FormCard>
            <div className="flex gap-2">
                <Button type="submit" disabled={form.processing}>Save</Button>
                <Button type="button" variant="ghost" onClick={() => history.back()}>Cancel</Button>
            </div>
        </form>
    );
}
