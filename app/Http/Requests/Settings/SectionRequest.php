<?php

namespace App\Http\Requests\Settings;

class SectionRequest extends MasterDataRequest
{
    protected function table(): string
    {
        return 'sections';
    }

    protected function routeParameter(): string
    {
        return 'section';
    }
}
