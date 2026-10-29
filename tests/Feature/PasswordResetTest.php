<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        config(['demo.admin_email' => 'admin@meridian.test', 'demo.admin_password' => null]);
        $this->seed(AdminSeeder::class);
    }

    private function knownEmail(): string
    {
        return 'admin@meridian.test';
    }

    /**
     * Request a link and return the token the notification carried.
     */
    private function requestTokenFor(string $email): string
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => $email])->assertSessionHasNoErrors();

        $token = null;

        Notification::assertSentTo(
            User::where('email', $email)->sole(),
            ResetPassword::class,
            function (ResetPassword $notification) use (&$token) {
                $token = $notification->token;

                return true;
            }
        );

        $this->assertIsString($token);

        return $token;
    }

    public function test_guests_can_reach_both_reset_forms(): void
    {
        $this->get('/forgot-password')->assertOk()->assertViewIs('auth.forgot-password')
            ->assertSee('action="'.route('password.email').'"', false);

        $this->get('/reset-password/sample-token')->assertOk()->assertViewIs('auth.reset-password')
            ->assertSee('action="'.route('password.store').'"', false);
    }

    public function test_requesting_a_link_for_a_known_address_sends_one(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => $this->knownEmail()])
            ->assertRedirect()
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();

        Notification::assertSentTo(User::sole(), ResetPassword::class);
    }

    /**
     * Accounts here are created by invitation, so confirming that an address is
     * known would confirm that someone works for the organization. The response
     * must therefore be indistinguishable from the known-address case.
     */
    public function test_an_unknown_address_is_indistinguishable_from_a_known_one(): void
    {
        Notification::fake();

        $known = $this->post('/forgot-password', ['email' => $this->knownEmail()]);
        $unknown = $this->post('/forgot-password', ['email' => 'nobody@example.org']);

        $this->assertSame($known->getStatusCode(), $unknown->getStatusCode());
        $this->assertSame(
            $known->getSession()->get('status'),
            $unknown->getSession()->get('status')
        );
        $unknown->assertSessionHasNoErrors();

        Notification::assertSentTimes(ResetPassword::class, 1);
    }

    public function test_a_valid_token_resets_the_password_without_signing_the_user_in(): void
    {
        Event::fake([PasswordReset::class]);

        $user = User::sole();
        $originalHash = $user->password;
        $originalRememberToken = $user->remember_token;

        $token = $this->requestTokenFor($this->knownEmail());

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $this->knownEmail(),
            'password' => 'ReplacementPass!2026',
            'password_confirmation' => 'ReplacementPass!2026',
        ])->assertRedirect(route('login'))->assertSessionHas('status')->assertSessionHasNoErrors();

        // A reset link that was read by somebody else must not hand them a live
        // session, so the visitor is sent to sign in rather than logged in.
        $this->assertGuest();

        $user->refresh();
        $this->assertNotSame($originalHash, $user->password);

        // Rotating this signs out "remember me" sessions on other devices, which
        // matters when the reset is a response to losing control of the account.
        $this->assertNotSame($originalRememberToken, $user->remember_token);

        Event::assertDispatched(PasswordReset::class);
    }

    public function test_the_new_password_works_and_the_old_one_stops_working(): void
    {
        $token = $this->requestTokenFor($this->knownEmail());

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $this->knownEmail(),
            'password' => 'ReplacementPass!2026',
            'password_confirmation' => 'ReplacementPass!2026',
        ])->assertSessionHasNoErrors();

        $this->post('/login', ['email' => $this->knownEmail(), 'password' => 'DemoAdmin!2026'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => $this->knownEmail(), 'password' => 'ReplacementPass!2026'])
            ->assertRedirect('/home');
        $this->assertAuthenticatedAs(User::sole());
    }

    public function test_a_token_cannot_be_reused(): void
    {
        $token = $this->requestTokenFor($this->knownEmail());

        $payload = [
            'token' => $token,
            'email' => $this->knownEmail(),
            'password' => 'ReplacementPass!2026',
            'password_confirmation' => 'ReplacementPass!2026',
        ];

        $this->post('/reset-password', $payload)->assertSessionHasNoErrors();
        $this->post('/reset-password', $payload)->assertSessionHasErrors('email');
    }

    public function test_an_invalid_token_is_rejected(): void
    {
        $this->post('/reset-password', [
            'token' => 'not-a-real-token',
            'email' => $this->knownEmail(),
            'password' => 'ReplacementPass!2026',
            'password_confirmation' => 'ReplacementPass!2026',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(
            Password::broker()->getUser(['email' => $this->knownEmail()])
                ->getAuthPassword() !== null
        );
    }

    /**
     * The token is stored against one address. Presenting it with a different
     * address must fail even when both addresses exist.
     */
    public function test_a_token_issued_for_one_address_cannot_reset_another(): void
    {
        $other = User::factory()->create(['email' => 'other@meridian.test']);
        $otherHash = $other->password;

        $token = $this->requestTokenFor($this->knownEmail());

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'other@meridian.test',
            'password' => 'ReplacementPass!2026',
            'password_confirmation' => 'ReplacementPass!2026',
        ])->assertSessionHasErrors('email');

        $this->assertSame($otherHash, $other->refresh()->password);
    }

    public function test_short_and_unconfirmed_passwords_are_rejected(): void
    {
        $token = $this->requestTokenFor($this->knownEmail());

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $this->knownEmail(),
            'password' => 'TooShort!1',
            'password_confirmation' => 'TooShort!1',
        ])->assertSessionHasErrors('password');

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $this->knownEmail(),
            'password' => 'ReplacementPass!2026',
            'password_confirmation' => 'DifferentPass!2026',
        ])->assertSessionHasErrors('password');
    }

    public function test_the_email_address_is_normalised_before_lookup(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => '  ADMIN@Meridian.TEST  '])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo(User::sole(), ResetPassword::class);
    }

    public function test_signed_in_users_are_kept_out_of_the_reset_forms(): void
    {
        $this->actingAs(User::sole());

        $this->get('/forgot-password')->assertRedirect('/home');
        $this->get('/reset-password/sample-token')->assertRedirect('/home');
    }
}
