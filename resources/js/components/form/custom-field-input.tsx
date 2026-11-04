import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { TextField } from './text-field';
import { TextareaField } from './textarea-field';
import { SelectField } from './select-field';
import { DateField } from './date-field';
import { FormError } from './form-error';

/** A custom field as the asset form receives it (AssetCustomFields::definition). */
export interface CustomFieldDefinition {
    db_column: string;
    name: string;
    element: string;
    format: string;
    help_text: string | null;
    required: boolean;
    options: string[];
}

export type CustomFieldValue = string | string[] | null;

interface Props {
    field: CustomFieldDefinition;
    value: CustomFieldValue;
    onChange: (value: CustomFieldValue) => void;
    error?: string;
}

// The server stores datetimes as `Y-m-d H:i:s`; <input type="datetime-local"> speaks `Y-m-d\TH:i`.
const toLocalInput = (v: string) => v.replace(' ', 'T').slice(0, 16);
const fromLocalInput = (v: string) => (v ? `${v.replace('T', ' ')}:00` : '');

export function CustomFieldInput({ field, value, onChange, error }: Props) {
    const id = `custom_${field.db_column}`;
    const text = typeof value === 'string' ? value : '';
    const label = (
        <Label htmlFor={id}>{field.name}{field.required && <span className="text-destructive"> *</span>}</Label>
    );
    const help = field.help_text && <p className="text-sm text-muted-foreground">{field.help_text}</p>;

    switch (field.element) {
        case 'textarea':
        case 'markdown_text':
            return (
                <div className="space-y-2 md:col-span-2">
                    <TextareaField id={id} label={field.name} required={field.required} value={text} onChange={onChange} error={error} />
                    {help}
                </div>
            );
        case 'select':
            return (
                <div className="space-y-2">
                    <SelectField id={id} label={field.name} required={field.required} nullable={!field.required}
                        options={field.options.map((o) => ({ value: o, label: o }))} value={text} onChange={onChange} error={error} />
                    {help}
                </div>
            );
        case 'date_picker':
            return (
                <div className="space-y-2">
                    <DateField id={id} label={field.name} required={field.required} value={text} onChange={onChange} error={error} />
                    {help}
                </div>
            );
        case 'datetime_picker':
            return (
                <div className="space-y-2">
                    {label}
                    <Input id={id} type="datetime-local" value={toLocalInput(text)} aria-invalid={!!error}
                        onChange={(e) => onChange(fromLocalInput(e.target.value))} />
                    {help}
                    <FormError message={error} />
                </div>
            );
        case 'radio':
            return (
                <fieldset className="space-y-2">
                    <legend className="text-sm font-medium">{field.name}{field.required && <span className="text-destructive"> *</span>}</legend>
                    <div className="flex flex-wrap gap-4">
                        {field.options.map((o) => (
                            <label key={o} className="flex items-center gap-2 text-sm">
                                <input type="radio" name={id} value={o} checked={text === o} onChange={() => onChange(o)} />
                                {o}
                            </label>
                        ))}
                    </div>
                    {help}
                    <FormError message={error} />
                </fieldset>
            );
        case 'checkbox': {
            const selected = Array.isArray(value) ? value : [];
            return (
                <fieldset className="space-y-2">
                    <legend className="text-sm font-medium">{field.name}{field.required && <span className="text-destructive"> *</span>}</legend>
                    <div className="flex flex-wrap gap-4">
                        {field.options.map((o) => (
                            <label key={o} className="flex items-center gap-2 text-sm">
                                <Checkbox checked={selected.includes(o)}
                                    onCheckedChange={(on) => onChange(on ? [...selected, o] : selected.filter((s) => s !== o))} />
                                {o}
                            </label>
                        ))}
                    </div>
                    {help}
                    <FormError message={error} />
                </fieldset>
            );
        }
        default:
            return (
                <div className="space-y-2">
                    <TextField id={id} label={field.name} required={field.required} value={text} onChange={onChange} error={error} />
                    {help}
                </div>
            );
    }
}
