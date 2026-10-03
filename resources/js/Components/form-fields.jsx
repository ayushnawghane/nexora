import { Combobox } from '@/Components/combobox';
import { Field, FieldDescription, FieldError, FieldLabel } from '@/Components/ui/field';
import { Input } from '@/Components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { Switch } from '@/Components/ui/switch';
import { Textarea } from '@/Components/ui/textarea';

const NONE = '__none__';

/*
 * Form field compositions bound to an Inertia useForm instance (`form`), with label, hint and
 * inline error. `name` may be a dotted path ("fees.service.amount"); values are read and written
 * through `get`/`set` when given, otherwise form.data[name].
 */

function Label({ id, label, required }) {
    return (
        <FieldLabel htmlFor={id}>
            {label}
            {required && <span className="text-destructive">*</span>}
        </FieldLabel>
    );
}

function useBinding(form, name, get, set) {
    return {
        value: get ? get() : form.data[name],
        setValue: set ?? ((v) => form.setData(name, v)),
        error: form.errors[name],
    };
}

export function TextField({
    form,
    name,
    label,
    required,
    hint,
    error: extraError,
    get,
    set,
    multiline,
    className,
    ...props
}) {
    const { value, setValue, error } = useBinding(form, name, get, set);
    const message = error ?? extraError;
    const id = `f-${name}`;
    const Control = multiline ? Textarea : Input;

    return (
        <Field data-invalid={!!message || undefined} className={className}>
            <Label id={id} label={label} required={required} />
            <Control
                id={id}
                value={value ?? ''}
                onChange={(e) => setValue(e.target.value)}
                aria-invalid={!!message || undefined}
                autoComplete="off"
                {...props}
            />
            {hint && !message && <FieldDescription>{hint}</FieldDescription>}
            <FieldError>{message}</FieldError>
        </Field>
    );
}

/** Select over [{ value, label }]. Values are compared as strings; `numeric` converts back. */
export function SelectField({
    form,
    name,
    label,
    required,
    hint,
    items,
    allowNone = !required,
    numeric = false,
    get,
    set,
    className,
    placeholder = 'Select…',
}) {
    const { value, setValue, error } = useBinding(form, name, get, set);
    const id = `f-${name}`;

    return (
        <Field data-invalid={!!error || undefined} className={className}>
            <Label id={id} label={label} required={required} />
            <Select
                value={value === null || value === undefined || value === '' ? NONE : String(value)}
                onValueChange={(v) => setValue(v === NONE ? null : numeric ? Number(v) : v)}
            >
                <SelectTrigger id={id} aria-invalid={!!error || undefined} className="w-full">
                    <SelectValue placeholder={placeholder} />
                </SelectTrigger>
                <SelectContent>
                    {allowNone && <SelectItem value={NONE}>Not set</SelectItem>}
                    {items.map((item) => (
                        <SelectItem key={item.value} value={String(item.value)}>
                            {item.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            {hint && !error && <FieldDescription>{hint}</FieldDescription>}
            <FieldError>{error}</FieldError>
        </Field>
    );
}

/** Searchable select for long lists, over [{ value, label, description? }]. */
export function ComboField({
    form,
    name,
    label,
    required,
    hint,
    items,
    get,
    set,
    className,
    placeholder,
}) {
    const { value, setValue, error } = useBinding(form, name, get, set);
    const id = `f-${name}`;

    return (
        <Field data-invalid={!!error || undefined} className={className}>
            <Label id={id} label={label} required={required} />
            <Combobox
                id={id}
                value={value}
                onChange={setValue}
                options={items}
                allowClear={!required}
                invalid={!!error}
                placeholder={placeholder}
            />
            {hint && !error && <FieldDescription>{hint}</FieldDescription>}
            <FieldError>{error}</FieldError>
        </Field>
    );
}

export function SwitchField({ form, name, label, hint, get, set, className }) {
    const { value, setValue, error } = useBinding(form, name, get, set);
    const id = `f-${name}`;

    return (
        <Field orientation="horizontal" className={className} data-invalid={!!error || undefined}>
            <Switch id={id} checked={!!value} onCheckedChange={setValue} />
            <div className="flex flex-col gap-0.5">
                <FieldLabel htmlFor={id} className="font-normal">
                    {label}
                </FieldLabel>
                {hint && <FieldDescription>{hint}</FieldDescription>}
                <FieldError>{error}</FieldError>
            </div>
        </Field>
    );
}
