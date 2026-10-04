<?php

namespace App\Actions\Security;

use App\Actions\Documents\StoresDocumentFiles;
use App\Enums\RegistrationAction;
use App\Enums\RegistrationStatus;
use App\Models\SecurityRegistration;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class RecordRegistrationEvent
{
    use StoresDocumentFiles;

    /**
     * Records a modification or the satisfaction (for a pledge: the release) of an active
     * registration, with its filing documents. A modification needs a reason and can change the
     * amount; satisfaction is final. Satisfaction can be recorded after the deal closes.
     *
     * @param  list<UploadedFile>  $files
     */
    public function handle(SecurityRegistration $registration, RegistrationAction $action, CarbonImmutable $on, ?string $filingReference, ?string $amount, ?string $reason, array $files, User $actor): SecurityRegistration
    {
        if ($action === RegistrationAction::Create) {
            throw ValidationException::withMessages(['action' => 'Record a modification or the satisfaction.']);
        }

        return $this->withFiles(function () use ($registration, $action, $on, $filingReference, $amount, $reason, $files, $actor) {
            $deal = Transaction::query()->whereKey($registration->transaction_id)->lockForUpdate()->firstOrFail();
            $locked = SecurityRegistration::query()->whereKey($registration->id)->lockForUpdate()->firstOrFail();

            if ($action === RegistrationAction::Modify && ! $deal->isOpenDeal()) {
                throw ValidationException::withMessages(['action' => 'A registration of a closed deal can only be satisfied.']);
            }
            if ($locked->status !== RegistrationStatus::Active) {
                throw ValidationException::withMessages(['action' => "This {$locked->kind->label()} is already {$locked->status->label($locked->kind)}."]);
            }
            if ($on->isAfter(today())) {
                throw ValidationException::withMessages(['happened_on' => 'The date can\'t be in the future.']);
            }
            $last = $locked->events()->reorder()->latest('happened_on')->value('happened_on');
            if ($last && $on->isBefore(CarbonImmutable::parse($last))) {
                throw ValidationException::withMessages(['happened_on' => 'The date can\'t be before the registration\'s last filing ('.CarbonImmutable::parse($last)->format('d M Y').').']);
            }
            if ($action === RegistrationAction::Modify && trim((string) $reason) === '') {
                throw ValidationException::withMessages(['reason' => 'Say what was modified and why.']);
            }

            $event = $locked->events()->create([
                'action' => $action,
                'happened_on' => $on,
                'filing_reference' => $filingReference,
                'amount' => $amount,
                'reason' => trim((string) $reason) ?: null,
                'created_by' => $actor->id,
            ]);
            foreach ($files as $file) {
                $this->attach($event, $deal, 'registrations', $file, $actor);
            }

            $locked->update(array_filter([
                'status' => $action === RegistrationAction::Satisfy ? RegistrationStatus::Satisfied : null,
                'amount' => $action === RegistrationAction::Modify ? $amount : null,
            ], fn ($v) => $v !== null));

            return $locked;
        });
    }
}
