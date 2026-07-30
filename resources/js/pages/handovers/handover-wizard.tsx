import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { TextField } from '@/components/form/text-field';
import { SelectField } from '@/components/form/select-field';
import { FormError } from '@/components/form/form-error';
import { SignaturePad } from '@/components/handover/signature-pad';
import { AssetPicker, type AssetOption } from './asset-picker';

type Option = { value: string; label: string };
type TypeOption = { value: string; label: string; allowedStates: string[]; assignsOwner: boolean };
type PersonOption = { value: string; label: string; email: string | null };

export interface HandoverWizardProps {
    typeOptions: TypeOption[]; recipientKindOptions: Option[]; personOptions: PersonOption[];
    assetOptions: AssetOption[]; defaultTerms: string;
}

const STEP_OF_FIELD: Record<string, number> = {
    type: 1, asset_ids: 1, recipient_kind: 2, recipient_person_id: 2, recipient_name: 2, recipient_email: 2,
    accessories: 3, condition_notes: 3, terms_text: 3, signature_png: 4,
};

export function HandoverWizard({ typeOptions, recipientKindOptions, personOptions, assetOptions, defaultTerms }: HandoverWizardProps) {
    const [step, setStep] = useState(1);
    const form = useForm({
        type: typeOptions[0]?.value ?? '', asset_ids: [] as string[],
        recipient_kind: recipientKindOptions[0]?.value ?? '', recipient_person_id: '',
        recipient_name: '', recipient_email: '', accessories: '', condition_notes: '',
        terms_text: defaultTerms, signature_png: '',
    });

    const selectedType = typeOptions.find((t) => t.value === form.data.type);
    const eligibleAssets = assetOptions.filter((a) => selectedType?.allowedStates.includes(a.state));
    const isInternal = form.data.recipient_kind === 'internal';

    const setType = (v: string) => {
        const t = typeOptions.find((o) => o.value === v);
        // drop now-ineligible picks
        const stillOk = form.data.asset_ids.filter((id) => {
            const a = assetOptions.find((o) => o.value === id);
            return a && t?.allowedStates.includes(a.state);
        });
        form.setData((d) => ({ ...d, type: v, asset_ids: stillOk }));
    };

    const pickPerson = (v: string) => {
        const p = personOptions.find((o) => o.value === v);
        form.setData((d) => ({ ...d, recipient_person_id: v, recipient_name: p?.label ?? d.recipient_name, recipient_email: p?.email ?? d.recipient_email }));
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/app/handovers', {
            onError: (errors) => {
                const first = Object.keys(errors)[0];
                if (first && STEP_OF_FIELD[first]) setStep(STEP_OF_FIELD[first]);
            },
        });
    };

    const canNext =
        (step === 1 && form.data.type !== '' && form.data.asset_ids.length > 0) ||
        (step === 2 && form.data.recipient_name !== '' && (!isInternal || form.data.recipient_person_id !== '')) ||
        step === 3;

    return (
        <form onSubmit={submit} className="max-w-2xl space-y-6">
            <ol className="flex gap-2 text-sm">
                {['Type & assets', 'Recipient', 'Details', 'Review & sign'].map((label, i) => (
                    <li key={label} className={`rounded px-2 py-1 ${step === i + 1 ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'}`}>{i + 1}. {label}</li>
                ))}
            </ol>

            {step === 1 && (
                <div className="space-y-4">
                    <SelectField id="type" label="Type" required options={typeOptions.map((t) => ({ value: t.value, label: t.label }))}
                        value={form.data.type} onChange={setType} error={form.errors.type} />
                    <div className="space-y-1">
                        <Label>Assets</Label>
                        <AssetPicker options={eligibleAssets} selected={form.data.asset_ids} onChange={(ids) => form.setData('asset_ids', ids)} />
                        <FormError message={form.errors.asset_ids} />
                    </div>
                </div>
            )}

            {step === 2 && (
                <div className="space-y-4">
                    <h2 className="text-lg font-medium">Recipient</h2>
                    <SelectField id="recipient_kind" label="Recipient kind" required options={recipientKindOptions}
                        value={form.data.recipient_kind} onChange={(v) => form.setData('recipient_kind', v)} error={form.errors.recipient_kind} />
                    {isInternal && (
                        <SelectField id="recipient_person_id" label="Person" required options={personOptions.map((p) => ({ value: p.value, label: p.label }))}
                            value={form.data.recipient_person_id} onChange={pickPerson} error={form.errors.recipient_person_id} />
                    )}
                    <TextField id="recipient_name" label="Recipient name" required
                        value={form.data.recipient_name} onChange={(v) => form.setData('recipient_name', v)} error={form.errors.recipient_name} />
                    <TextField id="recipient_email" label="Recipient email"
                        value={form.data.recipient_email} onChange={(v) => form.setData('recipient_email', v)} error={form.errors.recipient_email} />
                </div>
            )}

            {step === 3 && (
                <div className="space-y-4">
                    <h2 className="text-lg font-medium">Details</h2>
                    <div className="space-y-2"><Label htmlFor="accessories">Accessories</Label>
                        <Textarea id="accessories" value={form.data.accessories} onChange={(e) => form.setData('accessories', e.target.value)} /></div>
                    <div className="space-y-2"><Label htmlFor="condition_notes">Condition notes</Label>
                        <Textarea id="condition_notes" value={form.data.condition_notes} onChange={(e) => form.setData('condition_notes', e.target.value)} /></div>
                    <div className="space-y-2"><Label htmlFor="terms_text">Terms</Label>
                        <Textarea id="terms_text" rows={6} value={form.data.terms_text} onChange={(e) => form.setData('terms_text', e.target.value)} />
                        <FormError message={form.errors.terms_text} /></div>
                </div>
            )}

            {step === 4 && (
                <div className="space-y-4">
                    <h2 className="text-lg font-medium">Review &amp; sign</h2>
                    <div className="rounded-md border p-3 text-sm text-muted-foreground">
                        <div><strong className="text-foreground">{selectedType?.label}</strong> to <strong className="text-foreground">{form.data.recipient_name || '—'}</strong></div>
                        <div>{form.data.asset_ids.length} asset(s)</div>
                        {selectedType?.assignsOwner && (
                            <div className="mt-1 text-xs text-muted-foreground">The recipient will be set as the owner of these assets.</div>
                        )}
                    </div>
                    <div className="space-y-1">
                        <Label>Signature</Label>
                        <SignaturePad value={form.data.signature_png} onChange={(b64) => form.setData('signature_png', b64)} />
                        <FormError message={form.errors.signature_png} />
                    </div>
                </div>
            )}

            <div className="flex justify-between">
                <Button type="button" variant="ghost" onClick={() => setStep((s) => Math.max(1, s - 1))} disabled={step === 1}>Back</Button>
                {step < 4
                    ? <Button type="button" onClick={() => setStep((s) => s + 1)} disabled={!canNext}>Next</Button>
                    : <Button type="submit" disabled={form.processing || form.data.signature_png === ''}>Create handover</Button>}
            </div>
        </form>
    );
}
