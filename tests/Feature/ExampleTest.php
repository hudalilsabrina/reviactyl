<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('application can resolve core bindings', function () {
    expect(app()->isBooted())->toBeTrue();
    expect(config('app.name'))->toBe('Reviactyl');
    expect(config('app.env'))->toBe('testing');
});
