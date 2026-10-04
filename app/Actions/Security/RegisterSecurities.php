<?php

namespace App\Actions\Security;

use App\Actions\Documents\StoresDocumentFiles;
use App\Enums\RegistrationAction;
use App\Enums\RegistrationKind;
use App\Enums\RegistrationStatus;
use App\Models\DealSecurity;
use App\Models\SecurityRegistration;
use App\Models\SecurityRegistrationEvent;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class RegisterSecurities
{
    use StoresDocumentFiles;

    /**
     * Records a new ROC charge, CERSAI registration or pledge over some of the deal's securities,
     * with its filing documents. Each security can be in one active registration of each kind.
     *
     * @param  list<int>  $securityIds
     * @param  array<string, mixed>  $details  reference, amount and (pledges) the depository details
     * @param  list<UploadedFile>  $files
     */
    public function handle(Transaction $deal, RegistrationKind $kind, array $securityIds, CarbonImmutable $on, ?string $filingReference, array $details, array $files, User $actor): SecurityRegistration
    {
        return $this->withFiles(function () use ($deal, $kind, $securityIds, $on, $filingReference, $details, $files, $actor) {
            /** @var Transaction $locked */
            $locked = Transaction::query()->whereKey($deal->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isOpenDeal()) {
                throw ValidationException::withMessages(['security_ids' => 'The securities of a closed deal can\'t be registered.']);
            }
            if ($on->isAfter(today())) {
                throw ValidationException::withMessages(['happened_on' => 'The date can\'t be in the future.']);
            }

            $securities = $locked->securities()->whereKey($securityIds)->get();
            if ($securities->count() !== count(array_unique($securityIds))) {
                throw ValidationException::withMessages(['security_ids' => 'Some of the chosen securities aren\'t on this deal.']);
            }
            $wrong = $securities->reject(fn (DealSecurity $s) => in_array($kind, $s->nature->registrationKinds(), true));
            if ($wrong->isNotEmpty()) {
                throw ValidationException::withMessages(['security_ids' => "These can't have a {$kind->label()}: ".$wrong->map->summary()->implode('; ').'.']);
            }
            $taken = $securities->filter(fn (DealSecurity $s) => $s->registrations()->where('kind', $kind)->where('status', RegistrationStatus::Active)->exists());
            if ($taken->isNotEmpty()) {
                throw ValidationException::withMessages(['security_ids' => "Already in an active {$kind->label()}: ".$taken->map->summary()->implode('; ').'.']);
            }

            $registration = $locked->registrations()->create([
                ...$details,
                'kind' => $kind,
                'status' => RegistrationStatus::Active,
                'created_by' => $actor->id,
            ]);
            $registration->securities()->sync($securities->modelKeys());

            $event = $registration->events()->create([
                'action' => RegistrationAction::Create,
                'happened_on' => $on,
                'filing_reference' => $filingReference,
                'amount' => $details['amount'] ?? null,
                'created_by' => $actor->id,
            ]);
            $this->attachAll($event, $locked, $files, $actor);

            return $registration;
        });
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    private function attachAll(SecurityRegistrationEvent $event, Transaction $deal, array $files, User $actor): void
    {
        foreach ($files as $file) {
            $this->attach($event, $deal, 'registrations', $file, $actor);
        }
    }
}
