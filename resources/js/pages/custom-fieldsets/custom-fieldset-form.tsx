import { useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Switch } from '@/components/ui/switch';
import { TextField } from '@/components/form/text-field';
import { SelectField } from '@/components/form/select-field';
import { FormError } from '@/components/form/form-error';
import { FormCard, FormCardContent, FormCardHeader } from '@/components/form/form-card';

export interface AvailableField { id: string; name: string; element_label: string | null; auto_add_to_fieldsets: boolean }
export interface SelectedField { id: string; required: boolean }
export interface CustomFieldsetInitial { id: string; name: string; fields: SelectedField[] }

interface Props {
    initial?: CustomFieldsetInitial;
    initialFields?: SelectedField[];
    availableFields: AvailableField[];
    submitUrl: string;
    method: 'post' | 'put';
}

export function CustomFieldsetForm({ initial, initialFields = [], availableFields, submitUrl, method }: Props) {
    const form = useForm({
        name: initial?.name ?? '',
        fields: initial?.fields ?? initialFields,
    });
    const byId = new Map(availableFields.map((f) => [f.id, f]));
    const unselected = availableFields
        .filter((f) => !form.data.fields.some((s) => s.id === f.id))
        .map((f) => ({ value: f.id, label: f.name }));

    const setFields = (fields: SelectedField[]) => form.setData('fields', fields);
    const move = (index: number, by: -1 | 1) => {
        const next = [...form.data.fields];
        [next[index], next[index + by]] = [next[index + by], next[index]];
        setFields(next);
    };
    // Per-row errors arrive as fields.N.id / fields.N.required; surface them all in one place.
    const fieldErrors = Object.entries(form.errors).filter(([key]) => key.startsWith('fields')).map(([, message]) => message);

    const submit = (e: React.FormEvent) => { e.preventDefault(); form.submit(method, submitUrl); };

    return (
        <form onSubmit={submit} className="space-y-6">
            <FormCard>
                <FormCardHeader title="General" />
                <FormCardContent className="md:grid-cols-2">
                    <TextField id="name" label="Name" required autoFocus
                        value={form.data.name} onChange={(v) => form.setData('name', v)} error={form.errors.name} />
                </FormCardContent>
            </FormCard>

            <FormCard>
                <FormCardHeader title="Fields" description="The custom fields in this set, in the order they are shown." />
                <FormCardContent>
                    {form.data.fields.length > 0 ? (
                        <ol className="divide-y rounded-md border">
                            {form.data.fields.map((selected, i) => {
                                const field = byId.get(selected.id);
                                return (
                                    <li key={selected.id} className="flex items-center gap-3 px-3 py-2">
                                        <span className="w-6 text-sm text-muted-foreground">{i + 1}.</span>
                                        <span className="min-w-0 flex-1 truncate">{field?.name ?? 'Unknown field'}</span>
                                        {field?.element_label && <Badge variant="secondary">{field.element_label}</Badge>}
                                        <label className="flex items-center gap-2 text-sm">
                                            <Switch checked={selected.required} aria-label={`${field?.name} required`}
                                                onCheckedChange={(v) => setFields(form.data.fields.map((f, j) => (j === i ? { ...f, required: v } : f)))} />
                                            Required
                                        </label>
                                        <div className="flex gap-1">
                                            <Button type="button" variant="ghost" size="icon" disabled={i === 0} onClick={() => move(i, -1)} aria-label="Move up"><ArrowUp className="h-4 w-4" /></Button>
                                            <Button type="button" variant="ghost" size="icon" disabled={i === form.data.fields.length - 1} onClick={() => move(i, 1)} aria-label="Move down"><ArrowDown className="h-4 w-4" /></Button>
                                            <Button type="button" variant="ghost" size="icon" onClick={() => setFields(form.data.fields.filter((_, j) => j !== i))} aria-label={`Remove ${field?.name}`}><X className="h-4 w-4" /></Button>
                                        </div>
                                    </li>
                                );
                            })}
                        </ol>
                    ) : (
                        <p className="text-sm text-muted-foreground">No fields yet.</p>
                    )}
                    {unselected.length > 0 && (
                        <div className="md:w-1/2">
                            <SelectField id="add_field" label="Add field" options={unselected} value="" placeholder="Choose a field…"
                                onChange={(id) => setFields([...form.data.fields, { id, required: false }])} />
                        </div>
                    )}
                    {fieldErrors.map((message) => <FormError key={message} message={message} />)}
                </FormCardContent>
            </FormCard>

            <div className="flex gap-2">
                <Button type="submit" disabled={form.processing}>Save</Button>
                <Button type="button" variant="ghost" onClick={() => history.back()}>Cancel</Button>
            </div>
        </form>
    );
}
