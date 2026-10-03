<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Http\Requests\Masters\MasterRequest;
use App\Support\Masters\MasterRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class MasterController extends Controller
{
    public function __construct(private readonly MasterRegistry $registry) {}

    public function index(Request $request): Response
    {
        $this->authorizeView($request);

        return Inertia::render('Masters/Index', [
            'groups' => $this->registry->grouped(),
        ]);
    }

    public function show(Request $request, string $master): Response
    {
        $this->authorizeView($request);
        $definition = $this->registry->get($master);
        $searchable = $definition->searchable();

        $records = QueryBuilder::for($definition->query())
            ->allowedFilters(
                AllowedFilter::callback('search', function (Builder $query, mixed $value) use ($searchable) {
                    $term = '%'.trim((string) $value).'%';
                    $query->where(function (Builder $q) use ($searchable, $term) {
                        foreach ($searchable as $column) {
                            $q->orWhere($column, 'like', $term);
                        }
                    });
                }),
                AllowedFilter::exact('is_active'),
            )
            ->allowedSorts(...$definition->sortable())
            ->defaultSort($definition->defaultSort())
            ->paginate(25)
            ->withQueryString()
            ->through(fn ($record) => $definition->toRow($record));

        return Inertia::render('Masters/Show', [
            'master' => [
                'key' => $definition->key,
                'label' => $definition->label(),
                'singular' => $definition->singular(),
            ],
            'schema' => $definition->formSchema(),
            'records' => $records,
            'filters' => [
                'search' => $request->input('filter.search', ''),
                'is_active' => $request->input('filter.is_active', ''),
            ],
            'sort' => $request->input('sort', $definition->defaultSort()),
            'can' => ['manage' => $request->user()->can('masters.manage')],
        ]);
    }

    public function store(MasterRequest $request, string $master): RedirectResponse
    {
        $definition = $request->definition();
        $model = $definition->modelClass();

        DB::transaction(fn () => $definition->save(new $model, $request->validated()));

        return back()->with('success', ucfirst($definition->singular()).' added.');
    }

    public function update(MasterRequest $request, string $master, string $record): RedirectResponse
    {
        $definition = $request->definition();
        $model = $request->record();

        DB::transaction(fn () => $definition->save($model, $request->validated()));

        return back()->with('success', ucfirst($definition->singular()).' saved.');
    }

    public function toggle(Request $request, string $master, string $record): RedirectResponse
    {
        $this->authorizeManage($request);
        $definition = $this->registry->get($master);
        $model = $definition->query()->findOrFail($record);

        $model->setAttribute('is_active', ! $model->getAttribute('is_active'))->save();

        return back()->with('success', ucfirst($definition->singular()).($model->getAttribute('is_active') ? ' activated.' : ' deactivated.'));
    }

    public function destroy(Request $request, string $master, string $record): RedirectResponse
    {
        $this->authorizeManage($request);
        $definition = $this->registry->get($master);
        $model = $definition->query()->findOrFail($record);

        foreach ($definition->dependents() as $relation) {
            if ($model->{$relation}()->exists()) {
                return back()->with('error', 'This '.$definition->singular().' is in use and can\'t be deleted. Deactivate it instead.');
            }
        }

        $model->delete();

        return back()->with('success', ucfirst($definition->singular()).' deleted.');
    }

    private function authorizeView(Request $request): void
    {
        abort_unless($request->user()->can('masters.view'), 403);
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()->can('masters.manage'), 403);
    }
}
