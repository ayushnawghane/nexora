import { SelectField, SwitchField, TextField } from '@/Components/form-fields';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldLabel,
    FieldLegend,
    FieldSet,
} from '@/Components/ui/field';
import { formatDate } from '@/lib/format';
import { PlusIcon, Trash2Icon } from 'lucide-react';

/*
 * Renders a God Mode editor's fields (from App\GodMode\Editor::fields()) against
 * form.data.values. Fields are addressed by dotted path ("fees.service.amount",
 * "instruments.0.base_amount"), which is also how the server names its validation errors, so
 * every error shows under its own field.
 */

export function getPath(object, path) {
    return path.split('.').reduce((value, key) => (value == null ? undefined : value[key]), object);
}

function setPath(object, path, value) {
    const [key, ...rest] = path.split('.');
    const copy = Array.isArray(object) ? [...object] : { ...object };
    copy[key] = rest.length === 0 ? value : setPath(object?.[key] ?? {}, rest.join('.'), value);
    return copy;
}

function useValue(form, path) {
    return {
        get: () => getPath(form.data.values, path),
        set: (value) => form.setData('values', setPath(form.data.values, path, value)),
    };
}

/** How a stored value reads in a summary ("Yes", the option label, or the raw text). */
export function displayValue(field, value) {
    if (value === null || value === undefined || value === '') return null;
    if (field.type === 'boolean') return value ? 'Yes' : 'No';
    if (field.type === 'date') return formatDate(value);
    if (field.type === 'select') {
        return (
            field.options?.find((o) => String(o.value) === String(value))?.label ?? String(value)
        );
    }
    if (field.type === 'multiselect') {
        return (value ?? [])
            .map((v) => field.options?.find((o) => o.value === v)?.label ?? v)
            .join(', ');
    }
    if (Array.isArray(value)) return `${value.length} row${value.length === 1 ? '' : 's'}`;
    return String(value);
}

function Multiselect({ form, field, path }) {
    const { get, set } = useValue(form, path);
    const selected = get() ?? [];
    const error = form.errors[path] ?? form.errors[`${path}.0`];

    return (
        <FieldSet data-invalid={!!error || undefined}>
            <FieldLegend variant="label">
                {field.label}
                {field.required && <span className="text-destructive">*</span>}
            </FieldLegend>
            <div className="flex max-h-56 flex-col gap-2 overflow-y-auto rounded-md border p-3">
                {field.options.map((option) => {
                    const id = `f-${path}-${option.value}`;
                    return (
                        <Field key={option.value} orientation="horizontal">
                            <Checkbox
                                id={id}
                                checked={selected.includes(option.value)}
                                onCheckedChange={(on) =>
                                    set(
                                        on === true
                                            ? [...selected, option.value]
                                            : selected.filter((v) => v !== option.value),
                                    )
                                }
                            />
                            <FieldLabel htmlFor={id} className="font-normal">
                                {option.label}
                            </FieldLabel>
                        </Field>
                    );
                })}
            </div>
            <FieldError>{error}</FieldError>
        </FieldSet>
    );
}

function Rows({ form, field, path }) {
    const { get, set } = useValue(form, path);
    const rows = get() ?? [];

    return (
        <FieldSet data-invalid={!!form.errors[path] || undefined}>
            <FieldLegend variant="label">{field.label}</FieldLegend>
            {field.hint && <FieldDescription>{field.hint}</FieldDescription>}
            <div className="flex flex-col gap-3">
                {rows.map((_, index) => (
                    <div key={index} className="flex flex-col gap-3 rounded-md border p-3">
                        {field.fields.map((sub) => (
                            <FieldControl
                                key={sub.name}
                                form={form}
                                field={sub}
                                path={`${path}.${index}.${sub.name}`}
                            />
                        ))}
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="self-start text-destructive"
                            onClick={() => set(rows.filter((__, i) => i !== index))}
                        >
                            <Trash2Icon /> Remove
                        </Button>
                    </div>
                ))}
            </div>
            <Button
                type="button"
                variant="outline"
                size="sm"
                className="self-start"
                onClick={() => set([...rows, { ...field.blank }])}
            >
                <PlusIcon /> {field.addLabel ?? 'Add'}
            </Button>
            <FieldError>{form.errors[path]}</FieldError>
        </FieldSet>
    );
}

function Group({ form, field, path }) {
    return (
        <FieldSet className="rounded-md border p-3">
            <FieldLegend variant="label">{field.label}</FieldLegend>
            {field.fields.map((sub) => (
                <FieldControl key={sub.name} form={form} field={sub} path={`${path}.${sub.name}`} />
            ))}
            <FieldError>{form.errors[path]}</FieldError>
        </FieldSet>
    );
}

function FieldControl({ form, field, path }) {
    const { get, set } = useValue(form, path);
    const common = {
        form,
        name: path,
        label: field.label,
        required: field.required,
        hint: field.hint,
        get,
        set,
    };

    if (field.readOnly) {
        return (
            <Field>
                <FieldLabel>{field.label}</FieldLabel>
                <p className={field.mono ? 'font-mono text-[13px]' : 'text-[13px]'}>
                    {displayValue(field, get()) ?? '—'}
                </p>
                {field.hint && <FieldDescription>{field.hint}</FieldDescription>}
            </Field>
        );
    }

    switch (field.type) {
        case 'select':
            return <SelectField {...common} items={field.options} numeric={field.numeric} />;
        case 'boolean':
            return <SwitchField {...common} />;
        case 'multiselect':
            return <Multiselect form={form} field={field} path={path} />;
        case 'rows':
            return <Rows form={form} field={field} path={path} />;
        case 'group':
            return <Group form={form} field={field} path={path} />;
        case 'textarea':
            return <TextField {...common} multiline rows={3} />;
        case 'date':
            return <TextField {...common} type="date" className="max-w-48" />;
        case 'number':
            return <TextField {...common} inputMode="decimal" />;
        default:
            return (
                <TextField {...common} className={field.mono ? '[&_input]:font-mono' : undefined} />
            );
    }
}

/** All fields of one editor. `form` is a useForm({ values, fingerprint, reason }) instance. */
export function EditorFields({ form, fields }) {
    return fields.map((field) => (
        <FieldControl key={field.name} form={form} field={field} path={field.name} />
    ));
}
