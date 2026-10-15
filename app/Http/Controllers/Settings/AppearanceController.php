<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateAppearanceRequest;
use App\Services\AppearanceService;
use App\Support\CurrentFarm;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Settings → Appearance: branding, colours, typography, layout, login page and print settings with live preview.
 */
class AppearanceController extends Controller
{
    public function __construct(
        private readonly AppearanceService $appearance,
        private readonly CurrentFarm $currentFarm,
    ) {}

    public function edit(): View
    {
        $stored = $this->appearance->stored($this->currentFarm->farm());

        return view('settings.appearance.edit', [
            'values' => $stored,
            'presets' => AppearanceService::PRESETS,
            'fonts' => AppearanceService::FONTS,
            'defaults' => AppearanceService::DEFAULTS,
            'warnings' => $this->appearance->contrastWarnings(array_replace($stored, [
                'pdf_accent_color' => $stored['pdf_accent_color'] ?? $stored['primary_color'],
            ])),
        ]);
    }

    public function update(UpdateAppearanceRequest $request): RedirectResponse
    {
        $uploads = collect(array_keys(AppearanceService::UPLOADS))
            ->mapWithKeys(fn (string $key) => [$key => $request->file($key)])
            ->all();

        try {
            $this->appearance->save($this->currentFarm->farm(), $request->settings(), $uploads, $request->input('remove', []));
        } catch (InvalidArgumentException $e) {
            // e.g. an SVG that cannot be made safe
            return back()->withInput()->withErrors(['logo' => $e->getMessage()]);
        }

        $redirect = redirect()->route('settings.appearance.edit')->with('success', __('Appearance saved.'));
        $warnings = $this->appearance->contrastWarnings($this->appearance->get($this->currentFarm->farm()->fresh()));

        return $warnings ? $redirect->with('warning', implode(' ', $warnings)) : $redirect;
    }

    public function reset(): RedirectResponse
    {
        $this->appearance->reset($this->currentFarm->farm());

        return redirect()->route('settings.appearance.edit')->with('success', __('Appearance reset to the defaults.'));
    }

    /** A sample PDF with the current print branding (header, accent colour, footer). */
    public function samplePdf(): Response
    {
        return Pdf::loadView('pdf.appearance-sample', ['farm' => $this->currentFarm->farm()])
            ->setPaper('a4')
            ->stream('appearance-sample.pdf');
    }
}
