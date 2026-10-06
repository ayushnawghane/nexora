<?php

namespace App\GodMode\Editors;

use App\Enums\InvoiceStatus;
use App\GodMode\Editor;
use App\Models\Invoice;
use App\Models\State;
use App\Models\User;
use App\Services\Billing\InvoiceRenderer;
use App\Support\IndianIdentifiers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Corrects an invoice's heading, including an issued one (the normal screens never change those):
 * its number and date, who it's billed to, SAC, IRN details and notes. Amounts are corrected line
 * by line (InvoiceLineEditor); status changes go through the normal issue / cancel actions. An
 * issued invoice's PDF is rendered again afterwards.
 *
 * @extends Editor<Invoice>
 */
class InvoiceEditor extends Editor
{
    public function key(): string
    {
        return 'invoice';
    }

    public function label(): string
    {
        return 'Invoice';
    }

    public function modelClass(): string
    {
        return Invoice::class;
    }

    public function values(Model $record): array
    {
        return [
            'number' => $record->number,
            'invoice_date' => $record->invoice_date?->toDateString(),
            'billed_name' => $record->billed_name,
            'billed_address' => $record->billed_address,
            'billed_gstin' => $record->billed_gstin,
            'place_of_supply_state_id' => $record->place_of_supply_state_id,
            'sac' => $record->sac,
            'irn' => $record->irn,
            'ack_no' => $record->ack_no,
            'notes' => $record->notes,
            'cancel_reason' => $record->cancel_reason,
        ];
    }

    public function fields(Model $record): array
    {
        $issued = $record->status !== InvoiceStatus::Draft;

        return array_values(array_filter([
            $issued ? ['name' => 'number', 'label' => 'Number', 'type' => 'text', 'required' => true] : null,
            $issued ? ['name' => 'invoice_date', 'label' => 'Date', 'type' => 'date', 'required' => true] : null,
            ['name' => 'billed_name', 'label' => 'Billed to', 'type' => 'text', 'required' => true],
            ['name' => 'billed_address', 'label' => 'Address', 'type' => 'textarea', 'required' => true],
            ['name' => 'billed_gstin', 'label' => 'GSTIN', 'type' => 'text'],
            ['name' => 'place_of_supply_state_id', 'label' => 'Place of supply', 'type' => 'select', 'required' => true,
                'options' => State::query()->orderBy('name')->get(['id', 'name'])->map(fn (State $s) => ['value' => $s->id, 'label' => $s->name])->all()],
            ['name' => 'sac', 'label' => 'SAC', 'type' => 'text'],
            $issued ? ['name' => 'irn', 'label' => 'IRN', 'type' => 'text'] : null,
            $issued ? ['name' => 'ack_no', 'label' => 'Ack no.', 'type' => 'text'] : null,
            ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
            $record->status === InvoiceStatus::Cancelled ? ['name' => 'cancel_reason', 'label' => 'Cancel reason', 'type' => 'textarea', 'required' => true] : null,
        ]));
    }

    public function validate(Model $record, array $input, User $actor): array
    {
        $input = array_map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v, $input);
        if (isset($input['billed_gstin'])) {
            $input['billed_gstin'] = strtoupper($input['billed_gstin']);
        }
        $issued = $record->status !== InvoiceStatus::Draft;

        $validated = Validator::make($input, [
            'number' => [$issued ? 'required' : 'prohibited', 'string', 'max:40', Rule::unique('invoices', 'number')->where('kind', $record->kind->value)->ignore($record->id)],
            'invoice_date' => [$issued ? 'required' : 'prohibited', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:today'],
            'billed_name' => ['required', 'string', 'max:255'],
            'billed_address' => ['required', 'string', 'max:2000'],
            'billed_gstin' => ['nullable', 'string', 'size:15', function (string $attribute, mixed $value, \Closure $fail) {
                if (! IndianIdentifiers::isGstin((string) $value)) {
                    $fail('Enter a valid GSTIN.');
                }
            }],
            'place_of_supply_state_id' => ['required', 'integer', Rule::exists('states', 'id')],
            'sac' => ['nullable', 'digits_between:4,8'],
            'irn' => ['nullable', 'string', 'size:64', Rule::unique('invoices', 'irn')->ignore($record->id)],
            'ack_no' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'cancel_reason' => [$record->status === InvoiceStatus::Cancelled ? 'required' : 'nullable', 'string', 'max:2000'],
        ])->validate();

        return array_merge(['number' => null, 'invoice_date' => null, 'billed_gstin' => null, 'sac' => null, 'irn' => null, 'ack_no' => null, 'notes' => null, 'cancel_reason' => null], $validated);
    }

    public function apply(Model $record, array $validated, User $actor): void
    {
        $record->update($validated);
        if ($record->pdf_path !== null) {
            $record->forceFill(['pdf_path' => app(InvoiceRenderer::class)->store($record->refresh())])->save();
        }
    }

    public function transactionId(Model $record): int
    {
        return $record->transaction_id;
    }
}
