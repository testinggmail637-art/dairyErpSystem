<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

abstract class Controller
{
    /** Header sent by public/js/app.js when a list is filtered or paged without a page reload. */
    public const AJAX_LIST_HEADER = 'X-Ajax-List';

    /**
     * A list page: the full page normally; only its @fragment('results') (table + pagination) for AJAX
     * filter/paging requests from forms with data-ajax-list. The fragment response is marked with
     * X-Ajax-List-Fragment so the browser can tell it apart from a redirected full page.
     *
     * @param  array<string, mixed>  $data
     */
    protected function listView(Request $request, string $view, array $data): View|Response
    {
        if (! $request->hasHeader(self::AJAX_LIST_HEADER)) {
            return view($view, $data);
        }

        return response(view($view, $data)->fragment('results'))
            ->header('X-Ajax-List-Fragment', '1')
            ->header('Vary', self::AJAX_LIST_HEADER);
    }
}
