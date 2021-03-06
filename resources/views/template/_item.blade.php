@extends('folio::layout-v2')

@php
    $item = $item ?? null;
    $css = '/folio/css/folio-light.css';

    $menu_data = ['items' => ['<i class="fa fa-pencil"></i>' => $item->editPath()]];
    $header_view = 'partial.c-header-getting-simple-v2';

    if ($item) {

        $title = $title ?? $item->title.' · '.config('folio.title');
        $og_type = $og_type ?? 'article';
        $og_url = $og_url ?? $item->permalink();
        $og_description = $og_description ?? $item->description();
        $og_image = $og_image ?? $item->ogImage();
        $collection = $collection ?? $item->collection();
        $google_fonts = $google_fonts ?? $item->propertyArray('google-font');
        $scripts = $scripts ?? $item->propertyArray('js');        
        $stylesheets = $stylesheets ?? $item->propertyArray('css');

    }
@endphp