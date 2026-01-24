<?php

declare(strict_types=1);

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Nwidart\Modules\Facades\Module;

// uses(RefreshDatabase::class);

// it('shows the login page on / for guests', function (): void {
//     $response = $this->get('/');

//     $response->assertOk();
//     $response->assertSee('Login');
// });

// it('has the login rate limiter registered', function (): void {
//     expect(RateLimiter::limiter('login'))->not->toBeNull();
// });

// it('shows the dashboard on / for authenticated users', function (): void {
//     $user = User::factory()->create();

//     $response = $this->actingAs($user)->get('/');

//     $response->assertOk();
//     $response->assertSee('Moduli attivi');
// });

// it('protects module pages for guests', function (): void {
//     $response = $this->get('/modules/Core');

//     $response->assertRedirect('/');
// });

it('ensure the Core module is active', function (): void {
    $modules = Module::isEnabled('Core');
    expect($modules)->toBeTrue();
});
