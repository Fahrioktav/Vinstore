@php
$icons = [
'phone' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="'.$class.'">
    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75v3a2.25 2.25 0 002.25 2.25h2.25a.75.75 0 01.75.75v2.25a.75.75 0 01-.75.75H4.5a2.25 2.25 0 00-2.25 2.25v3" />
</svg>',

'mail' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="'.$class.'">
    <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.5 4.5L18 8m0 0l-7.5 4.5L3 8m0 0v8.25a2.25 2.25 0 002.25 2.25h13.5A2.25 2.25 0 0021 16.25V8" />
</svg>',

'map' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="'.$class.'">
    <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A2.25 2.25 0 013 15.26V5.25A2.25 2.25 0 015.25 3h13.5A2.25 2.25 0 0121 5.25v10.01a2.25 2.25 0 01-1.553 2.136L15 20l-6-2.727z" />
</svg>',

'search' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6">
    <path fill-rule="evenodd" d="M10.5 3.75a6.75 6.75 0 1 0 0 13.5 6.75 6.75 0 0 0 0-13.5ZM2.25 10.5a8.25 8.25 0 1 1 14.59 5.28l4.69 4.69a.75.75 0 1 1-1.06 1.06l-4.69-4.69A8.25 8.25 0 0 1 2.25 10.5Z" clip-rule="evenodd" />
</svg>',

'user' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6">
    <path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0ZM3.751 20.105a8.25 8.25 0 0 1 16.498 0 .75.75 0 0 1-.437.695A18.683 18.683 0 0 1 12 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 0 1-.437-.695Z" clip-rule="evenodd" />
</svg>',

];
@endphp

{!! $icons[$name] ?? '' !!}