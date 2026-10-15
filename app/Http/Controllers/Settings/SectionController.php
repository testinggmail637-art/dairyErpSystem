<?php

namespace App\Http\Controllers\Settings;

use App\Http\Requests\Settings\SectionRequest;
use App\Models\Section;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Settings → Sections. */
class SectionController extends MasterDataController
{
    protected function model(): string
    {
        return Section::class;
    }

    protected function routePrefix(): string
    {
        return 'sections';
    }

    protected function labels(): array
    {
        return ['plural' => __('Sections'), 'singular' => __('Section'), 'icon' => 'bi-diagram-3'];
    }

    public function store(SectionRequest $request): RedirectResponse
    {
        return $this->saveNew($request);
    }

    public function edit(Section $section): View
    {
        return $this->editRow($section);
    }

    public function update(SectionRequest $request, Section $section): RedirectResponse
    {
        return $this->saveExisting($request, $section);
    }
}
