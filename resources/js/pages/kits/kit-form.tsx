import { useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/text-field';
import { TextareaField } from '@/components/form/textarea-field';
import { NumberField } from '@/components/form/number-field';
import { ComboboxField } from '@/components/form/combobox-field';
import { SelectField } from '@/components/form/select-field';
import { FormCard, FormCardContent, FormCardHeader } from '@/components/form/form-card';

export type Option = { value: string; label: string };
export type LineType = 'model' | 'license' | 'accessory' | 'consumable';

export const LINE_TYPES: { value: LineType; label: string }[] = [
    { value: 'model', label: 'Asset model' },
    { value: 'license', label: 'License' },
    { value: 'accessory', label: 'Accessory' },
    { value: 'consumable', label: 'Consumable' },
];

export interface KitOptions {
    modelOptions: Option[];
    licenseOptions: Option[];
    accessoryOptions: Option[];
    consumableOptions: Option[];
}

export interface KitInitial {
    id: string;
    name: string;
    notes: string | null;
    items: { type: LineType; id: string; qty: number }[];
}

interface Line { type: LineType; id: string; qty: string }

interface Props {
    initial?: KitInitial;
    options: KitOptions;
    submitUrl: string;
    method: 'post' | 'put';
}

export function KitForm({ initial, options, submitUrl, method }: Props) {
    const form = useForm({
        name: initial?.name ?? '',
        notes: initial?.notes ?? '',
        items: (initial?.items ?? []).map((l): Line => ({ type: l.type, id: l.id, qty: String(l.qty) })),
    });

    const optionsFor = (type: LineType): Option[] => options[`${type}Options` as const];
    const errors = form.errors as Record<string, string | undefined>;
    const setLine = (index: number, patch: Partial<Line>) =>
        form.setData('items', form.data.items.map((l, i) => (i === index ? { ...l, ...patch } : l)));

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (method === 'put') form.put(submitUrl);
        else form.post(submitUrl);
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <FormCard>
                <FormCardHeader title="General" />
                <FormCardContent>
                    <TextField id="name" label="Name" required autoFocus
                        value={form.data.name} onChange={(v) => form.setData('name', v)} error={form.errors.name} />
                    <TextareaField id="notes" label="Notes" rows={4}
                        value={form.data.notes} onChange={(v) => form.setData('notes', v)} error={form.errors.notes} />
                </FormCardContent>
            </FormCard>

            <FormCard>
                <FormCardHeader title="Contents" description="What is checked out together when this kit is used." />
                <FormCardContent>
                    {form.data.items.length === 0 && <p className="text-sm text-muted-foreground">The kit is empty.</p>}
                    {form.data.items.map((line, i) => (
                        <div key={i} className="grid items-start gap-3 md:grid-cols-[12rem_1fr_7rem_auto]" data-testid="kit-line">
                            <SelectField id={`items_${i}_type`} label="Type" options={LINE_TYPES}
                                value={line.type} onChange={(v) => setLine(i, { type: v as LineType, id: '' })} error={errors[`items.${i}.type`]} />
                            <ComboboxField id={`items_${i}_id`} label="Item" required options={optionsFor(line.type)}
                                value={line.id} onChange={(v) => setLine(i, { id: v })} error={errors[`items.${i}.id`]} />
                            <NumberField id={`items_${i}_qty`} label="Quantity" required step="1" min="1" max="999"
                                value={line.qty} onChange={(v) => setLine(i, { qty: v })} error={errors[`items.${i}.qty`]} />
                            <Button type="button" variant="ghost" size="icon" className="mt-8" aria-label="Remove line"
                                onClick={() => form.setData('items', form.data.items.filter((_, n) => n !== i))}>
                                <Trash2 className="h-4 w-4" />
                            </Button>
                        </div>
                    ))}
                    <div>
                        <Button type="button" variant="outline"
                            onClick={() => form.setData('items', [...form.data.items, { type: 'model', id: '', qty: '1' }])}>
                            <Plus className="h-4 w-4" /> Add line
                        </Button>
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
