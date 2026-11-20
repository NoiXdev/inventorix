import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/text-field';
import { TextareaField } from '@/components/form/textarea-field';
import { SelectField } from '@/components/form/select-field';
import { SwitchField } from '@/components/form/switch-field';
import { FormCard, FormCardContent, FormCardHeader } from '@/components/form/form-card';

export type Option = { value: string; label: string };

export interface StatusLabelInitial {
    id: string;
    name: string | null;
    type: string;
    notes: string | null;
    show_in_nav: boolean;
    default_label: boolean;
    system: boolean;
}

interface Props {
    initial?: StatusLabelInitial;
    typeOptions: Option[];
    submitUrl: string;
    method: 'post' | 'put';
}

export function StatusLabelForm({ initial, typeOptions, submitUrl, method }: Props) {
    const form = useForm({
        name: initial?.name ?? '',
        type: initial?.type ?? 'deployable',
        notes: initial?.notes ?? '',
        show_in_nav: initial?.show_in_nav ?? false,
        default_label: initial?.default_label ?? false,
    });

    const submit = (e: React.FormEvent) => { e.preventDefault(); form.submit(method, submitUrl); };

    return (
        <form onSubmit={submit} className="space-y-6">
            <FormCard>
                <FormCardHeader title="General"
                    description={initial?.system ? 'Handovers move assets into and out of this status, so it can be renamed but not deleted.' : undefined} />
                <FormCardContent className="md:grid-cols-2">
                    <TextField id="name" label="Name" required autoFocus
                        value={form.data.name} onChange={(v) => form.setData('name', v)} error={form.errors.name} />
                    <SelectField id="type" label="Status type" required options={typeOptions}
                        value={form.data.type} onChange={(v) => form.setData('type', v)} error={form.errors.type} />
                    <SwitchField id="default_label" label="Default for new assets"
                        description="Preselected when creating an asset. Only one status can be the default."
                        checked={form.data.default_label} onChange={(v) => form.setData('default_label', v)} error={form.errors.default_label} />
                    <SwitchField id="show_in_nav" label="Show in navigation"
                        description="List this status under Assets in the sidebar."
                        checked={form.data.show_in_nav} onChange={(v) => form.setData('show_in_nav', v)} error={form.errors.show_in_nav} />
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
