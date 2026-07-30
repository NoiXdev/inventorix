import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { FormError } from './form-error';

interface Props {
    id: string;
    label: string;
    value: string;
    onChange: (v: string) => void;
    options: { value: string; label: string }[];
    error?: string;
    required?: boolean;
    placeholder?: string;
    nullable?: boolean;
}

const NONE = '__none__';

export function SelectField({ id, label, value, onChange, options, error, required, placeholder, nullable }: Props) {
    const current = nullable && value === '' ? NONE : value;
    return (
        <div className="space-y-2">
            <Label htmlFor={id}>{label}{required && <span className="text-destructive"> *</span>}</Label>
            <Select value={current} onValueChange={(v) => onChange(v === NONE ? '' : v)}>
                <SelectTrigger id={id} aria-invalid={!!error}>
                    <SelectValue placeholder={placeholder ?? `Select ${label.toLowerCase()}…`} />
                </SelectTrigger>
                <SelectContent>
                    {nullable && <SelectItem value={NONE}>—</SelectItem>}
                    {options.map((o) => <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>)}
                </SelectContent>
            </Select>
            <FormError message={error} />
        </div>
    );
}
