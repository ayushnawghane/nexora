<?php

namespace App\GodMode;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Choice lists for the generic editor's select fields. */
class Options
{
    /**
     * @param  class-string<\BackedEnum>  $enum
     * @return list<array{value: string, label: string}>
     */
    public static function enum(string $enum): array
    {
        /** @var list<array{value: string, label: string}> $options the HasOptions trait every app enum uses */
        $options = call_user_func([$enum, 'options']);

        return $options;
    }

    /**
     * Active records plus any already-chosen inactive one, so a correction can keep the current value.
     *
     * @template T of Model
     *
     * @param  Builder<T>  $query
     * @param  list<int|null>  $current
     * @return list<array{value: int, label: string}>
     */
    public static function records(Builder $query, string $label, array $current = []): array
    {
        $current = array_values(array_filter($current));

        return $query
            ->where(fn (Builder $q) => $q->where('is_active', true)->when($current !== [], fn (Builder $q) => $q->orWhereIn('id', $current)))
            ->orderBy($label)
            ->get(['id', $label])
            ->map(fn (Model $m) => ['value' => (int) $m->getKey(), 'label' => (string) $m->getAttribute($label)])
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $values
     * @return list<array{value: string, label: string}>
     */
    public static function plain(array $values): array
    {
        return array_map(fn (string $v) => ['value' => $v, 'label' => $v], $values);
    }
}
