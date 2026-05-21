<?php

declare(strict_types=1);

it('redirects the site root to the Filament admin panel', function (): void {
    $response = $this->get('/');

    $response->assertRedirect('/admin');
});
