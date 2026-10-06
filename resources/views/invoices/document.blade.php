{{--
    Proforma, tax invoice, credit note and reimbursement bill (one layout). Rendered by dompdf, so
    the CSS stays simple (tables, no flex/grid) and uses DejaVu Sans, which dompdf ships with.
--}}
@php
    use App\Enums\InvoiceKind;
    use App\Support\Money;
    $kind = $invoice->kind;
    $title = match ($kind) {
        InvoiceKind::Proforma => 'PROFORMA INVOICE',
        InvoiceKind::Tax => 'TAX INVOICE',
        InvoiceKind::CreditNote => 'CREDIT NOTE',
        InvoiceKind::Reimbursement => 'REIMBURSEMENT BILL (DEBIT NOTE)',
    };
    $hasTax = $invoice->gst_applies && $kind !== InvoiceKind::Reimbursement;
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} {{ $invoice->number }}</title>
    <style>
        @page { margin: 16mm 14mm 18mm 14mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5pt; line-height: 1.4; color: #111; }
        h1 { font-size: 13pt; letter-spacing: 0.08em; text-align: center; margin: 10px 0 10px; }
        table { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: top; }
        .issuer { font-size: 8pt; color: #333; }
        .issuer strong { font-size: 11pt; color: #111; }
        .box td, .box th { border: 0.5pt solid #777; padding: 4px 5px; vertical-align: top; text-align: left; }
        .box th { background: #f0f0f0; font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.04em; }
        .num { text-align: right !important; white-space: nowrap; }
        .muted { color: #555; }
        .label { color: #555; width: 28%; }
        .total td { font-weight: bold; background: #f7f7f7; }
        .words { margin: 6px 0 10px; font-size: 8pt; }
        .gap { height: 8px; }
        .qr { width: 110px; height: 110px; }
        .irn { font-size: 7pt; word-break: break-all; }
        .sign { margin-top: 26px; text-align: right; }
        .watermark { color: #b00; font-weight: bold; text-align: center; font-size: 10pt; margin-bottom: 6px; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td class="issuer" style="width: 70%">
                <strong>{{ $issuer['name'] }}</strong><br>
                {{ $issuer['address'] }}<br>
                Phone: {{ $issuer['phone'] }} · Email: {{ $issuer['email'] }} · {{ $issuer['website'] }}<br>
                CIN: {{ $issuer['cin'] }} · PAN: {{ $issuer['pan'] }}@if ($beaconGstin) · GSTIN: {{ $beaconGstin }}@endif
            </td>
            <td style="width: 30%; text-align: right">
                @if ($qr)
                    <img class="qr" src="{{ $qr }}" alt="Signed QR code">
                @endif
            </td>
        </tr>
    </table>

    <h1>{{ $title }}</h1>
    @if ($invoice->status === \App\Enums\InvoiceStatus::Cancelled)
        <div class="watermark">CANCELLED{{ $invoice->cancelled_at ? ' on '.$invoice->cancelled_at->format('d M Y') : '' }}</div>
    @endif

    <table class="box">
        <tr>
            <td class="label">{{ $kind === InvoiceKind::CreditNote ? 'Credit note no.' : ($kind === InvoiceKind::Reimbursement ? 'Bill no.' : 'Invoice no.') }}</td>
            <td><strong>{{ $invoice->number ?? 'DRAFT' }}</strong></td>
            <td class="label">Date</td>
            <td>{{ $invoice->invoice_date?->format('d M Y') ?? '—' }}</td>
        </tr>
        @if ($parent)
            <tr>
                <td class="label">{{ $kind === InvoiceKind::CreditNote ? 'Against tax invoice' : 'Proforma invoice' }}</td>
                <td>{{ $parent->number }}</td>
                <td class="label">Dated</td>
                <td>{{ $parent->invoice_date?->format('d M Y') }}</td>
            </tr>
        @endif
        <tr>
            <td class="label">Deal</td>
            <td>{{ $deal->el_number }}@if ($deal->deal_code)<br><span class="muted">{{ $deal->deal_code }}</span>@endif</td>
            <td class="label">Place of supply</td>
            <td>{{ $invoice->placeOfSupply?->name ?? '—' }}@if ($invoice->placeOfSupply?->gst_code) ({{ $invoice->placeOfSupply->gst_code }})@endif</td>
        </tr>
        @if ($invoice->period_from && $invoice->period_to)
            <tr>
                <td class="label">Period</td>
                <td colspan="3">{{ $invoice->period_from->format('d M Y') }} to {{ $invoice->period_to->format('d M Y') }}</td>
            </tr>
        @endif
    </table>

    <div class="gap"></div>

    <table class="box">
        <tr><th colspan="2">Billed to</th></tr>
        <tr><td class="label">Name</td><td><strong>{{ $invoice->billed_name }}</strong></td></tr>
        <tr><td class="label">Address</td><td>{{ $invoice->billed_address }}</td></tr>
        <tr><td class="label">GSTIN</td><td>{{ $invoice->billed_gstin ?? 'Unregistered' }}</td></tr>
    </table>

    <div class="gap"></div>

    <table class="box">
        <tr>
            <th style="width: 5%">#</th>
            <th>Description</th>
            <th style="width: 11%">SAC</th>
            <th style="width: 18%" class="num">Amount (₹)</th>
        </tr>
        @foreach ($invoice->lines as $line)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>
                    {{ $line->description }}
                    @if ($line->period_from && $line->period_to)
                        <br><span class="muted">{{ $line->period_from->format('d M Y') }} to {{ $line->period_to->format('d M Y') }}</span>
                    @endif
                    @if (! $line->taxable && $hasTax)
                        <br><span class="muted">Reimbursement, not subject to GST</span>
                    @endif
                </td>
                <td>{{ $line->taxable ? $invoice->sac : '—' }}</td>
                <td class="num">{{ Money::format($line->amount, '') }}</td>
            </tr>
        @endforeach
        @if ($hasTax)
            <tr><td colspan="3" class="num">Taxable value</td><td class="num">{{ Money::format($invoice->taxable_amount, '') }}</td></tr>
            @if ($invoice->inter_state)
                <tr><td colspan="3" class="num">IGST @ {{ rtrim(rtrim($invoice->igst_rate, '0'), '.') }}%</td><td class="num">{{ Money::format($invoice->igst, '') }}</td></tr>
            @else
                <tr><td colspan="3" class="num">CGST @ {{ rtrim(rtrim($invoice->cgst_rate, '0'), '.') }}%</td><td class="num">{{ Money::format($invoice->cgst, '') }}</td></tr>
                <tr><td colspan="3" class="num">SGST @ {{ rtrim(rtrim($invoice->sgst_rate, '0'), '.') }}%</td><td class="num">{{ Money::format($invoice->sgst, '') }}</td></tr>
            @endif
            @if (\Brick\Math\BigDecimal::of($invoice->non_taxable_amount)->isPositive())
                <tr><td colspan="3" class="num">Reimbursements (no GST)</td><td class="num">{{ Money::format($invoice->non_taxable_amount, '') }}</td></tr>
            @endif
        @endif
        <tr class="total"><td colspan="3" class="num">Total</td><td class="num">{{ Money::format($invoice->total) }}</td></tr>
    </table>
    <p class="words">{{ Money::inWords($invoice->total) }}</p>

    @if ($invoice->notes)
        <p><span class="muted">{{ $kind === InvoiceKind::CreditNote ? 'Reason:' : 'Notes:' }}</span> {{ $invoice->notes }}</p>
    @endif

    @if ($invoice->irn)
        <table class="box">
            <tr><td class="label">IRN</td><td class="irn">{{ $invoice->irn }}</td></tr>
            <tr><td class="label">Ack no. / date</td><td>{{ $invoice->ack_no }} · {{ $invoice->ack_at?->format('d M Y H:i') }}</td></tr>
        </table>
        <div class="gap"></div>
    @endif

    @if ($kind !== InvoiceKind::CreditNote)
        <table class="box">
            <tr><th colspan="2">Pay to</th></tr>
            <tr><td class="label">Beneficiary</td><td>{{ $issuer['bank']['beneficiary'] }}</td></tr>
            <tr><td class="label">Bank</td><td>{{ $issuer['bank']['bank'] }}</td></tr>
            <tr><td class="label">Account no.</td><td>{{ $issuer['bank']['account'] }}</td></tr>
            <tr><td class="label">IFSC</td><td>{{ $issuer['bank']['ifsc'] }}</td></tr>
        </table>
        <p class="muted" style="margin-top: 6px">Please quote the {{ $kind === InvoiceKind::Proforma ? 'proforma' : 'invoice' }} number with your payment.
            @if ($kind === InvoiceKind::Proforma) A tax invoice will be issued on receipt of payment. @endif</p>
    @endif

    <div class="sign">For {{ $issuer['name'] }}<br><br><br><span class="muted">This is a computer-generated document and needs no signature.</span></div>
</body>
</html>
