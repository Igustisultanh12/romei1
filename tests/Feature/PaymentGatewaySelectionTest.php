<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Payment\PaymentGatewayManager;
use App\Services\Payment\QrquService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentGatewaySelectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*ipify*'   => Http::response(['ip' => '103.28.12.99'], 200),
            '*/balance' => Http::response(['status' => true, 'data' => ['credit' => 500000]], 200),
        ]);

        $this->admin = User::factory()->create([
            'email'    => 'admin@romei.test',
            'role'     => 'admin',
            'password' => Hash::make('password123'),
        ]);

        $this->customer = User::factory()->create([
            'email'    => 'customer@romei.test',
            'role'     => 'customer',
            'password' => Hash::make('password123'),
        ]);

        Setting::set('admin_2fa_enabled', '0');
    }

    public function test_admin_settings_displays_payment_gateway_selection(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.settings'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Settings/AdminIndex')
            ->has('settings.payment_gateway_provider')
            ->has('settings.qrqu_api_url')
            ->has('settings.qrqu_api_key')
        );
    }

    public function test_admin_can_switch_gateway_to_qrqu(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.settings.update'), [
            'ceirku_mode'              => 'sandbox',
            'ceirku_api_url'           => 'https://ceirku.net/api/v1',
            'ceirku_api_key'           => 'sample_key',
            'fee_check_sim_lock'       => 5000,
            'fee_check_ceir_history'   => 7500,
            'fee_add_roamer_1m'        => 135000,
            'fee_add_roamer_3m'        => 180000,
            'payment_gateway_provider' => 'qrqu',
            'qrqu_api_url'             => 'https://qrqu.id',
            'qrqu_api_key'             => 'qrqu_live_12345678',
            'qrqu_api_secret'          => 'sec_test_secret_12345',
            'qrqu_webhook_secret'      => 'whsec_test_signature_999',
            'maintenance_mode'         => false,
        ]);

        $response->assertRedirect();
        $this->assertEquals('qrqu', Setting::get('payment_gateway_provider'));
        $this->assertEquals('https://qrqu.id', Setting::get('qrqu_api_url'));
        $this->assertEquals('qrqu_live_12345678', Setting::get('qrqu_api_key'));
        $this->assertEquals('sec_test_secret_12345', Setting::get('qrqu_api_secret'));
        $this->assertEquals('whsec_test_signature_999', Setting::get('qrqu_webhook_secret'));
        $this->assertTrue(PaymentGatewayManager::isQrqu());
        $this->assertFalse(PaymentGatewayManager::isDoku());
    }

    public function test_admin_can_switch_gateway_back_to_doku(): void
    {
        Setting::set('payment_gateway_provider', 'qrqu');

        $response = $this->actingAs($this->admin)->post(route('admin.settings.update'), [
            'ceirku_mode'              => 'sandbox',
            'ceirku_api_url'           => 'https://ceirku.net/api/v1',
            'ceirku_api_key'           => 'sample_key',
            'fee_check_sim_lock'       => 5000,
            'fee_check_ceir_history'   => 7500,
            'fee_add_roamer_1m'        => 135000,
            'fee_add_roamer_3m'        => 180000,
            'payment_gateway_provider' => 'doku',
            'doku_client_id'           => 'MALL-12345',
            'doku_secret_key'          => 'SK-DOKU-SECRET',
            'maintenance_mode'         => false,
        ]);

        $response->assertRedirect();
        $this->assertEquals('doku', Setting::get('payment_gateway_provider'));
        $this->assertTrue(PaymentGatewayManager::isDoku());
        $this->assertFalse(PaymentGatewayManager::isQrqu());
    }

    public function test_qrqu_service_generates_invoice_with_cryptographic_hmac_headers(): void
    {
        Setting::set('qrqu_api_url', 'https://qrqu.id');
        Setting::set('qrqu_api_key', 'qrqu_live_merchant123');
        Setting::set('qrqu_api_secret', 'sec_my_super_secret_key');

        Http::fake([
            'https://qrqu.id/api/v1/invoices' => function ($request) {
                // Verifikasi header QRqu otentikasi
                $hasKey = $request->hasHeader('X-QRQU-KEY');
                $hasTimestamp = $request->hasHeader('X-QRQU-TIMESTAMP');
                $hasNonce = $request->hasHeader('X-QRQU-NONCE');
                $hasSignature = $request->hasHeader('X-QRQU-SIGNATURE');

                if (!$hasKey || !$hasTimestamp || !$hasNonce || !$hasSignature) {
                    return Http::response(['message' => 'Missing auth headers'], 401);
                }

                $rawBody = $request->body();
                $expectedSig = hash_hmac(
                    'sha256',
                    $request->header('X-QRQU-KEY')[0] .
                    $request->header('X-QRQU-TIMESTAMP')[0] .
                    $request->header('X-QRQU-NONCE')[0] .
                    $rawBody,
                    'sec_my_super_secret_key'
                );

                if (!hash_equals($expectedSig, $request->header('X-QRQU-SIGNATURE')[0])) {
                    return Http::response(['message' => 'Invalid signature'], 401);
                }

                return Http::response([
                    'success' => true,
                    'data'    => [
                        'invoice_id'     => 'INV-QRQU-2026-12345',
                        'external_id'    => 'INV-ROMEI-TEST-001',
                        'amount'         => 50000,
                        'status'         => 'PENDING',
                        'payment_method' => 'QRIS',
                        'qr_string'      => '00020101021226540014ID.LINKAJA.WWW0118936009110022203002021520260929123455802ID5910ROMEI PAY6007JAKARTA',
                        'qr_url'         => 'https://qrqu.id/checkout/INV-QRQU-2026-12345',
                    ]
                ], 201);
            }
        ]);

        $mockTx = new \stdClass();
        $mockTx->invoice_number = 'INV-ROMEI-TEST-001';
        $mockTx->amount = 50000;
        $mockTx->user = $this->customer;

        $service = new QrquService();
        $result = $service->generateInvoice($mockTx);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('https://qrqu.id/checkout/INV-QRQU-2026-12345', $result['payment_url']);
        $this->assertNotEmpty($result['qr_string']);
        $this->assertEquals('INV-QRQU-2026-12345', $result['invoice_id']);
    }

    public function test_qrqu_webhook_processes_paid_callback_and_credits_wallet(): void
    {
        $wallet = $this->customer->wallet()->create(['balance' => 10000]);

        $invoiceId = 'INV-ROMEI-2026-WEBHOOK-TEST';
        $transaction = Transaction::create([
            'invoice_number' => $invoiceId,
            'amount'         => 75000,
            'status'         => 'PENDING',
            'user_id'        => $this->customer->id,
            'payable_type'   => Wallet::class,
            'payable_id'     => $wallet->id,
        ]);

        $webhookSecret = 'whsec_sample_secret_key_123';
        Setting::set('qrqu_webhook_secret', $webhookSecret);

        $payload = [
            'event'          => 'payment.paid',
            'event_id'       => 'EVT-TEST-1234567890',
            'invoice_id'     => 'INV-QRQU-999',
            'external_id'    => $invoiceId,
            'transaction_id' => 'TRX-QRQU-001',
            'amount'         => 75000,
            'status'         => 'PAID',
            'payment_method' => 'QRIS',
            'paid_at'        => now()->toIso8601String(),
            'timestamp'      => time(),
        ];

        $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha256', $rawBody, $webhookSecret);

        $response = $this->call(
            'POST',
            '/api/webhook/qrqu',
            [],
            [],
            [],
            [
                'CONTENT_TYPE'           => 'application/json',
                'HTTP_X_QRQU_SIGNATURE'  => $signature,
                'HTTP_X_QRQU_EVENT'      => 'payment.paid',
            ],
            $rawBody
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('transactions', [
            'invoice_number'      => $invoiceId,
            'status'              => 'SUCCESS',
            'payment_gateway_ref' => 'TRX-QRQU-001',
        ]);

        // Saldo awal 10.000 + Top Up 75.000 = 85.000
        $this->assertEquals(85000, $wallet->fresh()->balance);
        $this->assertEquals('SUCCESS', Cache::get('payment_status_' . $invoiceId));
    }

    public function test_qrqu_webhook_is_idempotent_preventing_double_crediting(): void
    {
        $wallet = $this->customer->wallet()->create(['balance' => 20000]);

        $invoiceId = 'INV-ROMEI-IDEMPOTENT-TEST';
        $transaction = Transaction::create([
            'invoice_number' => $invoiceId,
            'amount'         => 50000,
            'status'         => 'PENDING',
            'user_id'        => $this->customer->id,
            'payable_type'   => Wallet::class,
            'payable_id'     => $wallet->id,
        ]);

        $webhookSecret = 'whsec_sample_secret_key_123';
        Setting::set('qrqu_webhook_secret', $webhookSecret);

        $payload = [
            'event'          => 'payment.paid',
            'event_id'       => 'EVT-TEST-1234567890',
            'invoice_id'     => 'INV-QRQU-999',
            'external_id'    => $invoiceId,
            'transaction_id' => 'TRX-QRQU-001',
            'amount'         => 50000,
            'status'         => 'PAID',
        ];

        $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha256', $rawBody, $webhookSecret);

        // Panggilan webhook pertama
        $res1 = $this->call('POST', '/api/webhook/qrqu', [], [], [], [
            'CONTENT_TYPE'          => 'application/json',
            'HTTP_X_QRQU_SIGNATURE' => $signature,
        ], $rawBody);
        $res1->assertStatus(200);
        $this->assertEquals(70000, $wallet->fresh()->balance);

        // Panggilan webhook kedua (Replay / Retry)
        $res2 = $this->call('POST', '/api/webhook/qrqu', [], [], [], [
            'CONTENT_TYPE'          => 'application/json',
            'HTTP_X_QRQU_SIGNATURE' => $signature,
        ], $rawBody);
        $res2->assertStatus(200);

        // Saldo tetap 70.000 (TIDAK menjadi 120.000)
        $this->assertEquals(70000, $wallet->fresh()->balance);
    }

    public function test_qrqu_webhook_rejects_invalid_signature(): void
    {
        Setting::set('qrqu_webhook_secret', 'correct_secret');

        $payload = ['event' => 'payment.paid', 'external_id' => 'INV-ROMEI-123'];
        $rawBody = json_encode($payload);

        $response = $this->call('POST', '/api/webhook/qrqu', [], [], [], [
            'CONTENT_TYPE'          => 'application/json',
            'HTTP_X_QRQU_SIGNATURE' => 'invalid_signature_hash',
        ], $rawBody);

        $response->assertStatus(401);
    }

    public function test_admin_can_run_handshake_test_for_qrqu(): void
    {
        Http::fake([
            'https://qrqu.id/api/health'     => Http::response(['status' => 'ok'], 200),
            'https://qrqu.id/api/v1/account' => Http::response([
                'success' => true,
                'data'    => [
                    'name'         => 'PT Merchant Nusantara',
                    'company_name' => 'Nusantara POS',
                    'subscription' => ['plan' => 'Business'],
                ]
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.settings.test-qrqu'), [
            'qrqu_api_url'    => 'https://qrqu.id',
            'qrqu_api_key'    => 'qrqu_live_123',
            'qrqu_api_secret' => 'sec_456',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success'    => true,
            'is_auth_ok' => true,
        ]);
    }

    public function test_normalize_base_url_removes_redundant_paths_and_trailing_slashes(): void
    {
        $this->assertEquals('https://qrqu.id', QrquService::normalizeBaseUrl('https://qrqu.id'));
        $this->assertEquals('https://qrqu.id', QrquService::normalizeBaseUrl('https://qrqu.id/'));
        $this->assertEquals('https://qrqu.id', QrquService::normalizeBaseUrl('https://qrqu.id/api'));
        $this->assertEquals('https://qrqu.id', QrquService::normalizeBaseUrl('https://qrqu.id/api/'));
        $this->assertEquals('https://qrqu.id', QrquService::normalizeBaseUrl('https://qrqu.id/api/v1'));
        $this->assertEquals('https://qrqu.id', QrquService::normalizeBaseUrl('https://qrqu.id/api/v1/'));
        $this->assertEquals('https://qrqu.id', QrquService::normalizeBaseUrl('https://qrqu.id/api/v1/invoices'));
        $this->assertEquals('https://qrqu.id', QrquService::normalizeBaseUrl('https://qrqu.id/api/v1/account'));
        $this->assertEquals('https://qrqu.id', QrquService::normalizeBaseUrl('https://qrqu.id/v1'));
        $this->assertEquals('http://localhost:8000', QrquService::normalizeBaseUrl('localhost:8000'));
        $this->assertEquals('https://sub.domain.com/prefix', QrquService::normalizeBaseUrl('https://sub.domain.com/prefix/api/v1'));
    }

    public function test_admin_settings_update_normalizes_qrqu_api_url_in_database(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.settings.update'), [
            'ceirku_mode'              => 'sandbox',
            'ceirku_api_url'           => 'https://ceirku.net/api/v1',
            'ceirku_api_key'           => 'sample_key',
            'fee_check_sim_lock'       => 5000,
            'fee_check_ceir_history'   => 7500,
            'fee_add_roamer_1m'        => 135000,
            'fee_add_roamer_3m'        => 180000,
            'payment_gateway_provider' => 'qrqu',
            'qrqu_api_url'             => 'https://qrqu.id/api/v1/',
            'qrqu_api_key'             => 'qrqu_live_sample',
            'qrqu_api_secret'          => 'sec_sample',
            'maintenance_mode'         => false,
        ]);

        $response->assertRedirect();
        $this->assertEquals('https://qrqu.id', Setting::get('qrqu_api_url'));
    }

    public function test_admin_handshake_auto_normalizes_url_and_succeeds(): void
    {
        Http::fake([
            'https://qrqu.id/api/health'     => Http::response(['status' => 'UP'], 200),
            'https://qrqu.id/api/v1/account' => Http::response([
                'success' => true,
                'data'    => [
                    'name'         => 'PT Auto Normalizer',
                    'subscription' => ['plan' => 'Enterprise'],
                ]
            ], 200),
        ]);

        // Input URL dengan akhiran /api/v1/
        $response = $this->actingAs($this->admin)->post(route('admin.settings.test-qrqu'), [
            'qrqu_api_url'    => 'https://qrqu.id/api/v1/',
            'qrqu_api_key'    => 'qrqu_live_123',
            'qrqu_api_secret' => 'sec_456',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success'    => true,
            'is_auth_ok' => true,
        ]);
        $this->assertStringContainsString('PT Auto Normalizer', $response->json('message'));
    }

    public function test_admin_handshake_falls_back_to_v1_account_when_api_v1_returns_404(): void
    {
        Http::fake([
            'https://qrqu.id/api/health'     => Http::response(['status' => 'UP'], 200),
            'https://qrqu.id/api/v1/account' => Http::response(['message' => 'Not Found'], 404),
            'https://qrqu.id/v1/account'     => Http::response([
                'success' => true,
                'data'    => [
                    'name'         => 'Fallback Merchant',
                    'subscription' => ['plan' => 'Pro'],
                ]
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.settings.test-qrqu'), [
            'qrqu_api_url'    => 'https://qrqu.id',
            'qrqu_api_key'    => 'qrqu_live_123',
            'qrqu_api_secret' => 'sec_456',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success'    => true,
            'is_auth_ok' => true,
        ]);
        $this->assertStringContainsString('Fallback Merchant', $response->json('message'));
    }

    public function test_admin_handshake_returns_clear_diagnostic_when_404_received(): void
    {
        Http::fake([
            'https://qrqu.id/api/health'     => Http::response(['message' => 'Not Found'], 404),
            'https://qrqu.id/health'         => Http::response(['message' => 'Not Found'], 404),
            'https://qrqu.id/up'             => Http::response(['message' => 'Not Found'], 404),
            'https://qrqu.id'                => Http::response(['message' => 'Not Found'], 404),
            'https://qrqu.id/api/v1/account' => Http::response(['message' => 'Not Found'], 404),
            'https://qrqu.id/v1/account'     => Http::response(['message' => 'Not Found'], 404),
            'https://qrqu.id/account'        => Http::response(['message' => 'Not Found'], 404),
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.settings.test-qrqu'), [
            'qrqu_api_url'    => 'https://qrqu.id',
            'qrqu_api_key'    => 'qrqu_live_123',
            'qrqu_api_secret' => 'sec_456',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success'     => false,
            'status_code' => 404,
            'is_auth_ok'  => false,
        ]);
        $this->assertStringContainsString('HTTP 404', $response->json('message'));
        $this->assertStringContainsString('tidak ditemukan', $response->json('message'));
    }

    public function test_admin_handshake_returns_specific_diagnostic_when_401_invalid_key_received(): void
    {
        Http::fake([
            'https://qrqu.id/api/health'     => Http::response(['status' => 'UP'], 200),
            'https://qrqu.id/api/v1/account' => Http::response([
                'success' => false,
                'error'   => [
                    'code'    => 'INVALID_API_KEY',
                    'message' => 'The provided API Key is invalid or inactive',
                ]
            ], 401),
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.settings.test-qrqu'), [
            'qrqu_api_url'    => 'https://qrqu.id',
            'qrqu_api_key'    => 'qrqu_live_wrong',
            'qrqu_api_secret' => 'sec_wrong',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success'     => false,
            'status_code' => 401,
            'is_auth_ok'  => false,
        ]);
        $this->assertStringContainsString('API Key tidak terdaftar', $response->json('message'));
    }

    public function test_admin_api_monitor_displays_gateway_and_detailed_diagnostics(): void
    {
        Setting::set('payment_gateway_provider', 'qrqu');
        Setting::set('qrqu_api_url', 'https://qrqu.id');
        Setting::set('ceirku_api_key', 'test_ceir_key');

        Http::fake([
            'https://ceirku.net/api/v1/balance' => Http::response(['status' => true, 'data' => ['credit' => 25000]], 200),
            'https://ceirku.net/api/v1/order'   => Http::response(['status' => false, 'message' => 'Saldo kuota tidak mencukupi'], 400),
            'https://qrqu.id/api/health'        => Http::response(['status' => 'UP'], 200),
            'https://api.doku.com'              => Http::response(['status' => 'OK'], 200),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.monitoring'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('ApiMonitor/Index')
            ->has('apis', 5)
            ->where('ceir_balance', 500000)
            ->where('active_gateway', 'qrqu')
        );
    }
}
