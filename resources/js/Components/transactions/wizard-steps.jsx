import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { CheckIcon } from 'lucide-react';

export const STEPS = [
    { key: 'basics', label: 'Basics' },
    { key: 'contacts', label: 'Contacts' },
    { key: 'issue', label: 'Issue details' },
    { key: 'fees', label: 'Fees' },
    { key: 'schedule', label: 'Schedule' },
    { key: 'review', label: 'Review' },
];

/** A step is reachable when every step before it is complete. */
export function isReachable(progress, key) {
    for (const step of STEPS) {
        if (step.key === key) return true;
        if (!progress[step.key]) return false;
    }
    return false;
}

/**
 * Wizard progress: numbered steps, done steps ticked, the current one highlighted. Steps that
 * can't be opened yet are plain text. Scrolls sideways on narrow screens.
 */
export function WizardSteps({ transactionId, current, progress }) {
    return (
        <nav aria-label="Steps" className="-mx-1 overflow-x-auto px-1">
            <ol className="flex min-w-max items-center gap-1">
                {STEPS.map((step, index) => {
                    const done = progress[step.key] && step.key !== 'review';
                    const active = step.key === current;
                    const reachable = transactionId && isReachable(progress, step.key);
                    const body = (
                        <>
                            <span
                                className={cn(
                                    'flex size-6 shrink-0 items-center justify-center rounded-full border text-xs tabular-nums',
                                    active && 'border-primary bg-primary text-primary-foreground',
                                    !active &&
                                        done &&
                                        'border-success-border bg-success-bg text-success',
                                    !active &&
                                        !done &&
                                        'border-border-strong text-muted-foreground',
                                )}
                            >
                                {done && !active ? <CheckIcon className="size-3.5" /> : index + 1}
                            </span>
                            <span
                                className={cn(
                                    'text-[13px] whitespace-nowrap',
                                    active
                                        ? 'font-medium text-foreground'
                                        : 'text-muted-foreground',
                                )}
                            >
                                {step.label}
                            </span>
                        </>
                    );

                    return (
                        <li key={step.key} className="flex items-center gap-1">
                            {index > 0 && (
                                <span className="h-px w-4 bg-border-strong sm:w-6" aria-hidden />
                            )}
                            {reachable && !active ? (
                                <Link
                                    href={route('transactions.edit', [
                                        transactionId,
                                        { step: step.key },
                                    ])}
                                    className="flex items-center gap-2 rounded-md px-2 py-1.5 hover:bg-hover pointer-coarse:py-2.5"
                                >
                                    {body}
                                </Link>
                            ) : (
                                <span
                                    className={cn(
                                        'flex items-center gap-2 rounded-md px-2 py-1.5',
                                        active && 'bg-surface-3',
                                    )}
                                    aria-current={active ? 'step' : undefined}
                                >
                                    {body}
                                </span>
                            )}
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
