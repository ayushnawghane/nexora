<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\Setting;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * The PDF of an invoice (resources/views/invoices/document.blade.php), kept on the private disk.
 * It's rendered when the invoice is issued and again when it's cancelled (stamped CANCELLED).
 */
class InvoiceRenderer
{
    public function html(Invoice $invoice): string
    {
        $invoice->loadMissing(['lines', 'placeOfSupply', 'parent', 'transaction']);

        return view('invoices.document', [
            'invoice' => $invoice,
            'parent' => $invoice->parent,
            'deal' => $invoice->transaction,
            'issuer' => config('billing.issuer'),
            'beaconGstin' => Setting::value(Setting::BEACON_GSTIN),
            'qr' => $invoice->signed_qr ? $this->qr($invoice->signed_qr) : null,
        ])->render();
    }

    /** Renders and stores the PDF; returns its path on the local disk. */
    public function store(Invoice $invoice): string
    {
        $path = "invoices/{$invoice->transaction()->value('ulid')}/{$invoice->ulid}.pdf";
        Storage::disk('local')->put($path, Pdf::loadHTML($this->html($invoice))->setPaper('a4')->output());

        return $path;
    }

    private function qr(string $payload): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle(220, 1), new SvgImageBackEnd)))->writeString($payload);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
