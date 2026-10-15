<?php

namespace App\Http\Controllers;

use App\Support\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Landing page after login: the dashboard when allowed, otherwise the first screen in the user's menu
 * (e.g. Deliveries for a delivery boy), otherwise the profile page.
 */
class HomeController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        if ($request->user()->can('dashboard.view')) {
            return redirect()->route('dashboard');
        }

        foreach (Menu::for($request->user()) as $entry) {
            $url = $entry['url'] ?? $entry['items'][0]['url'] ?? null;

            if ($url !== null) {
                return redirect()->to($url);
            }
        }

        return redirect()->route('profile.edit');
    }
}
