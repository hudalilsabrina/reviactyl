<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test("home returns response", function () {
    $response = $this->get("/");
    $response->assertStatus(200);
});
