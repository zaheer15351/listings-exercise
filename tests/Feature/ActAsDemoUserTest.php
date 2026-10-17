<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ActAsDemoUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get('/_test/who-am-i', fn () => [
            'auth' => auth()->user()?->id,
            'auth_id' => Auth::id(),
            'request' => request()->user()?->id,
        ]);
    }

    /**
     * The README promises both accessors return the demo user. Before the fix
     * only $request->user() did, because the guard never saw the stub's user.
     */
    public function test_every_accessor_resolves_the_demo_user(): void
    {
        $demo = User::factory()->create();
        User::factory()->create();

        $this->get('/_test/who-am-i')->assertExactJson([
            'auth' => $demo->id,
            'auth_id' => $demo->id,
            'request' => $demo->id,
        ]);
    }

    public function test_an_unseeded_database_behaves_like_a_guest(): void
    {
        $this->get('/_test/who-am-i')->assertExactJson([
            'auth' => null,
            'auth_id' => null,
            'request' => null,
        ]);
    }
}
