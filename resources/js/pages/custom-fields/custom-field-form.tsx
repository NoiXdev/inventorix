import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/text-field';
import { TextareaField } from '@/components/form/textarea-field';
import { SelectField } from '@/components/form/select-field';
import { SwitchField } from '@/components/form/switch-field';
import { FormCard, FormCardContent, FormCardHeader } from '@/components/form/form-card';

export type Option = { value: string; label: string };

export interface CustomFieldOptions {
    elementOptions: Option[];
    formatOptions: Option[];
    textOnlyFormats: string[];
}

export interface CustomFieldInitial {
    id: string;
    name: string;
    db_column: string | null;
    element: string;
    format: string;
    custom_regex: string | null;
    field_values: string | null;
    help_text: string | null;
    field_encrypted: boolean;
    show_in_email: boolean;
    show_in_requestable_list: boolean;
    is_unique: boolean;
    display_in_user_view: boolean;
    auto_add_to_fieldsets: boolean;
    show_in_listview: boolean;
    display_checkin: boolean;
    display_audit: boolean;
}

type Flag = 'field_encrypted' | 'show_in_email' | 'show_in_requestable_list' | 'is_unique' | 'display_in_user_view'
    | 'auto_add_to_fieldsets' | 'show_in_listview' | 'display_checkin' | 'display_audit';

const CHOICE_ELEMENTS = ['select', 'radio', 'checkbox'];

const BEHAVIOUR: [Flag, string, string][] = [
    ['is_unique', 'Unique', 'No two items may share the same value.'],
    ['field_encrypted', 'Encrypted', 'Store the value encrypted.'],
    ['auto_add_to_fieldsets', 'Add to new fieldsets', 'Preselect this field whenever a fieldset is created.'],
];

const DISPLAY: [Flag, string, string][] = [
    ['show_in_listview', 'Show in list view', 'Offer the field as a column in tables.'],
    ['show_in_email', 'Show in emails', 'Include the value in checkout and acceptance emails.'],
    ['show_in_requestable_list', 'Show in requestable list', 'Show the value to users browsing requestable items.'],
    ['display_in_user_view', 'Show to users', 'Show the value to the user an item is assigned to.'],
    ['display_checkin', 'Show on check-in', 'Show the field on the check-in form.'],
    ['display_audit', 'Show on audit', 'Show the field on the audit form.'],
];

interface Props extends CustomFieldOptions { initial?: CustomFieldInitial; submitUrl: string; method: 'post' | 'put'; }

export function CustomFieldForm({ initial, elementOptions, formatOptions, textOnlyFormats, submitUrl, method }: Props) {
    const form = useForm({
        name: initial?.name ?? '',
        element: initial?.element ?? 'text',
        format: initial?.format ?? 'ANY',
        custom_regex: initial?.custom_regex ?? '',
        field_values: initial?.field_values ?? '',
        help_text: initial?.help_text ?? '',
        field_encrypted: initial?.field_encrypted ?? false,
        show_in_email: initial?.show_in_email ?? false,
        show_in_requestable_list: initial?.show_in_requestable_list ?? false,
        is_unique: initial?.is_unique ?? false,
        display_in_user_view: initial?.display_in_user_view ?? false,
        auto_add_to_fieldsets: initial?.auto_add_to_fieldsets ?? false,
        show_in_listview: initial?.show_in_listview ?? false,
        display_checkin: initial?.display_checkin ?? false,
        display_audit: initial?.display_audit ?? false,
    });
    const isText = form.data.element === 'text';
    const hasChoices = CHOICE_ELEMENTS.includes(form.data.element);
    const formats = isText ? formatOptions : formatOptions.filter((o) => !textOnlyFormats.includes(o.value));

    // Leaving the text element drops a text-only format the server would reject.
    const setElement = (element: string) => form.setData((d) => ({
        ...d, element, format: element !== 'text' && textOnlyFormats.includes(d.format) ? 'ANY' : d.format,
    }));

    const submit = (e: React.FormEvent) => { e.preventDefault(); form.submit(method, submitUrl); };
    const flagSwitch = ([key, label, description]: [Flag, string, string]) => (
        <SwitchField key={key} id={key} label={label} description={description}
            checked={form.data[key]} onChange={(v) => form.setData(key, v)} error={form.errors[key]} />
    );

    return (
        <form onSubmit={submit} className="space-y-6">
            <FormCard>
                <FormCardHeader title="General" />
                <FormCardContent className="md:grid-cols-2">
                    <div className="space-y-2">
                        <TextField id="name" label="Name" required autoFocus
                            value={form.data.name} onChange={(v) => form.setData('name', v)} error={form.errors.name} />
                        {initial?.db_column && (
                            <p className="text-sm text-muted-foreground">
                                Stored in column <code className="font-mono">{initial.db_column}</code>; renaming the field renames it.
                            </p>
                        )}
                    </div>
                    <SelectField id="element" label="Element" required options={elementOptions}
                        value={form.data.element} onChange={setElement} error={form.errors.element} />
                    <SelectField id="format" label="Format" required options={formats}
                        value={form.data.format} onChange={(v) => form.setData('format', v)} error={form.errors.format} />
                    {form.data.format === 'CUSTOM REGEX' && (
                        <TextField id="custom_regex" label="Regular expression" required placeholder="/^[A-Z]{3}-\d+$/"
                            value={form.data.custom_regex} onChange={(v) => form.setData('custom_regex', v)} error={form.errors.custom_regex} />
                    )}
                    {hasChoices && (
                        <div className="md:col-span-2">
                            <TextareaField id="field_values" label="Options" required rows={5} placeholder="One option per line"
                                value={form.data.field_values} onChange={(v) => form.setData('field_values', v)} error={form.errors.field_values} />
                        </div>
                    )}
                    <div className="md:col-span-2">
                        <TextareaField id="help_text" label="Help text" rows={3}
                            value={form.data.help_text} onChange={(v) => form.setData('help_text', v)} error={form.errors.help_text} />
                    </div>
                </FormCardContent>
            </FormCard>

            <FormCard>
                <FormCardHeader title="Behaviour" />
                <FormCardContent className="md:grid-cols-2">{BEHAVIOUR.map(flagSwitch)}</FormCardContent>
            </FormCard>

            <FormCard>
                <FormCardHeader title="Display" description="Where the value shows up." />
                <FormCardContent className="md:grid-cols-2">{DISPLAY.map(flagSwitch)}</FormCardContent>
            </FormCard>

            <div className="flex gap-2">
                <Button type="submit" disabled={form.processing}>Save</Button>
                <Button type="button" variant="ghost" onClick={() => history.back()}>Cancel</Button>
            </div>
        </form>
    );
}
