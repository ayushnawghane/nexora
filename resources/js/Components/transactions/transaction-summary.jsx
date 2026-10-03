import { Badge } from '@/Components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { formatDate, formatMoney } from '@/lib/format';

const dash = <span className="text-subtle-foreground">—</span>;

const labelOf = (items, value, key = 'value') =>
    items.find((item) => String(item[key]) === String(value))?.[key === 'id' ? 'name' : 'label'];

function Detail({ label, children }) {
    return (
        <div className="min-w-0">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="mt-0.5 text-[13px] break-words">{children || dash}</dd>
        </div>
    );
}

function Section({ title, children }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
            </CardHeader>
            <CardContent>{children}</CardContent>
        </Card>
    );
}

/** Read-only overview of a transaction, used by the wizard's review step and the transaction page. */
export function TransactionSummary({ transaction, options }) {
    const { basics, issue, fees, schedule } = transaction;
    const contacts = transaction.contacts.map((c) => ({
        ...c,
        contact: options.contacts.find((o) => o.id === c.company_contact_id),
    }));

    return (
        <div className="grid gap-3 lg:grid-cols-2">
            <Section title="Basics">
                <dl className="grid gap-4 sm:grid-cols-2">
                    <Detail label="Company">{transaction.company.name}</Detail>
                    <Detail label="Vertical team">
                        {labelOf(options.verticalTeams, basics.vertical_team_id, 'id')}
                    </Detail>
                    <Detail label="Relationship manager">
                        {labelOf(options.users, basics.relationship_manager_id)}
                    </Detail>
                    <Detail label="Signatory">
                        {labelOf(options.signatories, basics.signatory_id)}
                    </Detail>
                    <Detail label="Transaction type">
                        {labelOf(options.transactionTypes, basics.transaction_type_id, 'id')}
                    </Detail>
                    <Detail label="Lead source">
                        {labelOf(options.leadSources, basics.lead_source_id, 'id')}
                    </Detail>
                    <Detail label="Arranger">
                        {labelOf(options.arrangers, basics.arranger_id)}
                    </Detail>
                    <Detail label="Origin">{labelOf(options.origins, basics.origin)}</Detail>
                    <div className="sm:col-span-2">
                        <Detail label="Brief">{basics.brief}</Detail>
                    </div>
                </dl>
            </Section>

            <Section title="Contacts">
                {contacts.length === 0 ? (
                    dash
                ) : (
                    <ul className="flex flex-col gap-3">
                        {contacts.map(({ company_contact_id, recipient, contact }) => (
                            <li key={company_contact_id} className="flex items-start gap-3">
                                <Badge variant={recipient === 'to' ? 'brand' : 'neutral'}>
                                    {recipient === 'to' ? 'To' : 'Cc'}
                                </Badge>
                                <div className="min-w-0 text-[13px]">
                                    <div className="font-medium">{contact?.name}</div>
                                    <div className="truncate text-xs text-muted-foreground">
                                        {[contact?.designation, contact?.email, contact?.mobile]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </Section>

            <Section title="Issue details">
                {issue ? (
                    <dl className="grid gap-4 sm:grid-cols-2">
                        <Detail label="Total issue size">
                            <span className="text-base font-semibold tabular-nums">
                                {formatMoney(issue.total_issue_size)}
                            </span>
                        </Detail>
                        <Detail label="Base / green shoe">
                            <span className="tabular-nums">
                                {formatMoney(issue.base_issue_size)} /{' '}
                                {formatMoney(issue.green_shoe_size)}
                            </span>
                        </Detail>
                        <Detail label="Listing">{labelOf(options.listings, issue.listing)}</Detail>
                        <Detail label="Issue type">
                            {labelOf(options.issueTypes, issue.issue_type)}
                        </Detail>
                        <Detail label="Security">
                            {issue.is_secured ? 'Secured' : 'Unsecured'}
                        </Detail>
                        <Detail label="Rating">{issue.is_rated ? 'Rated' : 'Unrated'}</Detail>
                        <Detail label="Tenure">
                            {issue.tenure_months} months
                            {issue.tenure_days ? ` ${issue.tenure_days} days` : ''}
                        </Detail>
                        <Detail label="Instruments">
                            {issue.instruments.map((i) => i.instrument.toUpperCase()).join(', ')}
                        </Detail>
                    </dl>
                ) : (
                    dash
                )}
            </Section>

            <Section title="Fees">
                {Object.keys(fees).length === 0 ? (
                    dash
                ) : (
                    <div className="flex flex-col gap-4">
                        {schedule.lines.map((line) => {
                            const fee = fees[line.kind];
                            return (
                                <dl key={line.kind} className="grid gap-4 sm:grid-cols-2">
                                    <Detail label={line.label}>
                                        <span className="font-semibold tabular-nums">
                                            {formatMoney(line.annual_amount)}
                                        </span>
                                        {line.kind === 'service' && ' p.a.'}
                                        {fee.amount_type === 'percent' &&
                                            ` (${Number(fee.percent)}% of issue size)`}
                                    </Detail>
                                    <Detail label="Billing">
                                        {line.frequency}, {line.timing.toLowerCase()} from{' '}
                                        {formatDate(fee.start_date)}
                                    </Detail>
                                </dl>
                            );
                        })}
                        <p className="text-xs text-muted-foreground">
                            {schedule.verified_at
                                ? `Schedule verified by ${schedule.verified_by} on ${formatDate(schedule.verified_at)}.`
                                : 'Schedule not verified yet.'}
                        </p>
                    </div>
                )}
            </Section>
        </div>
    );
}
