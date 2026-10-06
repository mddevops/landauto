<?php

namespace Tests\Feature\Auth;

use App\Actions\Fortify\CreateNewUser;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_registration_creates_an_unverified_user_and_sends_the_verification_email()
    {
        Notification::fake();

        $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::query()->where('email', 'test@example.com')->firstOrFail();

        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);

        $this->get(route('dashboard'))->assertRedirect(route('verification.notice'));
    }

    public function test_registration_creates_one_personal_workspace_with_an_active_owner_membership()
    {
        Notification::fake();

        $this->post(route('register.store'), [
            'name' => 'Иван Петров',
            'email' => 'owner@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasNoErrors();

        $user = User::query()->where('email', 'owner@example.com')->firstOrFail();
        $workspace = Workspace::query()->sole();
        $membership = $user->memberships()->sole();

        $this->assertSame('Моё пространство', $workspace->name);
        $this->assertSame('Иван Петров', $user->name);
        $this->assertSame($workspace->id, $membership->workspace_id);
        $this->assertSame(WorkspaceRole::Owner, $membership->role);
        $this->assertSame(WorkspaceMemberStatus::Active, $membership->status);
        $this->assertNotNull($membership->joined_at);
    }

    public function test_registration_stores_a_normalized_email()
    {
        Notification::fake();

        $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => '  Test.User@Example.COM ',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasNoErrors();

        $this->assertSame(['test.user@example.com'], User::query()->pluck('email')->all());
    }

    public function test_registration_rejects_an_email_that_differs_only_in_case_or_whitespace()
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => ' TAKEN@Example.com ',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(1, User::query()->count());
    }

    public function test_create_new_user_action_rejects_a_non_string_email()
    {
        try {
            (new CreateNewUser)->create([
                'name' => 'Test User',
                'email' => ['test@example.com'],
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $this->fail('A non-string email must fail validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
        }

        $this->assertSame(0, User::query()->count());
    }

    public function test_create_new_user_action_normalizes_email_independently_of_http_middleware()
    {
        $user = (new CreateNewUser)->create([
            'name' => 'Test User',
            'email' => "  Direct@Example.COM \t",
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertSame('direct@example.com', $user->email);

        $this->expectException(ValidationException::class);

        (new CreateNewUser)->create([
            'name' => 'Duplicate',
            'email' => 'DIRECT@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
    }
}
