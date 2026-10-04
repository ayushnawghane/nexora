import { Combobox } from '@/Components/combobox';
import { ConfirmAction } from '@/Components/confirm-action';
import { DataTable } from '@/Components/data-table';
import { PageHeader } from '@/Components/page-header';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/Components/ui/field';
import { Input } from '@/Components/ui/input';
import { ScrollArea } from '@/Components/ui/scroll-area';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/Components/ui/sheet';
import { useTableFilters } from '@/hooks/use-table-filters';
import AppLayout from '@/Layouts/AppLayout';
import { useForm } from '@inertiajs/react';
import { MoreHorizontalIcon, PlusIcon, SearchIcon } from 'lucide-react';
import { useMemo, useState } from 'react';

const ALL = '__all__';

function emptyValue(field) {
    if (field.type === 'multiselect') return [];
    if (field.type === 'select') return null;
    if (field.type === 'boolean') return false;
    return '';
}

function emptyValues(schema) {
    return Object.fromEntries(schema.map((f) => [f.name, emptyValue(f)]));
}

function MasterForm({ master, schema, record, onDone }) {
    const editing = Boolean(record);
    const initial = editing
        ? Object.fromEntries(
              schema.map((f) => [
                  f.name,
                  record[f.name] ?? (f.type === 'select' ? null : emptyValue(f)),
              ]),
          )
        : emptyValues(schema);
    const { data, setData, post, put, processing, errors } = useForm(initial);

    const submit = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: onDone };
        if (editing) put(route('masters.update', [master.key, record.id]), options);
        else post(route('masters.store', master.key), options);
    };

    return (
        <form onSubmit={submit} noValidate className="flex h-full flex-col">
            <SheetHeader>
                <SheetTitle>
                    {editing ? `Edit ${master.singular}` : `New ${master.singular}`}
                </SheetTitle>
                <SheetDescription>Fields marked * are required.</SheetDescription>
            </SheetHeader>
            <ScrollArea className="min-h-0 flex-1 px-4">
                <FieldGroup className="pb-4">
                    {schema.map((field) => {
                        const id = `field-${field.name}`;
                        const error = errors[field.name] || errors[`${field.name}.0`];
                        if (field.type === 'boolean') {
                            return (
                                <Field
                                    key={field.name}
                                    orientation="horizontal"
                                    data-invalid={!!error || undefined}
                                >
                                    <Checkbox
                                        id={id}
                                        checked={data[field.name] === true}
                                        onCheckedChange={(on) => setData(field.name, on === true)}
                                    />
                                    <FieldLabel htmlFor={id} className="font-normal">
                                        {field.label}
                                    </FieldLabel>
                                    <FieldError>{error}</FieldError>
                                </Field>
                            );
                        }
                        return (
                            <Field key={field.name} data-invalid={!!error || undefined}>
                                <FieldLabel htmlFor={id}>
                                    {field.label}
                                    {field.required && <span className="text-destructive">*</span>}
                                </FieldLabel>
                                {field.type === 'select' ? (
                                    <Combobox
                                        id={id}
                                        value={data[field.name]}
                                        onChange={(v) => setData(field.name, v)}
                                        options={field.options}
                                        allowClear={!field.required}
                                        invalid={!!error}
                                    />
                                ) : field.type === 'multiselect' ? (
                                    <div className="flex max-h-56 flex-col gap-2 overflow-y-auto rounded-md border p-3">
                                        {field.options.map((option) => {
                                            const optionId = `${id}-${option.value}`;
                                            const checked = data[field.name].includes(option.value);
                                            return (
                                                <Field key={option.value} orientation="horizontal">
                                                    <Checkbox
                                                        id={optionId}
                                                        checked={checked}
                                                        onCheckedChange={(on) =>
                                                            setData(
                                                                field.name,
                                                                on === true
                                                                    ? [
                                                                          ...data[field.name],
                                                                          option.value,
                                                                      ]
                                                                    : data[field.name].filter(
                                                                          (v) => v !== option.value,
                                                                      ),
                                                            )
                                                        }
                                                    />
                                                    <FieldLabel
                                                        htmlFor={optionId}
                                                        className="font-normal"
                                                    >
                                                        {option.label}
                                                    </FieldLabel>
                                                </Field>
                                            );
                                        })}
                                    </div>
                                ) : (
                                    <Input
                                        id={id}
                                        type={field.type === 'email' ? 'email' : 'text'}
                                        value={data[field.name] ?? ''}
                                        onChange={(e) => setData(field.name, e.target.value)}
                                        aria-invalid={!!error || undefined}
                                    />
                                )}
                                {field.hint && !error && (
                                    <FieldDescription>{field.hint}</FieldDescription>
                                )}
                                <FieldError>{error}</FieldError>
                            </Field>
                        );
                    })}
                </FieldGroup>
            </ScrollArea>
            <SheetFooter className="flex-row justify-end border-t">
                <Button type="button" variant="outline" onClick={onDone} disabled={processing}>
                    Cancel
                </Button>
                <Button type="submit" disabled={processing}>
                    {processing ? 'Saving…' : 'Save'}
                </Button>
            </SheetFooter>
        </form>
    );
}

