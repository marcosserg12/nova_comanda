<?php

it('defines the redesign tokens in the Tailwind theme', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    foreach ([
        '--color-nana-red: #D62828;',
        '--color-nana-cream: #FFF2D9;',
        '--color-nana-ink: #161616;',
        '--color-nana-yellow: #FFB703;',
        '--color-nana-green: #1F8A50;',
        '--color-nana-orange: #E8590C;',
        '--color-nana-muted: #6B6257;',
        '--color-nana-line: #E7D9C2;',
        "--font-sans: 'Archivo'",
        "--font-display: 'Archivo Black'",
        '--shadow-hard: 4px 4px 0 var(--color-nana-ink);',
        '--shadow-hard-sm: 2px 2px 0 var(--color-nana-ink);',
        '@keyframes nana-pulse',
        '@utility nana-highlight',
        'prefers-reduced-motion: reduce',
    ] as $token) {
        expect($css)->toContain($token);
    }
});

it('bundles the fonts instead of loading them from the internet', function () {
    $js = file_get_contents(resource_path('js/app.js'));

    expect($js)->toContain("import '@fontsource/archivo/500.css';")
        ->toContain("import '@fontsource/archivo/600.css';")
        ->toContain("import '@fontsource/archivo/700.css';")
        ->toContain("import '@fontsource/archivo/800.css';")
        ->toContain("import '@fontsource/archivo-black/400.css';")
        ->and(file_get_contents(base_path('vite.config.js')))->not->toContain('bunny')
        ->and(file_get_contents(resource_path('views/components/layouts/app.blade.php')))->not->toContain('fonts.googleapis');
});
