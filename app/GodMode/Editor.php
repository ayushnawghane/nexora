<?php

namespace App\GodMode;

use App\Models\User;
use App\Support\FormRequestRules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One kind of record God Mode can correct. An editor exposes the record's values in the same shape
 * the normal form submits, validates a correction with the normal form's FormRequest rules (minus its
 * permission check), and saves it through the same action the normal screen uses. The values before
 * and after are what the change log stores and what a rollback re-applies.
 *
 * @template TModel of Model
 */
abstract class Editor
{
    abstract public function key(): string;

    abstract public function label(): string;

    /**
     * @return class-string<TModel>
     */
    abstract public function modelClass(): string;

    /**
     * The record's current values, shaped like the normal form's input.
     *
     * @param  TModel  $record
     * @return array<string, mixed>
     */
    abstract public function values(Model $record): array;

    /**
     * Form fields for the generic editor (see resources/js/Components/god-mode/editor-form.jsx).
     *
     * @param  TModel  $record
     * @return list<array<string, mixed>>
     */
    abstract public function fields(Model $record): array;

    /**
     * Saves validated values through the normal action. Runs inside the change's DB transaction.
     *
     * @param  array<string, mixed>  $validated
     * @param  TModel  $record
     */
    abstract public function apply(Model $record, array $validated, User $actor): void;

    /**
     * The FormRequest whose rules a correction must pass, or null when validate() is overridden.
     *
     * @return class-string<FormRequest>|null
     */
    protected function request(): ?string
    {
        return null;
    }

    /**
     * Route parameters the FormRequest reads with $this->route(...).
     *
     * @param  TModel  $record
     * @return array<string, mixed>
     */
    protected function routeParameters(Model $record): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  TModel  $record
     * @return array<string, mixed>
     */
    public function validate(Model $record, array $input, User $actor): array
    {
        return FormRequestRules::validate((string) $this->request(), $input, $this->routeParameters($record), $actor);
    }

    /**
     * @return TModel
     */
    public function find(string $id): Model
    {
        $class = $this->modelClass();

        return $class::query()->findOrFail($id);
    }

    /**
     * @param  TModel  $record
     */
    public function transactionId(Model $record): ?int
    {
        return null;
    }

    /**
     * @param  TModel  $record
     */
    public function companyId(Model $record): ?int
    {
        return null;
    }

    /**
     * Whether a change can be undone by re-applying its old values (they must form a complete,
     * valid input; e.g. "no billing yet" can't be re-applied).
     *
     * @param  array<string, mixed>  $before
     */
    public function canRollBack(array $before): bool
    {
        return true;
    }

    /** Whether the record page lists the current values (off when they'd read as a proposal). */
    public function showsSummary(): bool
    {
        return true;
    }

    /**
     * A fingerprint of the current values. A correction or rollback is refused when the record has
     * changed since the screen was loaded (someone else edited it in between).
     *
     * @param  TModel  $record
     */
    public function fingerprint(Model $record): string
    {
        return hash('sha256', (string) json_encode($this->values($record)));
    }
}
