<?php

namespace Tests\Feature;

use App\Models\ChatbotRule;
use App\Models\Kamar;
use App\Models\KamarReservasi;
use App\Models\User;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_kirimchat_webhook_does_not_require_secret_by_default(): void
    {
        config(['services.kirimchat.require_webhook_secret' => false]);

        $response = $this->postJson('/api/webhooks/kirimchat', [
            'message' => 'test webhook',
        ]);

        $response->assertStatus(200);
    }

    public function test_kirimchat_webhook_stores_inbound_whatsapp_message(): void
    {
        Http::fake();
        config(['services.kirimchat.require_webhook_secret' => false]);

        $response = $this->postJson('/api/webhooks/kirimchat', [
            'event_type' => 'message.received',
            'event_id' => 'evt_test_001',
            'data' => [
                'message_id' => 'wamid.test',
                'customer_phone' => '6281234567890',
                'direction' => 'inbound',
                'message_type' => 'text',
                'content' => 'Halo',
                'channel' => 'whatsapp',
            ],
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas(WhatsappMessage::class, [
            'phone_number' => '6281234567890',
            'direction' => 'inbound',
            'message_type' => 'text',
            'message_text' => 'halo',
        ]);
    }

    public function test_kirimchat_webhook_accepts_valid_signature_when_required(): void
    {
        config([
            'services.kirimchat.require_webhook_secret' => true,
            'services.kirimchat.webhook_secret' => 'secret-test',
        ]);

        $payload = json_encode(['message' => 'test webhook'], JSON_THROW_ON_ERROR);
        $signature = 'sha256='.hash_hmac('sha256', $payload, 'secret-test');

        $response = $this->call('POST', '/api/webhooks/kirimchat', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_KIRIMCHAT_SIGNATURE' => $signature,
        ], $payload);

        $response->assertStatus(200);
    }

    public function test_chatbot_rule_replies_to_matching_inbound_message(): void
    {
        Http::fake();
        config(['services.kirimchat.require_webhook_secret' => false]);

        ChatbotRule::create([
            'nama' => 'Info aula',
            'keyword' => 'aula',
            'match_type' => 'contains',
            'reply_text' => 'Info aula tersedia.',
            'priority' => 1,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/webhooks/kirimchat', [
            'event_type' => 'message.received',
            'data' => [
                'customer_phone' => '6281234567890',
                'direction' => 'inbound',
                'message_type' => 'text',
                'content' => 'Saya mau info aula',
                'channel' => 'whatsapp',
            ],
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas(WhatsappMessage::class, [
            'phone_number' => '6281234567890',
            'direction' => 'outbound',
            'message_text' => 'Info aula tersedia.',
        ]);
    }

    public function test_kirimchat_webhook_ignores_outbound_status_events(): void
    {
        config(['services.kirimchat.require_webhook_secret' => false]);

        $response = $this->postJson('/api/webhooks/kirimchat', [
            'event_type' => 'message.sent',
            'data' => [
                'customer_phone' => '6281234567890',
                'direction' => 'outbound',
                'message_type' => 'text',
                'content' => 'Balasan bot',
                'channel' => 'whatsapp',
            ],
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseMissing(WhatsappMessage::class, [
            'phone_number' => '6281234567890',
            'message_text' => 'Balasan bot',
        ]);
    }

    public function test_landing_can_track_booking_code(): void
    {
        KamarReservasi::create([
            'kode' => 'RSV-TEST-001',
            'nama_pemesan' => 'Budi',
            'kegiatan' => 'Diklat',
            'phone_number' => '628111',
            'jumlah_peserta' => 10,
            'status' => 'approved',
        ]);

        $response = $this->post('/lacak-booking', [
            'kode' => 'RSV-TEST-001',
            'phone_number' => '628111',
        ]);

        $response->assertStatus(200);
        $response->assertSee('RSV-TEST-001');
        $response->assertSee('approved');
    }

    public function test_admin_reservasi_calculates_single_room_billing(): void
    {
        $this->actingAs(User::factory()->create());

        $kamar = Kamar::create([
            'kode' => 'KM-01',
            'nama' => 'Kamar Utama',
            'gedung' => 'A',
            'jenis' => 'standard',
            'kapasitas' => 2,
            'tersedia' => 1,
            'harga_per_malam' => 150000,
            'status' => 'available',
        ]);

        $response = $this->post(route('admin.reservasi.store'), [
            'nama_pemesan' => 'Siti',
            'kegiatan' => 'Diklat Teknis',
            'kamar_id' => $kamar->id,
            'tanggal_masuk' => '2026-06-10',
            'tanggal_keluar' => '2026-06-12',
            'jumlah_peserta' => 1,
            'status' => 'pending',
            'payment_status' => 'partial',
        ]);

        $response->assertRedirect(route('admin.dashboard', ['section' => 'reservasi']));
        $this->assertDatabaseHas(KamarReservasi::class, [
            'nama_pemesan' => 'Siti',
            'kamar_id' => $kamar->id,
            'durasi_hari' => 2,
            'total_harga' => 300000,
            'payment_status' => 'partial',
        ]);
    }

    public function test_admin_reservasi_calculates_multiple_room_billing(): void
    {
        $this->actingAs(User::factory()->create());

        $kamarA = Kamar::create([
            'kode' => 'KM-02',
            'nama' => 'Kamar A',
            'gedung' => 'A',
            'jenis' => 'standard',
            'kapasitas' => 2,
            'tersedia' => 1,
            'harga_per_malam' => 100000,
            'status' => 'available',
        ]);
        $kamarB = Kamar::create([
            'kode' => 'KM-03',
            'nama' => 'Kamar B',
            'gedung' => 'B',
            'jenis' => 'vip',
            'kapasitas' => 2,
            'tersedia' => 1,
            'harga_per_malam' => 200000,
            'status' => 'available',
        ]);

        $response = $this->post(route('admin.reservasi.store'), [
            'nama_pemesan' => 'Doni',
            'kegiatan' => 'Workshop',
            'jumlah_peserta' => 2,
            'status' => 'approved',
            'payment_status' => 'unpaid',
            'multiple_kamar' => '1',
            'items' => [
                ['kamar_id' => $kamarA->id, 'tanggal_masuk' => '2026-06-10', 'tanggal_keluar' => '2026-06-12'],
                ['kamar_id' => $kamarB->id, 'tanggal_masuk' => '2026-06-11', 'tanggal_keluar' => '2026-06-12'],
            ],
        ]);

        $response->assertRedirect(route('admin.dashboard', ['section' => 'reservasi']));
        $this->assertDatabaseHas(KamarReservasi::class, [
            'nama_pemesan' => 'Doni',
            'multiple_kamar' => true,
            'total_harga' => 400000,
            'payment_status' => 'unpaid',
        ]);
    }
}
