import { Badge } from '@/Components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { formatDate, formatMoney } from '@/lib/format';

/** One fee's billing schedule: a period per row, with the pro-rata ones marked. */
export function ScheduleTable({ line }) {
    return (
        <Card className="gap-0 py-0">
            <CardHeader className="border-b py-4">
                <CardTitle>{line.label}</CardTitle>
                <CardDescription>
                    {formatMoney(line.annual_amount)}
                    {line.kind === 'service' ? ' per annum' : ''} · {line.frequency} · {line.timing}
                </CardDescription>
            </CardHeader>
            <CardContent className="px-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Year</TableHead>
                            <TableHead>Period</TableHead>
                            <TableHead className="text-right">Days</TableHead>
                            <TableHead>Bill date</TableHead>
                            <TableHead className="text-right">Rate p.a.</TableHead>
                            <TableHead className="text-right">Amount</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {line.periods.map((p) => (
                            <TableRow key={p.sequence}>
                                <TableCell className="text-muted-foreground">
                                    {p.financial_year}
                                </TableCell>
                                <TableCell>
                                    {formatDate(p.from_date)} – {formatDate(p.to_date)}
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {p.days}
                                    <span className="text-subtle-foreground">
                                        /{p.days_in_year}
                                    </span>
                                </TableCell>
                                <TableCell>{formatDate(p.bill_date)}</TableCell>
                                <TableCell className="text-right">
                                    {formatMoney(p.base_amount)}
                                </TableCell>
                                <TableCell className="text-right font-medium">
                                    <span className="inline-flex items-center gap-2">
                                        {p.prorated && <Badge variant="neutral">Pro rata</Badge>}
                                        {formatMoney(p.amount)}
                                    </span>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                    <TableFooter>
                        <TableRow>
                            <TableCell colSpan={5} className="font-medium">
                                Total over the tenure
                            </TableCell>
                            <TableCell className="text-right font-semibold">
                                {formatMoney(line.total)}
                            </TableCell>
                        </TableRow>
                    </TableFooter>
                </Table>
            </CardContent>
        </Card>
    );
}
