@php
    $containerParentClasses = match(config('devdojo.auth.appearance.alignment.container')){
        'left' => 'sm:ml-0 h-full',
        'center' => 'sm:mx-auto',
        'right' => 'sm:mr-0 h-full',
    };

    $containerClasses = match(config('devdojo.auth.appearance.alignment.container')){
        'left' => 'sm:border-r',
        'center' => 'sm:border sm:rounded-xl',
        'right' => 'sm:border-l',
    };
@endphp

<div id="auth-container-parent" class="relative w-full sm:max-w-md {{ $containerParentClasses }}">
    {{-- Fix: allow scrolling when form content exceeds viewport height --}}
    <style>
        body#auth-body { overflow-y: auto !important; height: auto !important; min-height: 100vh; }
        #auth-main-content { height: auto !important; min-height: 100vh; }
    </style>
    <div id="auth-container" class="flex relative top-0 z-20 flex-col justify-center items-stretch px-10 py-8 w-full min-h-screen bg-white border-gray-200 sm:top-auto sm:h-auto {{ $containerClasses }}">
        {{ $slot }}
    </div>
</div>
