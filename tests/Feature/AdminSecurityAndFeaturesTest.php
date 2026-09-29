<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Setting;
use App\Models\Feedback;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminSecurityAndFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake([
            '*ipify*'   => Http::response(['ip' => '103.28.12.99'], 200),
            '*/balance' => Http::response(['status' => true, 'data' => ['credit' => 500000]], 200),
            '*'         => Http::response([], 200),
        ]);
        Setting::set('admin_2fa_enabled', '1');
    }

    public function test_admin_login_triggers_2fa_and_defaults_to_whatsapp_channel(): void
    {
        $admin = User::factory()->create([
            'email'           => 'admin@romei.test',
            'password'        => Hash::make('password123'),
            'role'            => 'admin',
            'whatsapp_number' => '628123456789',
        ]);

        $response = $this->post('/login', [
            'email'    => 'admin@romei.test',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('admin.2fa.index'));
        $this->assertEquals($admin->id, session('admin_2fa_user_id'));
        $this->assertEquals('whatsapp', session('admin_2fa_channel'));
        $this->assertNotNull(session('admin_2fa_otp_hash'));
    }

    public function test_admin_can_view_2fa_challenge_screen(): void
    {
        $admin = User::factory()->create([
            'role'            => 'admin',
            'whatsapp_number' => '628123456789',
        ]);

        $this->actingAs($admin)
            ->withSession([
                'admin_2fa_user_id'    => $admin->id,
                'admin_2fa_otp_hash'   => Hash::make('123456'),
                'admin_2fa_expires_at' => now()->addMinutes(10)->timestamp,
                'admin_2fa_channel'    => 'whatsapp',
            ])
            ->get(route('admin.2fa.index'))
            ->assertStatus(200);
    }

    public function test_admin_2fa_verification_succeeds_with_correct_otp(): void
    {
        $admin = User::factory()->create([
            'role'            => 'admin',
            'whatsapp_number' => '628123456789',
        ]);

        $response = $this->actingAs($admin)
            ->withSession([
                'admin_2fa_user_id'    => $admin->id,
                'admin_2fa_otp_hash'   => Hash::make('654321'),
                'admin_2fa_expires_at' => now()->addMinutes(10)->timestamp,
                'admin_2fa_attempts'   => 0,
                'admin_2fa_channel'    => 'whatsapp',
            ])
            ->post(route('admin.2fa.verify'), [
                'code' => '654321',
            ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertTrue(session('admin_2fa_verified'));
    }

    public function test_admin_2fa_verification_fails_with_incorrect_otp(): void
    {
        $admin = User::factory()->create([
            'role'            => 'admin',
            'whatsapp_number' => '628123456789',
        ]);

        $response = $this->actingAs($admin)
            ->withSession([
                'admin_2fa_user_id'    => $admin->id,
                'admin_2fa_otp_hash'   => Hash::make('654321'),
                'admin_2fa_expires_at' => now()->addMinutes(10)->timestamp,
                'admin_2fa_attempts'   => 0,
                'admin_2fa_channel'    => 'whatsapp',
            ])
            ->post(route('admin.2fa.verify'), [
                'code' => '000000',
            ]);

        $response->assertSessionHasErrors('code');
        $this->assertNotTrue(session('admin_2fa_verified'));
        $this->assertEquals(1, session('admin_2fa_attempts'));
    }

    public function test_admin_can_resend_otp_and_switch_to_email_channel(): void
    {
        Mail::fake();

        $admin = User::factory()->create([
            'role'            => 'admin',
            'whatsapp_number' => '628123456789',
            'email'           => 'admin@romei.test',
        ]);

        $response = $this->actingAs($admin)
            ->withSession([
                'admin_2fa_user_id'    => $admin->id,
                'admin_2fa_otp_hash'   => Hash::make('111111'),
                'admin_2fa_expires_at' => now()->addMinutes(10)->timestamp,
                'admin_2fa_sent_at'    => now()->subMinutes(2)->timestamp,
                'admin_2fa_channel'    => 'whatsapp',
            ])
            ->post(route('admin.2fa.resend'), [
                'channel' => 'email',
            ]);

        $response->assertSessionHas('success');
        $this->assertEquals('email', session('admin_2fa_channel'));
        Mail::assertSent(\App\Mail\OtpNotificationMail::class);
    }

    public function test_admin_mail_gateway_page_is_accessible_to_authenticated_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->withSession(['admin_2fa_verified' => true])
            ->get(route('admin.mail.index'))
            ->assertStatus(200);
    }

    public function test_admin_can_update_mail_gateway_configuration(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->withSession(['admin_2fa_verified' => true])
            ->post(route('admin.mail.update'), [
                'mail_mailer'       => 'smtp',
                'mail_host'         => 'smtp.custom.io',
                'mail_port'         => 587,
                'mail_username'     => 'mailer-user',
                'mail_password'     => 'secret123',
                'mail_encryption'   => 'tls',
                'mail_from_address' => 'no-reply@romei-gateway.com',
                'mail_from_name'    => 'ROMEI Test Gateway',
                'enable_email_otp'  => '1',
                'admin_2fa_enabled' => '1',
            ]);

        $response->assertRedirect(route('admin.mail.index'));
        $this->assertEquals('smtp.custom.io', Setting::get('mail_host'));
        $this->assertEquals('no-reply@romei-gateway.com', Setting::get('mail_from_address'));
    }

    public function test_admin_can_test_ceirku_api_gateway_endpoint(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        \Illuminate\Support\Facades\Http::fake([
            '*/balance' => \Illuminate\Support\Facades\Http::response([
                'status' => true,
                'data'   => ['credit' => 500000],
            ], 200),
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['admin_2fa_verified' => true])
            ->postJson(route('admin.settings.test-ceirku'), [
                'ceirku_api_url' => 'https://ceirku-proxy.test/api/v1',
                'ceirku_api_key' => 'TEST_KEY_123',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success'    => true,
                'is_auth_ok' => true,
            ]);
    }

    public function test_admin_feedbacks_management_is_accessible_and_moderatable(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $feedback = Feedback::create([
            'name'         => 'Budi Test',
            'email'        => 'budi@test.com',
            'service_type' => 'Registrasi IMEI',
            'rating'       => 5,
            'message'      => 'Layanan sangat cepat!',
            'status'       => 'pending',
        ]);

        $this->actingAs($admin)
            ->withSession(['admin_2fa_verified' => true])
            ->get(route('admin.feedbacks.index'))
            ->assertStatus(200);

        // Update status to approved
        $this->actingAs($admin)
            ->withSession(['admin_2fa_verified' => true])
            ->post(route('admin.feedbacks.update-status', $feedback->id), [
                'status' => 'approved',
            ])
            ->assertSessionHas('success');

        $this->assertEquals('approved', $feedback->fresh()->status);
    }

    public function test_admin_can_detect_public_server_ip(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->withSession(['admin_2fa_verified' => true])
            ->postJson(route('admin.settings.detect-ip'));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'ip'      => '103.28.12.99',
            ]);
    }

    public function test_admin_settings_page_contains_server_ip(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->withSession(['admin_2fa_verified' => true])
            ->get(route('admin.settings'));

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Settings/AdminIndex')
                ->has('settings.server_ip')
                ->where('settings.server_ip', '103.28.12.99')
            );
    }
}
