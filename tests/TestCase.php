<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Page tests render the root Blade view, which would otherwise need a
        // built Vite manifest in public/build. The tests are about the response,
        // not the asset pipeline, so stub the directives out.
        $this->withoutVite();
    }

    /**
     * The auth stub resolves every request as the lowest-id user, so create the
     * demo user before anyone else in a test.
     */
    protected function demoUser(): User
    {
        return User::query()->orderBy('id')->first() ?? User::factory()->create();
    }
}