export default function MasterShow({
    master,
    schema,
    records,
    filters: initialFilters,
    sort,
    can,
}) {
    const { filters, setFilter, query } = useTableFilters(initialFilters, { sort });
    const [sheet, setSheet] = useState({ open: false, record: null, key: 0 });

    const openForm = (record = null) => setSheet((s) => ({ open: true, record, key: s.key + 1 }));
    const closeForm = () => setSheet((s) => ({ ...s, open: false }));

    const columns = useMemo(() => {
        const fieldColumns = schema
            .filter((f) => f.list)
            .map((f) => ({
                id: f.name,
                header: f.label,
                meta: f.sortable ? { sortKey: f.name } : {},
                cell: ({ row }) => {
                    if (f.type === 'boolean') {
                        return row.original[f.name] ? (
                            <span className="text-foreground">Yes</span>
                        ) : (
                            <span className="text-subtle-foreground">—</span>
                        );
                    }
                    const value =
                        f.type === 'select' || f.type === 'multiselect'
                            ? row.original[`${f.name}__label`]
                            : row.original[f.name];
                    return value ? (
                        <span className="text-foreground">{value}</span>
                    ) : (
                        <span className="text-subtle-foreground">—</span>
                    );
                },
            }));

        return [
            ...fieldColumns,
            {
                id: 'status',
                header: 'Status',
                cell: ({ row }) =>
                    row.original.is_active ? (
                        <Badge variant="success">Active</Badge>
                    ) : (
                        <Badge variant="neutral">Inactive</Badge>
                    ),
            },
            ...(can.manage
                ? [
                      {
                          id: 'actions',
                          header: '',
                          meta: { align: 'right' },
                          cell: ({ row }) => (
                              <DropdownMenu>
                                  <DropdownMenuTrigger asChild>
                                      <Button
                                          variant="ghost"
                                          size="icon-sm"
                                          aria-label="Row actions"
                                      >
                                          <MoreHorizontalIcon />
                                      </Button>
                                  </DropdownMenuTrigger>
                                  <DropdownMenuContent align="end">
                                      <DropdownMenuItem onSelect={() => openForm(row.original)}>
                                          Edit
                                      </DropdownMenuItem>
                                      <ConfirmAction
                                          href={route('masters.toggle', [
                                              master.key,
                                              row.original.id,
                                          ])}
                                          title={
                                              row.original.is_active
                                                  ? `Deactivate this ${master.singular}?`
                                                  : `Activate this ${master.singular}?`
                                          }
                                          description={
                                              row.original.is_active
                                                  ? 'It will no longer be offered in forms. Existing records keep it.'
                                                  : 'It will be offered in forms again.'
                                          }
                                          confirmLabel={
                                              row.original.is_active ? 'Deactivate' : 'Activate'
                                          }
                                          trigger={
                                              <DropdownMenuItem
                                                  onSelect={(e) => e.preventDefault()}
                                              >
                                                  {row.original.is_active
                                                      ? 'Deactivate'
                                                      : 'Activate'}
                                              </DropdownMenuItem>
                                          }
                                      />
                                      <ConfirmAction
                                          method="delete"
                                          href={route('masters.destroy', [
                                              master.key,
                                              row.original.id,
                                          ])}
                                          title={`Delete this ${master.singular}?`}
                                          description="Records that are in use can't be deleted; deactivate them instead."
                                          confirmLabel="Delete"
                                          destructive
                                          trigger={
                                              <DropdownMenuItem
                                                  variant="destructive"
                                                  onSelect={(e) => e.preventDefault()}
                                              >
                                                  Delete
                                              </DropdownMenuItem>
                                          }
                                      />
                                  </DropdownMenuContent>
                              </DropdownMenu>
                          ),
                      },
                  ]
                : []),
        ];
    }, [schema, can.manage, master]);

    return (
        <AppLayout
            title={master.label}
            breadcrumbs={[
                { title: 'Masters', href: route('masters.index') },
                { title: master.label },
            ]}
        >
            <PageHeader
                title={master.label}
                actions={
                    can.manage && (
                        <Button onClick={() => openForm()}>
                            <PlusIcon /> Add {master.singular}
                        </Button>
                    )
                }
            />
            <DataTable
                columns={columns}
                paginator={records}
                sort={sort}
                query={query}
                emptyTitle={`No ${master.label.toLowerCase()} found`}
                toolbar={
                    <div className="flex flex-wrap items-center gap-2">
                        <div className="relative w-full sm:w-72">
                            <SearchIcon className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={filters.search}
                                onChange={(e) => setFilter('search', e.target.value)}
                                placeholder="Search"
                                className="pl-8"
                                aria-label={`Search ${master.label}`}
                            />
                        </div>
                        <Select
                            value={filters.is_active === '' ? ALL : String(filters.is_active)}
                            onValueChange={(v) => setFilter('is_active', v === ALL ? '' : v)}
                        >
                            <SelectTrigger className="w-36" aria-label="Status">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>All statuses</SelectItem>
                                <SelectItem value="1">Active</SelectItem>
                                <SelectItem value="0">Inactive</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                }
            />
            <Sheet open={sheet.open} onOpenChange={(open) => !open && closeForm()}>
                <SheetContent className="flex w-full flex-col gap-0 sm:max-w-md">
                    {sheet.open && (
                        <MasterForm
                            key={sheet.key}
                            master={master}
                            schema={schema}
                            record={sheet.record}
                            onDone={closeForm}
                        />
                    )}
                </SheetContent>
            </Sheet>
        </AppLayout>
    );
}
