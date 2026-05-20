<?php

declare(strict_types=1);

use App\Models\PropertyContact;
use App\Models\PropertyListing;
use App\Models\PropertyRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

uses(DatabaseTransactions::class);

function makeContactTestUser(string $suffix): User
{
    $id = DB::table('users')->insertGetId([
        'name'              => "Test User {$suffix}",
        'email'             => "test-contact-{$suffix}@example.com",
        'username'          => "testcontact{$suffix}",
        'avatar'            => 'demo/default.png',
        'password'          => bcrypt('password'),
        'locale'            => 'es',
        'terms_accepted'    => true,
        'terms_accepted_at' => now(),
        'created_at'        => now(),
        'updated_at'        => now(),
    ]);

    return User::find($id);
}

beforeEach(function (): void {
    $suffix = uniqid('', true);
    $this->owner   = makeContactTestUser("owner-{$suffix}");
    $this->visitor = makeContactTestUser("visitor-{$suffix}");
    $this->listing = PropertyListing::factory()->create([
        'user_id'          => $this->owner->id,
        'is_active'        => true,
        'property_type'    => 'house',
        'transaction_type' => 'sale',
        'country'          => 'Argentina',
        'state'            => 'Córdoba',
        'city'             => 'Villa Carlos Paz',
        'price'            => 150000,
        'currency'         => 'USD',
    ]);
});

it('creates contact and auto-request with source whatsapp_contact', function (): void {
    $this->actingAs($this->visitor)
        ->postJson(route('property.contacts.store'), [
            'listing_id' => $this->listing->id,
            'action'     => 'whatsapp',
        ])
        ->assertOk()
        ->assertJson(['ok' => true]);

    expect(PropertyContact::where([
        'listing_id'      => $this->listing->id,
        'visitor_user_id' => $this->visitor->id,
        'action'          => 'whatsapp',
    ])->exists())->toBeTrue();

    expect(PropertyRequest::where([
        'user_id'           => $this->visitor->id,
        'source'            => 'whatsapp_contact',
        'source_listing_id' => $this->listing->id,
    ])->exists())->toBeTrue();
});

it('creates contact and auto-request with source phone_contact', function (): void {
    $this->actingAs($this->visitor)
        ->postJson(route('property.contacts.store'), [
            'listing_id' => $this->listing->id,
            'action'     => 'phone',
        ])
        ->assertOk()
        ->assertJson(['ok' => true]);

    expect(PropertyContact::where([
        'listing_id'      => $this->listing->id,
        'visitor_user_id' => $this->visitor->id,
        'action'          => 'phone',
    ])->exists())->toBeTrue();

    expect(PropertyRequest::where([
        'user_id'           => $this->visitor->id,
        'source'            => 'phone_contact',
        'source_listing_id' => $this->listing->id,
    ])->exists())->toBeTrue();
});

it('does not create duplicate auto-request when whatsapp then phone on same listing', function (): void {
    $this->actingAs($this->visitor)
        ->postJson(route('property.contacts.store'), [
            'listing_id' => $this->listing->id,
            'action'     => 'whatsapp',
        ]);

    $this->actingAs($this->visitor)
        ->postJson(route('property.contacts.store'), [
            'listing_id' => $this->listing->id,
            'action'     => 'phone',
        ]);

    expect(PropertyRequest::where([
        'user_id'           => $this->visitor->id,
        'source_listing_id' => $this->listing->id,
    ])->count())->toBe(1);
});

it('does not create auto-request when owner contacts their own listing', function (): void {
    $this->actingAs($this->owner)
        ->postJson(route('property.contacts.store'), [
            'listing_id' => $this->listing->id,
            'action'     => 'phone',
        ])
        ->assertOk();

    expect(PropertyRequest::where('user_id', $this->owner->id)->exists())->toBeFalse();
    expect(PropertyContact::where('listing_id', $this->listing->id)->exists())->toBeFalse();
});

it('does not create auto-request for unauthenticated visitors', function (): void {
    $this->postJson(route('property.contacts.store'), [
        'listing_id' => $this->listing->id,
        'action'     => 'phone',
    ])->assertUnauthorized();

    expect(PropertyRequest::where('source_listing_id', $this->listing->id)->exists())->toBeFalse();
});

it('auto-request stores correct details with null budget', function (): void {
    $this->actingAs($this->visitor)
        ->postJson(route('property.contacts.store'), [
            'listing_id' => $this->listing->id,
            'action'     => 'whatsapp',
        ]);

    $autoRequest = PropertyRequest::where([
        'user_id'           => $this->visitor->id,
        'source_listing_id' => $this->listing->id,
    ])->first();

    expect($autoRequest->client_name)->toBe($this->visitor->name)
        ->and($autoRequest->client_email)->toBe($this->visitor->email)
        ->and($autoRequest->property_type)->toBe('house')
        ->and($autoRequest->transaction_type)->toBe('sale')
        ->and($autoRequest->country)->toBe('Argentina')
        ->and($autoRequest->state)->toBe('Córdoba')
        ->and($autoRequest->city)->toBe('Villa Carlos Paz')
        ->and($autoRequest->max_budget)->toBeNull()
        ->and($autoRequest->is_active)->toBeTrue();
});
