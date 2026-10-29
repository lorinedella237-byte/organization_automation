<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        config(['demo.admin_email' => 'admin@meridian.test', 'demo.admin_password' => null]);
        $this->seed(AdminSeeder::class);
    }

    private function credentials(): array
    {
        return ['email' => 'admin@meridian.test', 'password' => 'DemoAdmin!2026'];
    }

    public function test_guests_can_see_login_but_cannot_access_home(): void
    {
        $this->get('/login')->assertOk()->assertViewIs('auth.login')
            ->assertSee('action="'.route('login').'"', false);
        $this->get('/home')->assertRedirect('/login');
        $this->getJson('/home')->assertUnauthorized();
        $this->get('/register')->assertNotFound();
    }

    public function test_seeded_credentials_sign_in_rotate_session_and_render_account_home(): void
    {
        $this->withSession(['probe' => 'retained']);
        $oldSession = session()->getId();
        $oldToken = session()->token();

        $this->post('/login', $this->credentials())->assertRedirect('/home')->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs(User::sole());
        $this->assertNotSame($oldSession, session()->getId());
        $this->assertNotSame($oldToken, session()->token());
        $this->get('/home')->assertOk()->assertSee('Welcome, Demo Admin')
            ->assertSee('admin@meridian.test')->assertSee('Sign out');
        $this->get('/login')->assertRedirect('/home');
        $this->get('/')->assertRedirect('/home');
    }

    public function test_failed_credentials_are_generic_and_password_is_not_flashed(): void
    {
        foreach (['admin@meridian.test', 'missing@meridian.test'] as $email) {
            $this->from('/login')->post('/login', ['email' => $email, 'password' => 'wrong-password'])
                ->assertRedirect('/login')->assertSessionHasErrors(['email' => trans('auth.failed')]);
            $this->assertGuest();
            $this->assertNull(session()->get('_old_input.password'));
            $this->get('/login')->assertOk()->assertSee(trans('auth.failed'));
        }
    }

    public function test_login_validates_input_and_normalizes_email(): void
    {
        $this->postJson('/login', ['email' => ['invalid'], 'password' => [], 'remember' => 'anything'])
            ->assertUnprocessable()->assertJsonValidationErrors(['email', 'password', 'remember']);
        $this->assertGuest();
        $this->post('/login', ['email' => ' ADMIN@MERIDIAN.TEST ', 'password' => 'DemoAdmin!2026'])
            ->assertRedirect('/home');
        $this->assertAuthenticated();
    }

    public function test_five_failures_lock_out_correct_credentials_until_cooldown_expires(): void
    {
        $this->freezeTime();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/login', ['email' => 'admin@meridian.test', 'password' => 'wrong'])
                ->assertUnprocessable()->assertJsonValidationErrors('email');
        }
        $this->postJson('/login', $this->credentials())->assertUnprocessable()
            ->assertJsonPath('errors.email.0', trans('auth.throttle', ['seconds' => 60, 'minutes' => 1]));
        $this->assertGuest();

        $this->travel(61)->seconds();
        $this->post('/login', $this->credentials())->assertRedirect('/home');
        $this->assertAuthenticated();
        $this->travelBack();
    }

    public function test_ip_limit_covers_attempts_against_many_different_accounts(): void
    {
        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->postJson('/login', ['email' => "missing{$attempt}@meridian.test", 'password' => 'wrong'])
                ->assertUnprocessable();
        }
        $this->postJson('/login', $this->credentials())->assertTooManyRequests();
        $this->assertGuest();
    }

    public function test_remember_me_creates_cookie_and_logout_clears_session(): void
    {
        $cookie = Auth::guard('web')->getRecallerName();
        $login = $this->post('/login', $this->credentials() + ['remember' => '1'])
            ->assertRedirect('/home')->assertCookie($cookie);
        $this->assertNotEmpty(User::sole()->remember_token);
        $oldRemember = User::sole()->remember_token;
        $oldSession = session()->getId();
        $oldToken = session()->token();
        session()->put('private_marker', 'must disappear');

        $this->withCookie($cookie, $login->getCookie($cookie)->getValue())
            ->post('/logout')->assertRedirect('/login')->assertCookieExpired($cookie);
        $this->assertGuest();
        $this->assertNotSame($oldSession, session()->getId());
        $this->assertNotSame($oldToken, session()->token());
        $this->assertNotSame($oldRemember, User::sole()->remember_token);
        $this->assertNull(session()->get('private_marker'));
        $this->get('/home')->assertRedirect('/login');
        $this->get('/logout')->assertStatus(405);
    }

    public function test_login_and_logout_require_valid_csrf_tokens(): void
    {
        $this->app->bind(VerifyCsrfToken::class, EnforcedCsrfForAuthenticationTest::class);
        $this->withSession(['_token' => 'valid-csrf-token']);
        $this->post('/login', $this->credentials())->assertStatus(419);
        $this->assertGuest();

        $this->post('/login', $this->credentials() + ['_token' => 'valid-csrf-token'])->assertRedirect('/home');
        $this->post('/logout')->assertStatus(419);
        $this->assertAuthenticated();
        $this->post('/logout', ['_token' => session()->token()])->assertRedirect('/login');
        $this->assertGuest();
    }
}

class EnforcedCsrfForAuthenticationTest extends VerifyCsrfToken
{
    protected function runningUnitTests()
    {
        return false;
    }
}
