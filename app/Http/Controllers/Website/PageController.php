<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class PageController extends Controller
{
    public function show(string $page): View
    {
        // Pages are designed one by one in 7A.3 / 7A.4; until then they share a simple placeholder.
        return view()->exists("website.pages.{$page}")
            ? view("website.pages.{$page}")
            : view('website.pages.placeholder', ['page' => $page]);
    }
}
