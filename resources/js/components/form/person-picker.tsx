import { useState } from 'react';
import axios from 'axios';
import { ComboboxField } from './combobox-field';
import { TextField } from './text-field';
import { FormError } from './form-error';
import { Button } from '@/components/ui/button';
import {
    Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';

interface Option { value: string; label: string }
interface Props {
    id: string;
    label: string;
    value: string;
    onChange: (v: string) => void;
    options: Option[];
    error?: string;
    createUrl: string;
}

function splitName(query: string): { firstname: string; lastname: string } {
    const trimmed = query.trim();
    const idx = trimmed.indexOf(' ');
    if (idx === -1) return { firstname: trimmed, lastname: '' };
    return { firstname: trimmed.slice(0, idx), lastname: trimmed.slice(idx + 1).trim() };
}

export function PersonPicker({ id, label, value, onChange, options, error, createUrl }: Props) {
    const [items, setItems] = useState<Option[]>(options);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [firstname, setFirstname] = useState('');
    const [lastname, setLastname] = useState('');
    const [email, setEmail] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [formError, setFormError] = useState('');
    const [saving, setSaving] = useState(false);

    const startCreate = (query: string) => {
        const { firstname: f, lastname: l } = splitName(query);
        setFirstname(f);
        setLastname(l);
        setEmail('');
        setErrors({});
        setFormError('');
        setDialogOpen(true);
    };

    const submit = async () => {
        setSaving(true);
        setErrors({});
        setFormError('');
        try {
            const { data } = await axios.post(
                createUrl,
                { firstname, lastname, email },
                { headers: { Accept: 'application/json' } },
            );
            const option = { value: data.id as string, label: data.name as string };
            setItems((prev) => [...prev, option]);
            onChange(option.value);
            setDialogOpen(false);
        } catch (e: unknown) {
            if (axios.isAxiosError(e) && e.response?.status === 422 && e.response.data?.errors) {
                const mapped: Record<string, string> = {};
                for (const [k, msgs] of Object.entries(e.response.data.errors as Record<string, string[]>)) mapped[k] = msgs[0];
                setErrors(mapped);
            } else {
                setFormError('Person konnte nicht angelegt werden. Bitte erneut versuchen.');
            }
        } finally {
            setSaving(false);
        }
    };

    const hasExactMatch = (query: string) =>
        items.some((o) => o.label.toLowerCase() === query.trim().toLowerCase());

    return (
        <>
            <ComboboxField
                id={id}
                label={label}
                value={value}
                onChange={onChange}
                options={items}
                error={error}
                nullable
                footer={(query, close) =>
                    query.trim() !== '' && !hasExactMatch(query) ? (
                        <button
                            type="button"
                            className="block w-full rounded px-2 py-1.5 text-left text-sm text-primary hover:bg-accent"
                            onClick={() => { close(); startCreate(query); }}
                        >
                            „{query.trim()}" als Person anlegen
                        </button>
                    ) : null
                }
            />

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent>
                    <DialogHeader><DialogTitle>Neue Person anlegen</DialogTitle></DialogHeader>
                    <div className="space-y-4">
                        <FormError message={formError} />
                        <TextField id="pp-firstname" label="Vorname" required autoFocus
                            value={firstname} onChange={setFirstname} error={errors.firstname} />
                        <TextField id="pp-lastname" label="Nachname" required
                            value={lastname} onChange={setLastname} error={errors.lastname} />
                        <TextField id="pp-email" label="E-Mail"
                            value={email} onChange={setEmail} error={errors.email} />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => setDialogOpen(false)} disabled={saving}>Abbrechen</Button>
                        <Button type="button" onClick={submit} disabled={saving}>Anlegen</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
