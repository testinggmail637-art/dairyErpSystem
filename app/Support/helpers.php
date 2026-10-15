<?php

if (! function_exists('asset_v')) {
    /**
     * Asset URL with a cache-busting version taken from the file's modification time.
     * Example: asset_v('css/app.css') → http://…/css/app.css?v=1760512345
     */
    function asset_v(string $path): string
    {
        $file = public_path($path);

        return asset($path).(is_file($file) ? '?v='.filemtime($file) : '');
    }
}
