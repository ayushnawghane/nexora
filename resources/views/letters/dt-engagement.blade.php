{{--
    Debenture Trustee engagement letter (draft wording, pending business sign-off: docs/PLAN.md §6).
    Rendered to HTML and stored with each letter version, then converted to PDF by dompdf, so keep
    the CSS simple (no flex/grid) and use DejaVu Sans, which dompdf ships with.
--}}
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Engagement Letter {{ $elNumber }}</title>
    <style>
        @page { margin: 28mm 18mm 22mm 18mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; line-height: 1.45; color: #111; }
        h1 { font-size: 14pt; letter-spacing: 0.08em; text-align: center; margin: 0 0 14px; }
        h2 { font-size: 11pt; margin: 22px 0 8px; }
        p { margin: 0 0 9px; }
        .meta td { padding: 1px 12px 1px 0; vertical-align: top; }
        .muted { color: #555; }
        table.grid { width: 100%; border-collapse: collapse; margin: 6px 0 10px; }
        table.grid th, table.grid td { border: 0.5pt solid #777; padding: 5px 6px; vertical-align: top; text-align: left; }
        table.grid th { background: #f0f0f0; font-size: 9pt; }
        .words { font-size: 8.5pt; color: #444; }
        .sign { margin-top: 26px; width: 100%; }
        .sign td { width: 50%; vertical-align: top; padding-right: 20px; }
        .page-break { page-break-before: always; }
        ol li { margin-bottom: 6px; }
    </style>
</head>
<body>
    <h1>ENGAGEMENT LETTER</h1>

    <table class="meta">
        <tr><td class="muted">EL No.</td><td><strong>{{ $elNumber }}</strong></td></tr>
        <tr><td class="muted">EL Date</td><td>{{ $elDate }}</td></tr>
        @if ($dealCode)
            <tr><td class="muted">Deal Code</td><td>{{ $dealCode }}</td></tr>
        @endif
    </table>

    <p style="margin-top: 14px">To,<br>
        <strong>{{ $company->name }}</strong><br>
        @if ($company->cin) CIN: {{ $company->cin }}<br> @endif
        @if ($registeredAddress) {{ $registeredAddress }} @endif
    </p>

    @if ($attention->isNotEmpty())
        <p>Kind attention: {{ $attention->implode('; ') }}</p>
    @endif

    <p><strong>Subject: Appointment of Beacon Trusteeship Limited as Debenture Trustee for the proposed issue of
        {{ $instruments }} aggregating up to {{ $issueSize }}.</strong></p>

    <p>Dear Sir / Madam,</p>

    <p>We refer to our discussions regarding the appointment of Beacon Trusteeship Limited ("Beacon") as
        Debenture Trustee for the proposed {{ strtolower($secured) }}, {{ strtolower($listing) }}
        {{ strtolower($issueType) }} of {{ $instruments }} by {{ $company->name }} (the "Company") of
        {{ $issueSize }} ({{ $issueSizeWords }})@if ($greenShoe), comprising a base issue of {{ $baseIssue }} and a
        green shoe option of {{ $greenShoe }}@endif, for a tenure of {{ $tenure }} (the "Issue").</p>

    <p>We are pleased to confirm our consent to act as Debenture Trustee for the Issue, subject to the terms set
        out in this letter and its annexures:</p>
    <p>Annexure I &ndash; Schedule of Fees<br>Annexure II &ndash; Terms of Engagement</p>

    <p>Kindly acknowledge this letter and return a signed copy by email or courier as a token of your
        acceptance. For any clarification, please contact
        {{ $relationshipManager?->name ?? 'your relationship manager' }}@if ($relationshipManager?->email) ({{ $relationshipManager->email }})@endif.</p>

    <table class="sign">
        <tr>
            <td>
                Yours faithfully,<br>
                For <strong>Beacon Trusteeship Limited</strong><br><br><br>
                Name: {{ $signatory?->name ?? '________________' }}<br>
                Designation: {{ $signatory?->designation?->name ?? 'Authorised Signatory' }}
            </td>
            <td>
                Accepted for <strong>{{ $company->name }}</strong><br><br><br><br>
                Name: ________________________<br>
                Designation: ___________________
            </td>
        </tr>
    </table>

    <div class="page-break"></div>
    <h2>Annexure I &ndash; Schedule of Fees</h2>
    <p class="muted">Product: Debenture Trustee &middot; EL No. {{ $elNumber }}</p>

    <table class="grid">
        <thead>
            <tr><th>Fee</th><th>Amount</th><th>Frequency</th><th>Effective from</th><th>Payment terms</th></tr>
        </thead>
        <tbody>
            @foreach ($fees as $fee)
                <tr>
                    <td>{{ $fee['label'] }}</td>
                    <td>
                        {{ $fee['amount'] }}
                        <div class="words">{{ $fee['words'] }}</div>
                        @if ($fee['basis']) <div class="words">({{ $fee['basis'] }})</div> @endif
                        @if ($fee['escalation']) <div class="words">Escalation: {{ $fee['escalation'] }}</div> @endif
                    </td>
                    <td>{{ $fee['frequency'] }}</td>
                    <td>{{ $fee['from'] }}</td>
                    <td>{{ $fee['timing'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="words">Recurring fees are charged per financial year (April to March); a part of a year is charged in
        proportion to the number of days. All fees are exclusive of applicable taxes, which will be charged
        additionally at the prevailing rates.</p>

    <h2>Annexure II &ndash; Terms of Engagement</h2>
    <ol>
        <li><strong>Appointment.</strong> Beacon shall act as Debenture Trustee for the Issue on the terms of this
            letter and the transaction documents to be executed for the Issue.</li>
        <li><strong>Fees and expenses.</strong> Fees are payable as set out in Annexure I. Out-of-pocket expenses
            incurred in connection with the Issue shall be reimbursed at actuals. Payments shall be made by bank
            transfer only.</li>
        <li><strong>Information.</strong> The Company shall provide all information and documents required by
            Beacon to perform its obligations under applicable law, including SEBI regulations.</li>
        <li><strong>Confidentiality.</strong> Beacon shall keep confidential all information received in connection
            with the Issue, except where disclosure is required by law or a regulator.</li>
        <li><strong>Validity.</strong> This letter remains valid until the Issue is fully redeemed and all
            obligations to the debenture holders are discharged, unless terminated earlier in accordance with the
            transaction documents.</li>
    </ol>
</body>
</html>
