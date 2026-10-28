import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { FormError } from './form-error';

interface Props {
    id: string;
    label: string;
    value: string;
    onChange: (v: string) => void;
    error?: string;
    required?: boolean;
    autoFocus?: boolean;
    rows?: number;
    placeholder?: string;
}

export function TextareaField({ id, label, value, onChange, error, required, autoFocus, rows = 4, placeholder }: Props) {
    return (
        <div className="space-y-2">
            <Label htmlFor={id}>{label}{required && <span className="text-destructive"> *</span>}</Label>
            <Textarea id={id} value={value} rows={rows} placeholder={placeholder} autoFocus={autoFocus} aria-invalid={!!error}
                onChange={(e) => onChange(e.target.value)} />
            <FormError message={error} />
        </div>
    );
}
