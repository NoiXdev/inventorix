import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/text-field';
import { NumberField } from '@/components/form/number-field';
import { SelectField } from '@/components/form/select-field';
import { FormCard, FormCardContent, FormCardHeader } from '@/components/form/form-card';
import { MONTH_OPTIONS, type Depreciation, type Option } from './types';

interface Props { initial?: Depreciation; typeOptions: Option[]; submitUrl: string; method: 'post' | 'put'; }

export function DepreciationForm({ initial, typeOptions, submitUrl, method }: Props) {
    const form = useForm({
        name: initial?.name ?? '',
        months: initial ? String(initial.months) : '',
        depreciation_min: initial?.depreciation_min ?? '0',
        fiscal_year_start_month: initial ? String(initial.fiscal_year_start_month) : '1',
        rate_multiplier: initial?.rate_multiplier ?? '1',
        depreciation_type: initial?.depreciation_type ?? 'amount',
    });
    const isPercentage = form.data.depreciation_type === 'percentage';
    const submit = (e: React.FormEvent) => { e.preventDefault(); form.submit(method, submitUrl); };
    return (
        <form onSubmit={submit} className="space-y-6">
            <FormCard>
                <FormCardHeader title="General" />
                <FormCardContent className="md:grid-cols-2">
                    <TextField id="name" label="Name" required autoFocus
                        value={form.data.name} onChange={(v) => form.setData('name', v)} error={form.errors.name} />
                    <NumberField id="months" label="Useful life (months)" required step="1" min="1"
                        value={form.data.months} onChange={(v) => form.setData('months', v)} error={form.errors.months} />
                    <SelectField id="fiscal_year_start_month" label="Fiscal year starts in" required options={MONTH_OPTIONS}
                        value={form.data.fiscal_year_start_month} onChange={(v) => form.setData('fiscal_year_start_month', v)} error={form.errors.fiscal_year_start_month} />
                    <NumberField id="rate_multiplier" label="Rate multiplier" required min="0.01"
                        value={form.data.rate_multiplier} onChange={(v) => form.setData('rate_multiplier', v)} error={form.errors.rate_multiplier} />
                </FormCardContent>
            </FormCard>
            <FormCard>
                <FormCardHeader title="Floor value" description="The value an asset is never depreciated below." />
                <FormCardContent className="md:grid-cols-2">
                    <SelectField id="depreciation_type" label="Floor value type" required options={typeOptions}
                        value={form.data.depreciation_type} onChange={(v) => form.setData('depreciation_type', v)} error={form.errors.depreciation_type} />
                    <NumberField id="depreciation_min" label={isPercentage ? 'Floor value (%)' : 'Floor value'} required min="0" max={isPercentage ? '100' : undefined}
                        value={form.data.depreciation_min} onChange={(v) => form.setData('depreciation_min', v)} error={form.errors.depreciation_min} />
                </FormCardContent>
            </FormCard>
            <div className="flex gap-2">
                <Button type="submit" disabled={form.processing}>Save</Button>
                <Button type="button" variant="ghost" onClick={() => history.back()}>Cancel</Button>
            </div>
        </form>
    );
}
