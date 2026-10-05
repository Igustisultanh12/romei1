<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\CeirkuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CeirkuNewApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
        ]);
    }

    public function test_ceirku_get_account_info_parses_dhru_success_response(): void
    {
        Http::fake([
            'https://ceirku.org/api' => Http::response([
                'SUCCESS' => [
                    [
                        'message'  => 'Account info retrieved',
                        'credit'   => '1500000.00',
                        'currency' => 'IDR',
                        'mail'     => 'igustisultan12@gmail.com',
                    ]
                ],
                'apiversion' => '8.2',
            ], 200),
        ]);

        $info = CeirkuService::getAccountInfo('https://ceirku.org/api', 'valid_key_123', 'Igshax12');

        $this->assertTrue($info['success']);
        $this->assertTrue($info['is_auth_ok']);
        $this->assertEquals(1500000.0, $info['credit']);
        $this->assertEquals('IDR', $info['currency']);
        $this->assertEquals('igustisultan12@gmail.com', $info['email']);
    }

    public function test_ceirku_get_account_info_parses_dhru_error_response(): void
    {
        Http::fake([
            'https://ceirku.org/api' => Http::response([
                'ERROR' => [
                    [
                        'MESSAGE' => 'Authentication Failed',
                    ]
                ],
                'apiversion' => '8.2',
            ], 200),
        ]);

        $info = CeirkuService::getAccountInfo('https://ceirku.org/api', 'wrong_key', 'Igshax12');

        $this->assertFalse($info['is_auth_ok']);
        $this->assertStringContainsString('Authentication Failed', $info['message']);
    }

    public function test_ceirku_get_service_list_parses_categories_and_services(): void
    {
        Http::fake([
            'https://ceirku.org/api' => Http::response([
                'SUCCESS' => [
                    [
                        'LIST' => [
                            'IMEI UNLOCK INDONESIA' => [
                                'SERVICES' => [
                                    [
                                        'SERVICEID'   => 30,
                                        'SERVICENAME' => 'IMEI WHITELIST 3 BULAN',
                                        'CREDIT'      => '125000',
                                        'TIME'        => '1-24 Hours',
                                    ],
                                    [
                                        'SERVICEID'   => 36,
                                        'SERVICENAME' => 'ADD ROAMER 3 BULAN',
                                        'CREDIT'      => '175000',
                                        'TIME'        => 'Instant',
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                'apiversion' => '8.2',
            ], 200),
        ]);

        $result = CeirkuService::getServiceList('https://ceirku.org/api', 'key_123', 'Igshax12');

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['services']);
        $this->assertEquals(30, $result['services'][0]['id']);
        $this->assertEquals('IMEI WHITELIST 3 BULAN', $result['services'][0]['name']);
        $this->assertEquals(125000.0, $result['services'][0]['credit']);
    }

    public function test_ceirku_place_imei_order_returns_reference_id(): void
    {
        Http::fake([
            'https://ceirku.org/api' => function ($request) {
                $payload = json_decode($request->body(), true);
                $this->assertEquals('Igshax12', $payload['username']);
                $this->assertEquals('placeimeiorder', $payload['action']);
                $this->assertEquals(30, $payload['parameters']['ID']);
                $this->assertEquals('352416000000001', $payload['parameters']['IMEI']);

                return Http::response([
                    'SUCCESS' => [
                        [
                            'message'     => 'Order placed successfully',
                            'REFERENCEID' => '987654',
                        ]
                    ],
                    'apiversion' => '8.2',
                ], 200);
            }
        ]);

        Setting::set('ceirku_api_url', 'https://ceirku.org/api');
        Setting::set('ceirku_username', 'Igshax12');
        Setting::set('ceirku_api_key', 'valid_key_123');

        $res = CeirkuService::placeImeiOrder('352416000000001', 30);

        $this->assertTrue($res['success']);
        $this->assertEquals('987654', $res['reference_id']);
    }

    public function test_ceirku_get_imei_order_maps_status_and_code(): void
    {
        Http::fake([
            'https://ceirku.org/api' => function ($request) {
                $payload = json_decode($request->body(), true);
                $this->assertEquals('getimeiorder', $payload['action']);
                $this->assertEquals(987654, $payload['parameters']['ID']);

                return Http::response([
                    'SUCCESS' => [
                        [
                            'STATUS'  => 2,
                            'CODE'    => 'TERDAFTAR_KEMENPERIN',
                            'message' => 'Completed',
                        ]
                    ],
                    'apiversion' => '8.2',
                ], 200);
            }
        ]);

        Setting::set('ceirku_api_url', 'https://ceirku.org/api');
        Setting::set('ceirku_username', 'Igshax12');
        Setting::set('ceirku_api_key', 'valid_key_123');

        $res = CeirkuService::getImeiOrder(987654);

        $this->assertTrue($res['success']);
        $this->assertEquals('SUCCESS', $res['status']);
        $this->assertEquals('TERDAFTAR_KEMENPERIN', $res['code']);
    }

    public function test_admin_can_test_ceirku_with_new_api_endpoint_and_username(): void
    {
        Http::fake([
            'https://ceirku.org/api' => Http::response([
                'SUCCESS' => [
                    [
                        'message'  => 'Account OK',
                        'credit'   => '750000',
                        'currency' => 'IDR',
                        'mail'     => 'admin@romei.test',
                    ]
                ],
                'apiversion' => '8.2',
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)
            ->withSession(['admin_2fa_verified' => true])
            ->postJson(route('admin.settings.test-ceirku'), [
                'ceirku_api_url'  => 'https://ceirku.org/api',
                'ceirku_username' => 'Igshax12',
                'ceirku_api_key'  => 'valid_key_123',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success'    => true,
            'is_auth_ok' => true,
            'credit'     => 750000,
        ]);
    }

    public function test_ceirku_sync_orders_command_updates_success_and_refunds_on_rejected(): void
    {
        $user = User::factory()->create();
        $wallet = $user->wallet()->create(['balance' => 50000]);

        // Transaksi 1: Sukses
        $txSuccess = Transaction::create([
            'invoice_number' => 'TX-SUCCESS-001',
            'amount'         => 135000,
            'status'         => 'SUCCESS',
            'user_id'        => $user->id,
            'payable_type'   => Wallet::class,
            'payable_id'     => $wallet->id,
            'metadata'       => [
                'ceirku_order_id' => 'ORD-101',
                'ceirku_status'   => 'PROCESSING',
            ],
        ]);

        // Transaksi 2: Ditolak (akan di-refund)
        $txRejected = Transaction::create([
            'invoice_number' => 'TX-REJECTED-002',
            'amount'         => 180000,
            'status'         => 'SUCCESS',
            'user_id'        => $user->id,
            'payable_type'   => Wallet::class,
            'payable_id'     => $wallet->id,
            'metadata'       => [
                'ceirku_order_id' => 'ORD-202',
                'ceirku_status'   => 'PROCESSING',
            ],
        ]);

        Http::fake([
            'https://ceirku.org/api' => function ($request) {
                $payload = json_decode($request->body(), true);
                $id = $payload['parameters']['ID'] ?? null;
                if ($id === 'ORD-101') {
                    return Http::response([
                        'SUCCESS' => [['STATUS' => 2, 'CODE' => 'SUCCESS_ACTIVE']],
                        'apiversion' => '8.2',
                    ], 200);
                }
                if ($id === 'ORD-202') {
                    return Http::response([
                        'SUCCESS' => [['STATUS' => 1, 'CODE' => 'BLACKLISTED_DEVICE']],
                        'apiversion' => '8.2',
                    ], 200);
                }
                return Http::response(['ERROR' => [['MESSAGE' => 'Not found']]], 404);
            }
        ]);

        Setting::set('ceirku_api_url', 'https://ceirku.org/api');
        Setting::set('ceirku_username', 'Igshax12');
        Setting::set('ceirku_api_key', 'valid_key_123');

        $this->artisan('ceirku:sync-orders')
            ->assertExitCode(0);

        $this->assertEquals('SUCCESS', $txSuccess->fresh()->metadata['ceirku_status']);
        $this->assertEquals('SUCCESS_ACTIVE', $txSuccess->fresh()->metadata['ceirku_result']);

        $this->assertEquals('FAILED', $txRejected->fresh()->metadata['ceirku_status']);
        $this->assertEquals('BLACKLISTED_DEVICE', $txRejected->fresh()->metadata['ceirku_result']);

        // Saldo awal 50.000 + Refund 180.000 = 230.000
        $this->assertEquals(230000, $wallet->fresh()->balance);

        $this->assertDatabaseHas('transactions', [
            'payable_type' => Wallet::class,
            'user_id'      => $user->id,
            'amount'       => 180000,
            'status'       => 'SUCCESS',
            'description'  => 'Refund otomatis via penolakan order pusat #ORD-202. Alasan: BLACKLISTED_DEVICE',
        ]);
    }
}
