import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/text-field';
import { TextareaField } from '@/components/form/textarea-field';
import { ComboboxField } from '@/components/form/combobox-field';
import { FormCard, FormCardContent, FormCardHeader } from '@/components/form/form-card';

export type Option = { value: string; label: string };

export interface DepartmentInitial {
    id: string;
    name: string;
    manager_id: string | null;
    location_id: string | null;
    notes: string | null;
}

interface Props {
    initial?: DepartmentInitial;
    managerOptions: Option[];
    locationOptions: Option[];
    submitUrl: string;
    method: 'post' | 'put';
}

export function DepartmentForm({ initial, managerOptions, locationOptions, submitUrl, method }: Props) {
    const form = useForm({
        name: initial?.name ?? '',
        manager_id: initial?.manager_id ?? '',
        location_id: initial?.location_id ?? '',
        notes: initial?.notes ?? '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (method === 'put') form.put(submitUrl);
        else form.post(submitUrl);
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <FormCard>
                <FormCardHeader title="General" />
                <FormCardContent className="md:grid-cols-2">
                    <TextField id="name" label="Name" required autoFocus
                        value={form.data.name} onChange={(v) => form.setData('name', v)} error={form.errors.name} />
                    <ComboboxField id="manager_id" label="Manager" nullable options={managerOptions}
                        value={form.data.manager_id} onChange={(v) => form.setData('manager_id', v)} error={form.errors.manager_id} />
                    <ComboboxField id="location_id" label="Location" nullable options={locationOptions}
                        value={form.data.location_id} onChange={(v) => form.setData('location_id', v)} error={form.errors.location_id} />
                </FormCardContent>
            </FormCard>

            <FormCard>
                <FormCardHeader title="Notes" />
                <FormCardContent>
                    <TextareaField id="notes" label="Notes" rows={5}
                        value={form.data.notes} onChange={(v) => form.setData('notes', v)} error={form.errors.notes} />
                </FormCardContent>
            </FormCard>

            <div className="flex gap-2">
                <Button type="submit" disabled={form.processing}>Save</Button>
                <Button type="button" variant="ghost" onClick={() => history.back()}>Cancel</Button>
            </div>
        </form>
    );
}
