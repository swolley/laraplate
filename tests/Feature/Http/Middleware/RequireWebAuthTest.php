<?php

declare(strict_types=1);

use App\Http\Middleware\RequireWebAuth;
use App\Models\User;
use Illuminate\Support\Facades\Route;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    Route::get('/__test_require_web_auth', fn (): Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response => response('ok', 200))
        ->middleware(RequireWebAuth::class);
});

it('allows authenticated users through', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/__test_require_web_auth');

    $response->assertStatus(200);
});

it('returns 401 json for unauthenticated request when expects json', function (): void {
    $response = $this->getJson('/modules/Core');

    $response->assertStatus(401);
    $response->assertJson(['message' => 'Unauthenticated.']);
});

it('redirects guest to home when not expecting json', function (): void {
    $response = $this->get('/modules/Core');

    $response->assertRedirect('/');
});
