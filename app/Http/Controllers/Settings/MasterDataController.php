<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\MasterDataRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Shared list/create/edit for small farm master tables with name, code and active flag
 * (sections, payment modes). No delete: rows are deactivated so old records keep their reference.
 */
abstract class MasterDataController extends Controller
{
    /** @return class-string<Model> */
    abstract protected function model(): string;

    /** Route name prefix, e.g. "sections". */
    abstract protected function routePrefix(): string;

    /** @return array{plural: string, singular: string, icon: string} */
    abstract protected function labels(): array;

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        $rows = $this->model()::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        return view('settings.master-data.index', $this->viewData(['rows' => $rows, 'search' => $search]));
    }

    public function create(): View
    {
        return view('settings.master-data.form', $this->viewData(['row' => new ($this->model())(['is_active' => true])]));
    }

    protected function saveNew(MasterDataRequest $request): RedirectResponse
    {
        $row = $this->model()::create($request->validated());

        return redirect()->route($this->routePrefix().'.index')
            ->with('success', __(':item ":name" added.', ['item' => $this->labels()['singular'], 'name' => $row->name]));
    }

    protected function editRow(Model $row): View
    {
        return view('settings.master-data.form', $this->viewData(['row' => $row]));
    }

    protected function saveExisting(MasterDataRequest $request, Model $row): RedirectResponse
    {
        $row->update($request->validated());

        return redirect()->route($this->routePrefix().'.index')
            ->with('success', __(':item ":name" updated.', ['item' => $this->labels()['singular'], 'name' => $row->name]));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function viewData(array $data): array
    {
        return $data + ['labels' => $this->labels(), 'routePrefix' => $this->routePrefix()];
    }
}
