<?php

namespace App\Http\Controllers\Transactions;

use App\Enums\TransactionStatus;
use App\Exports\TransactionsExport;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Transaction lists by stage. The wizard (TransactionWizardController) handles editing drafts. */
class TransactionController extends Controller
{
    /** List name => [title, description, statuses shown]. */
    private const LISTS = [
        'drafts' => ['Drafts', 'Transactions being prepared, and rejected ones sent back for changes.', [TransactionStatus::Draft, TransactionStatus::Rejected]],
        'pending' => ['Pending approval', 'Transactions waiting for approvers.', [TransactionStatus::PendingApproval]],
        'approved' => ['Approved', 'Approved transactions whose engagement letter hasn\'t been issued yet.', [TransactionStatus::Approved]],
    ];

    public function drafts(Request $request): Response
    {
        return $this->list($request, 'drafts');
    }

    public function pending(Request $request): Response
    {
        return $this->list($request, 'pending');
    }

    public function approved(Request $request): Response
    {
        return $this->list($request, 'approved');
    }

    public function active(Request $request): Response
    {
        return $this->list($request, 'active');
    }

    public function closed(Request $request): Response
    {
        return $this->list($request, 'closed');
    }

    /** Excel download of a list, with the same search applied. */
    public function export(Request $request, string $list): BinaryFileResponse
    {
        $this->authorize('viewAny', Transaction::class);
        abort_unless(isset(self::LISTS[$list]), 404);

        return Excel::download(
            new TransactionsExport(self::LISTS[$list][2], trim((string) $request->input('filter.search')) ?: null),
            "transactions-{$list}-".now()->format('Y-m-d').'.xlsx',
        );
    }

    private function list(Request $request, string $list): Response
    {
        $this->authorize('viewAny', Transaction::class);
        [$title, $description, $statuses] = self::LISTS[$list];

        $transactions = QueryBuilder::for(
            Transaction::query()
                ->inStatus(...$statuses)
                ->with(['company:id,name', 'issueDetail:id,transaction_id,total_issue_size', 'relationshipManager:id,name'])
        )
            ->allowedFilters(AllowedFilter::callback('search', function (Builder $query, mixed $value) {
                $term = '%'.trim((string) $value).'%';
                $query->where(fn (Builder $q) => $q
                    ->where('el_number', 'like', $term)
                    ->orWhereHas('company', fn (Builder $c) => $c->where('name', 'like', $term)->orWhere('cin', 'like', $term)));
            }))
            ->allowedSorts('created_at', 'updated_at', 'submitted_at')
            ->defaultSort('-updated_at')
            ->paginate($request->integer('per_page', 25) > 0 ? min($request->integer('per_page', 25), 100) : 25)
            ->withQueryString()
            ->through(fn (Transaction $t) => [
                'id' => $t->ulid,
                'company' => $t->company->name,
                'el_number' => $t->el_number,
                'status' => $t->status->value,
                'status_label' => $t->status->label(),
                'status_tone' => $t->status->tone(),
                'issue_size' => $t->issueDetail?->total_issue_size,
                'relationship_manager' => $t->relationshipManager?->name,
                'updated_at' => $t->updated_at?->toIso8601String(),
                'editable' => $t->status->isEditable(),
            ]);

        return Inertia::render('Transactions/Index', [
            'list' => $list,
            'exportUrl' => route('transactions.export', ['list' => $list, 'filter' => ['search' => $request->input('filter.search')]]),
            'title' => $title,
            'description' => $description,
            'transactions' => $transactions,
            'filters' => ['search' => $request->input('filter.search', '')],
            'sort' => $request->input('sort', '-updated_at'),
            'can' => ['create' => $request->user()->can('create', Transaction::class)],
        ]);
    }
}
