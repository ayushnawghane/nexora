/** Page title row: title, optional description, and right-aligned actions. */
export function PageHeader({ title, description, actions }) {
    return (
        <div className="flex flex-wrap items-start justify-between gap-4">
            <div className="min-w-0">
                <h1 className="text-base font-semibold tracking-tight text-foreground">{title}</h1>
                {description && (
                    <p className="mt-0.5 text-[13px] text-muted-foreground">{description}</p>
                )}
            </div>
            {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
        </div>
    );
}
