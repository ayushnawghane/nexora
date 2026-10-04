<?php

namespace App\Http\Controllers\Deals;

use App\Enums\IsinPaymentStatus;
use App\Exports\IsinsExport;
use App\Http\Controllers\Controller;
use App\Models\DealIsin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Every ISIN across deals, with what's due next (Stack's ISIN MIS), and its Excel export. */
class IsinController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('deals.view');

        $nextDue = AllowedSort::callback('next_due_on', fn (Builder $q, bool $descending) => self::byNextDue($q, $descending));
        $isins = QueryBuilder::for(self::query())
            ->allowedFilters(
                AllowedFilter::callback('search', fn (Builder $q, mixed $value) => self::search($q, (string) $value)),
                AllowedFilter::callback('due', fn (Builder $q, mixed $value) => self::due($q, (string) $value)),
            )
            ->allowedSorts('maturity_date', 'isin', $nextDue)
            ->defaultSort($nextDue)
            ->paginate($request->integer('per_page', 25) > 0 ? min($request->integer('per_page', 25), 100) : 25)
            ->withQueryString()
            ->through(fn (DealIsin $i) => [
                'id' => $i->ulid,
                'isin' => $i->isin,
                'series_name' => $i->series_name,
                'deal_id' => $i->transaction->ulid,
                'company' => $i->transaction->company->name,
                'el_number' => $i->transaction->el_number,
                'maturity_date' => $i->maturity_date?->toDateString(),
                'coupon' => $i->coupon_rate !== null ? rtrim(rtrim($i->coupon_rate, '0'), '.').'%' : $i->coupon_type?->label(),
                'next_due_on' => $i->getAttribute('next_due_on'),
                'overdue' => (int) $i->getAttribute('overdue_count'),
            ]);

        return Inertia::render('Isins/Index', [
            'isins' => $isins,
            'filters' => [
                'search' => $request->input('filter.search', ''),
                'due' => $request->input('filter.due', ''),
            ],
            'sort' => $request->input('sort', 'next_due_on'),
            'exportUrl' => route('isins.export', ['filter' => array_filter([
                'search' => $request->input('filter.search'),
                'due' => $request->input('filter.due'),
            ])]),
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $this->authorize('deals.view');
        $query = self::byNextDue(self::query(), false)->orderBy('deal_isins.id');
        self::search($query, trim((string) $request->input('filter.search')));
        self::due($query, (string) $request->input('filter.due'));

        return Excel::download(new IsinsExport($query), 'isins-'.now()->format('Y-m-d').'.xlsx');
    }

    /**
     * ISINs with their next due date and overdue count.
     *
     * @return Builder<DealIsin>
     */
    public static function query(): Builder
    {
        return DealIsin::query()
            ->with(['transaction:id,ulid,el_number,company_id', 'transaction.company:id,name'])
            ->withMin(['payments as next_due_on' => fn (Builder $q) => $q->where('status', IsinPaymentStatus::Due)], 'due_on')
            ->withExists(['payments as has_due' => fn (Builder $q) => $q->where('status', IsinPaymentStatus::Due)])
            ->withCount(['payments as overdue_count' => fn (Builder $q) => $q->where('status', IsinPaymentStatus::Due)->whereDate('due_on', '<', today())]);
    }

    /**
     * ISINs with something still due come first (MySQL would put the empty dates first), soonest
     * or latest next due date as asked.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private static function byNextDue(Builder $query, bool $descending): Builder
    {
        return $query->orderByDesc('has_due')->orderBy('next_due_on', $descending ? 'desc' : 'asc');
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private static function search(Builder $query, string $term): void
    {
        if ($term === '') {
            return;
        }
        $like = '%'.$term.'%';
        $query->where(fn (Builder $q) => $q
            ->where('isin', 'like', $like)
            ->orWhere('series_name', 'like', $like)
            ->orWhereHas('transaction', fn (Builder $t) => $t->where('el_number', 'like', $like)
                ->orWhereHas('company', fn (Builder $c) => $c->where('name', 'like', $like))));
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private static function due(Builder $query, string $value): void
    {
        match ($value) {
            'overdue' => $query->whereHas('payments', fn (Builder $q) => $q->where('status', IsinPaymentStatus::Due)->whereDate('due_on', '<', today())),
            'next30' => $query->whereHas('payments', fn (Builder $q) => $q->where('status', IsinPaymentStatus::Due)->whereBetween('due_on', [today()->toDateString(), today()->addDays(30)->toDateString()])),
            default => null,
        };
    }
}
