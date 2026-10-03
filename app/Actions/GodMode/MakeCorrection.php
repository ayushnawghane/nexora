<?php

namespace App\Actions\GodMode;

use App\GodMode\Editor;
use App\Models\GodModeChange;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MakeCorrection
{
    /**
     * Applies a God Mode correction: refuses it if the record changed since the screen was loaded,
     * validates it with the normal form's rules, saves it through the normal action and records the
     * values before and after with the reason. Everything happens in one DB transaction.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(Editor $editor, Model $record, array $input, string $reason, string $fingerprint, User $actor): GodModeChange
    {
        return DB::transaction(function () use ($editor, $record, $input, $reason, $fingerprint, $actor) {
            /** @var Model $locked */
            $locked = $record->newQuery()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            if (! hash_equals($editor->fingerprint($locked), $fingerprint)) {
                throw ValidationException::withMessages(['fingerprint' => 'This record changed after you opened it. Reload the page and make the correction again.']);
            }

            $before = $editor->values($locked);
            $validated = $editor->validate($locked, $input, $actor);
            $editor->apply($locked, $validated, $actor);
            $after = $editor->values($locked->refresh());

            if (self::same($before, $after)) {
                throw ValidationException::withMessages(['fingerprint' => 'Nothing changed. Edit a value before saving.']);
            }

            return GodModeChange::query()->create([
                'user_id' => $actor->id,
                'editor' => $editor->key(),
                'subject_type' => $locked::class,
                'subject_id' => $locked->getKey(),
                'transaction_id' => $editor->transactionId($locked),
                'company_id' => $editor->companyId($locked),
                'reason' => trim($reason),
                'before' => $before,
                'after' => $after,
                'can_roll_back' => $editor->canRollBack($before),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    public static function same(array $a, array $b): bool
    {
        return json_encode(self::normalise($a)) === json_encode(self::normalise($b));
    }

    /**
     * Round-trips through JSON (as the change log stores values) and sorts object keys, because
     * MySQL's JSON column reorders keys: a stored "after" must still match a fresh read.
     */
    private static function normalise(mixed $value): mixed
    {
        $value = json_decode((string) json_encode($value), true);
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn ($v) => self::normalise($v), $value);
    }
}
