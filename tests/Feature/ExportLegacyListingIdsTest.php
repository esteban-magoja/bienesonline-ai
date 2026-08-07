<?php

use App\Models\PropertyListing;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

uses(DatabaseTransactions::class);

it('exports the country slug, legacy ID and Spanish property URL', function () {
    $userId = DB::table('users')->insertGetId([
        'name' => 'Legacy export test user',
        'email' => 'legacy-export-test@example.com',
        'username' => 'legacy-export-test',
        'password' => bcrypt('password'),
        'locale' => 'es',
        'terms_accepted' => true,
        'terms_accepted_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $listing = PropertyListing::factory()->create([
        'user_id' => $userId,
        'title' => 'Departamento las Condes 2d 2b',
        'description' => "Descripción de prueba.\nID Anuncio: DEA123TEST",
        'city' => 'Las Condes',
        'country' => 'Chile',
        'is_active' => false,
    ]);
    $unmatchedListing = PropertyListing::factory()->create([
        'user_id' => $userId,
        'description' => 'ID Anuncio: BROKEN123 no está al final de la descripción.',
    ]);
    $path = tempnam(storage_path('app'), 'legacy-listings-');

    expect($path)->toBeString();

    try {
        $exitCode = Artisan::call('listings:export-legacy-ids', [
            'path' => $path,
            '--chunk' => 1,
        ]);

        expect($exitCode)->toBe(0);

        $rows = array_map(
            static fn (string $line): array => str_getcsv($line),
            file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES),
        );
        $listingRow = collect($rows)->first(fn (array $row): bool => ($row[1] ?? null) === 'DEA123TEST');

        expect($rows[0])->toBe(['country_slug', 'legacy_id', 'url'])
            ->and($listingRow)->toBe([
                'chile',
                'DEA123TEST',
                "/es/chile/las-condes/propiedad/{$listing->id}-departamento-las-condes-2d-2b",
            ])
            ->and(collect($rows)->pluck(1))->not->toContain('BROKEN123');
    } finally {
        if (is_string($path) && file_exists($path)) {
            unlink($path);
        }
    }
});
