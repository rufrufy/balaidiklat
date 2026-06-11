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

    public function test_sapa_balai_flow_creates_reservation_via_rules(): void
    {
        Http::fake();
        config(['services.kirimchat.require_webhook_secret' => false]);

        $this->seed(\Database\Seeders\ChatbotRuleSeeder::class);

        $kamar = Kamar::create([
            'kode' => 'KM-FLOW',
            'nama' => 'Kamar Flow',
            'tipe' => 'kamar',
            'harga_per_malam' => 200000,
            'fasilitas' => 'AC, kamar mandi dalam, wifi',
            'status' => 'available',
        ]);

        $phone = '6285600000001';
        $send = function (string $content) use ($phone): void {
            $this->postJson('/api/webhooks/kirimchat', [
                'event_type' => 'message.received',
                'data' => [
                    'customer_phone' => $phone,
                    'direction' => 'inbound',
                    'message_type' => 'text',
                    'content' => $content,
                    'channel' => 'whatsapp',
                ],
            ])->assertStatus(200);
        };

        $send('menu');
        $send('1'); // Informasi layanan -> list_kamar -> state pilih_kamar
        $this->assertSame('pilih_kamar', \App\Models\WhatsappSession::where('phone_number', $phone)->value('state'));

        $send('1'); // pilih kamar nomor 1 -> detail fasilitas -> state pesan_isi_data
        $this->assertSame('pesan_isi_data', \App\Models\WhatsappSession::where('phone_number', $phone)->value('state'));

        $send('Budi, 15-06-2026, 17-06-2026, 6285600000001'); // simpan reservasi -> main_menu
        $this->assertSame('main_menu', \App\Models\WhatsappSession::where('phone_number', $phone)->value('state'));

        // Reservasi tersimpan ke DB dari chatbot.
        $this->assertDatabaseHas(KamarReservasi::class, [
            'nama_pemesan' => 'Budi',
            'kamar_id' => $kamar->id,
            'tipe_penyewa' => 'perorangan',
            'phone_number' => '6285600000001',
            'total_harga' => 400000,
            'payment_status' => 'unpaid',
        ]);

        // Balasan terakhir berupa interactive (tombol menu utama).
        $this->assertDatabaseHas(WhatsappMessage::class, [
            'phone_number' => $phone,
            'direction' => 'outbound',
            'message_type' => 'interactive',
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
            'tipe' => 'kamar',
            'harga_per_malam' => 150000,
            'status' => 'available',
        ]);

        $response = $this->post(route('admin.reservasi.store'), [
            'nama_pemesan' => 'Siti',
            'tipe_penyewa' => 'instansi',
            'instansi' => 'BKPP',
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

    public function test_admin_reservasi_perorangan_does_not_require_instansi(): void
    {
        $this->actingAs(User::factory()->create());

        $kamar = Kamar::create([
            'kode' => 'KM-PR',
            'nama' => 'Kamar Perorangan',
            'tipe' => 'kamar',
            'harga_per_malam' => 100000,
            'status' => 'available',
        ]);

        $response = $this->post(route('admin.reservasi.store'), [
            'nama_pemesan' => 'Andi',
            'tipe_penyewa' => 'perorangan',
            'kamar_id' => $kamar->id,
            'tanggal_masuk' => '2026-06-10',
            'tanggal_keluar' => '2026-06-11',
            'status' => 'pending',
        ]);

        $response->assertRedirect(route('admin.dashboard', ['section' => 'reservasi']));
        $this->assertDatabaseHas(KamarReservasi::class, [
            'nama_pemesan' => 'Andi',
            'tipe_penyewa' => 'perorangan',
            'instansi' => null,
            'kegiatan' => null,
            'payment_status' => 'unpaid',
        ]);
    }

    public function test_admin_reservasi_calculates_multiple_room_billing(): void
    {
        $this->actingAs(User::factory()->create());

        $kamarA = Kamar::create([
            'kode' => 'KM-02',
            'nama' => 'Kamar A',
            'tipe' => 'kamar',
            'harga_per_malam' => 100000,
            'status' => 'available',
        ]);
        $kamarB = Kamar::create([
            'kode' => 'KM-03',
            'nama' => 'Kamar B',
            'tipe' => 'ruang_kelas',
            'harga_per_malam' => 200000,
            'status' => 'available',
        ]);

        $response = $this->post(route('admin.reservasi.store'), [
            'nama_pemesan' => 'Doni',
            'tipe_penyewa' => 'instansi',
            'instansi' => 'BKPP',
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

    public function test_retribusi_billing_payload_has_static_and_dynamic_fields(): void
    {
        $reservasi = KamarReservasi::create([
            'kode' => 'RSV-RET-001',
            'nama_pemesan' => 'BKPP',
            'kegiatan' => 'Diklat',
            'jumlah_peserta' => 5,
            'status' => 'approved',
        ]);

        $billing = \App\Models\RetribusiBilling::create([
            'kamar_reservasi_id' => $reservasi->id,
            'tanggal' => '2026-06-11',
            'keterangan' => 'Sewa Diklat',
            'kredit' => 210000,
        ]);

        $payload = $billing->toRetribusiPayload();

        $this->assertSame('1111', $payload['noskpd']);
        $this->assertSame('2026', $payload['periode']);
        $this->assertSame('4 1 2', $payload['sts_ssrd']);
        $this->assertSame('BKPP', $payload['namapenyetor']);
        $this->assertSame('76|4.1.02.02.01.0005|Retribusi Pemakaian Ruangan Balai Diklat', $payload['rekening']);
        $this->assertSame('11-06-2026', $payload['tanggal']);
        $this->assertSame('Sewa Diklat', $payload['keterangan']);
        $this->assertSame('210000', $payload['kredit']);
    }

    public function test_retribusi_api_returns_payload(): void
    {
        $reservasi = KamarReservasi::create([
            'kode' => 'RSV-RET-002',
            'nama_pemesan' => 'BKPP',
            'kegiatan' => 'Diklat',
            'jumlah_peserta' => 5,
            'status' => 'approved',
        ]);

        $billing = \App\Models\RetribusiBilling::create([
            'kamar_reservasi_id' => $reservasi->id,
            'tanggal' => '2026-06-11',
            'keterangan' => 'Sewa Diklat',
            'kredit' => 210000,
        ]);

        $response = $this->getJson(route('api.retribusi.show', $billing));

        $response->assertStatus(200);
        $response->assertJson(['noskpd' => '1111', 'kredit' => '210000', 'keterangan' => 'Sewa Diklat']);
    }

    public function test_availability_service_parses_date_range(): void
    {
        $service = new \App\Services\KamarAvailabilityService();

        $this->assertSame(['2026-06-15', '2026-06-17'], $service->parseDateInput('15-06-2026 sampai 17-06-2026'));
        $this->assertSame(['2026-06-15', '2026-06-16'], $service->parseDateInput('15-06-2026'));
        $this->assertNull($service->parseDateInput('tidak ada tanggal'));
    }
}
