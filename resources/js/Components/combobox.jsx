import { Button } from '@/Components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/Components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';
import { cn } from 'cn';
import { CheckIcon, ChevronsUpDownIcon } from 'lucide-react';
import { useState } from 'react';

/**
 * Searchable select (shadcn combobox pattern: Popover + Command).
 * options: [{ value, label, description? }]. Values are compared as strings.
 */
export function Combobox({
    id,
    value,
    onChange,
    options,
    placeholder = 'Select…',
    searchPlaceholder = 'Search…',
    emptyText = 'No match found.',
    allowClear = true,
    invalid,
    disabled,
    className,
}) {
    const [open, setOpen] = useState(false);
    const selected = options.find((o) => String(o.value) === String(value ?? ''));

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    id={id}
                    type="button"
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    aria-invalid={invalid || undefined}
                    disabled={disabled}
                    className={cn(
                        'w-full justify-between font-normal',
                        !selected && 'text-muted-foreground',
                        className,
                    )}
                >
                    <span className="truncate">{selected ? selected.label : placeholder}</span>
                    <ChevronsUpDownIcon className="opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent
                className="w-(--radix-popover-trigger-width) min-w-64 p-0"
                align="start"
            >
                <Command>
                    <CommandInput placeholder={searchPlaceholder} />
                    <CommandList>
                        <CommandEmpty>{emptyText}</CommandEmpty>
                        <CommandGroup>
                            {allowClear && selected && (
                                <CommandItem
                                    value="__clear__"
                                    onSelect={() => {
                                        onChange(null);
                                        setOpen(false);
                                    }}
                                    className="text-muted-foreground"
                                >
                                    Clear selection
                                </CommandItem>
                            )}
                            {options.map((option) => (
                                <CommandItem
                                    key={option.value}
                                    value={`${option.label} ${option.description ?? ''} ${option.value}`}
                                    onSelect={() => {
                                        onChange(option.value);
                                        setOpen(false);
                                    }}
                                >
                                    <div className="min-w-0 flex-1">
                                        <div className="truncate">{option.label}</div>
                                        {option.description && (
                                            <div className="truncate text-xs text-muted-foreground">
                                                {option.description}
                                            </div>
                                        )}
                                    </div>
                                    <CheckIcon
                                        className={cn(
                                            'text-primary',
                                            String(option.value) === String(value ?? '')
                                                ? 'opacity-100'
                                                : 'opacity-0',
                                        )}
                                    />
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
