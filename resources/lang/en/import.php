<?php

return [
    'title'             => 'Import listings',
    'subtitle'          => 'Import your listings from the previous system',
    'background_notice' => 'The process runs in the background and may take several minutes.',
    'button'            => 'Import my listings',
    'select_country'    => 'Country of origin',
    'choose_country'    => 'Choose country',
    'started'           => 'Import started. Processing in the background...',
    'already_running'   => 'An import is already in progress.',
    'not_configured'    => 'The import system is not configured.',
    'invalid_country'   => 'Invalid or unconfigured country.',
    'connection_error'  => 'Could not connect to the previous system.',
    'api_error'         => 'The previous system returned an error.',
    'no_listings'       => 'No listings found to import for your account.',
    'processing'        => 'Importing listings...',
    'completed'         => 'Import completed',
    'failed'            => 'Import failed',
    'status'            => [
        'pending'    => 'Queued',
        'processing' => 'Processing',
        'completed'  => 'Completed',
        'failed'     => 'Failed',
    ],
    'results' => [
        'imported' => ':count imported',
        'skipped'  => ':count already existed',
        'failed'   => ':count failed',
        'of'       => 'of :total listings',
    ],
];
