<?php

return [
    'snippets_per_novel' => 10,

    'search' => [
        // The lexical mode is the operational kill switch and remains the default.
        'mode' => strtolower((string) env('KOMPENDIUM_SEARCH_MODE', 'lexical')) === 'hybrid'
            ? 'hybrid'
            : 'lexical',
        'index_version' => max(1, (int) env('KOMPENDIUM_SEARCH_INDEX_VERSION', 1)),
        'embedding_model' => env('KOMPENDIUM_SEARCH_EMBEDDING_MODEL', 'ts/multilingual-e5-large'),
        'text_weight' => max(0.01, (float) env('KOMPENDIUM_SEARCH_TEXT_WEIGHT', 1)),
        'semantic_weight' => max(0.01, (float) env('KOMPENDIUM_SEARCH_SEMANTIC_WEIGHT', 2)),
    ],

    'post_filter' => [
        'initial_batch_size' => 150,
        'max_candidates_per_request' => 1200,
        'batch_growth_factor' => 2,
    ],
];
