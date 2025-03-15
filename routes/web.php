<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelMarkdown\MarkdownRenderer;

Route::get('/', function () {
    // For dev
    // Cache::forget('docs.homepage');

    $html = Cache::remember('docs.homepage', 86400, function () {
        $markdownContent = File::get(resource_path('markdown/documentation.md'));
        $renderer = app(MarkdownRenderer::class);

        return $renderer->toHtml($markdownContent);
    });

    return view('docs', ['markdownHtml' => $html]);
});
