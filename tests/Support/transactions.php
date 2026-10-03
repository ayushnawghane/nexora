<?php

/*
 * Shared helpers for transaction tests: payloads for each wizard step and ready-made drafts.
 */

use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Auth\TwoFactor;
use Database\Seeders\PermissionSeeder;

function wizardUser(array $extra = []): User
{
    return signIn(permissions: ['transactions.view', 'transactions.create', ...$extra]);
}

function basicsPayload(array $overrides = []): array
{
    return array_merge([
        'company_id' => Company::factory()->create()->id,
        'vertical_team_id' => Transaction::factory()->make()->vertical_team_id,
        'relationship_manager_id' => User::factory()->create()->id,
        'origin' => 'bd',
        'brief' => '  NCD issue for working capital  ',
    ], $overrides);
}

function issuePayload(array $overrides = []): array
{
    return array_merge([
        'listing' => 'unlisted', 'issue_type' => 'private_placement', 'is_secured' => true, 'is_rated' => false,
        'base_issue_size' => '2,00,00,00,000', 'green_shoe_size' => '0', 'tenure_months' => 36, 'tenure_days' => 0,
        'instruments' => [['instrument' => 'ncd', 'base_amount' => '2000000000', 'green_shoe_amount' => '0']],
    ], $overrides);
}

function feesPayload(array $acceptance = [], array $service = []): array
{
    return ['fees' => [
        'acceptance' => array_merge([
            'enabled' => true, 'amount_type' => 'fixed', 'amount' => '65000', 'basis' => 'issue_size', 'frequency' => 'one_time',
            'start_reference' => 'el_date', 'start_date' => '2025-09-16', 'timing' => 'advance', 'escalation_type' => 'none',
        ], $acceptance),
        'service' => array_merge([
            'enabled' => true, 'amount_type' => 'fixed', 'amount' => '65000', 'basis' => 'issue_size', 'frequency' => 'annual',
            'start_reference' => 'dta_execution', 'start_date' => '2025-09-16', 'timing' => 'advance', 'escalation_type' => 'none',
        ], $service),
    ]];
}

/** A draft with contacts and issue details saved, ready for the fees step. */
function draftReadyForFees(): Transaction
{
    $transaction = Transaction::factory()->create();
    $contact = CompanyContact::factory()->for($transaction->company)->create();
    test()->put("/transactions/{$transaction->ulid}/contacts", ['contacts' => [['company_contact_id' => $contact->id, 'recipient' => 'to']]]);
    test()->put("/transactions/{$transaction->ulid}/issue", issuePayload())->assertSessionHasNoErrors();

    return $transaction->fresh();
}

/** A complete draft with a verified schedule, ready to send for approval. Needs a signed-in wizard user. */
function draftReadyForApproval(): Transaction
{
    $transaction = draftReadyForFees();
    test()->put("/transactions/{$transaction->ulid}/fees", feesPayload())->assertSessionHasNoErrors();
    test()->post("/transactions/{$transaction->ulid}/schedule/verify")->assertSessionHasNoErrors();

    return $transaction->fresh();
}

/** An active user holding the given approval permissions. */
function approver(bool $head = false): User
{
    test()->seed(PermissionSeeder::class);
    $user = User::factory()->create();
    $user->givePermissionTo(array_merge(['approvals.vote', 'transactions.view'], $head ? ['approvals.head'] : []));

    return $user;
}

/** Acts as an existing user who has already passed 2FA this session. */
function actAs(User $user)
{
    return test()->actingAs($user)->withSession([TwoFactor::SESSION_PASSED_AT => now()->getTimestamp()]);
}
