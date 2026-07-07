<?php

namespace Tests\Feature;

use App\Models\ChatbotRule;
use App\Models\Kamar;
use App\Models\KamarReservasi;
use App\Models\LayananPengaduan;
use App\Models\RetribusiBilling;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Models\WhatsappSession;
use App\Services\KamarAvailabilityService;
use Database\Seeders\ChatbotRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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

        $this->seed(ChatbotRuleSeeder::class);

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
        $this->assertSame('pilih_kamar', WhatsappSession::where('phone_number', $phone)->value('state'));

        $send('1'); // pilih kamar nomor 1 -> detail fasilitas -> state pesan_isi_data
        $this->assertSame('pesan_isi_data', WhatsappSession::where('phone_number', $phone)->value('state'));

        // Form key-value multiline + "WA: sama" -> pakai nomor pengirim.
        $send("Nama: Budi Santoso\nMasuk: 15-06-2026\nKeluar: 17-06-2026\nWA: sama");
        $this->assertSame('pesan_pembayaran', WhatsappSession::where('phone_number', $phone)->value('state'));

        // Reservasi tersimpan ke DB dari chatbot.
        $this->assertDatabaseHas(KamarReservasi::class, [
            'nama_pemesan' => 'Budi Santoso',
            'kamar_id' => $kamar->id,
            'tipe_penyewa' => 'perorangan',
            'phone_number' => $phone,
            'total_harga' => 400000,
            'payment_status' => 'unpaid',
        ]);

        // Balasan reservasi berupa interactive (tombol Menu Utama + Bayar).
        $this->assertDatabaseHas(WhatsappMessage::class, [
            'phone_number' => $phone,
            'direction' => 'outbound',
            'message_type' => 'interactive',
        ]);

        // Klik Bayar -> pilih metode -> QRIS.
        $send('bayar');
        $this->assertSame('pesan_metode_bayar', WhatsappSession::where('phone_number', $phone)->value('state'));

        $send('qris'); // QRIS -> minta upload bukti pembayaran
        $this->assertSame('pesan_upload_bukti', WhatsappSession::where('phone_number', $phone)->value('state'));
    }

    public function test_sapa_balai_payment_proof_upload_marks_paid(): void
    {
        Http::fake([
            'cdn.example.test/*' => Http::response("\xFF\xD8\xFFFAKEJPEG", 200, ['Content-Type' => 'image/jpeg']),
            'eretribusi.semarangkota.go.id/api/v2/prod/retribusi/store*' => Http::response([
                'success' => true,
                'data' => [
                    'id_billing' => 740000000001221,
                    'link_ssrd' => 'https://eretribusi.semarangkota.go.id/ssrd/test',
                    'no_ketetapan' => 'A123',
                ],
            ], 200),
            'eretribusi.semarangkota.go.id/api/v2/prod/retribusi/check*' => Http::response([
                'success' => true,
                'data' => ['tgl_bayar' => '2026-06-15 10:00:00'],
            ], 200),
            '*' => Http::response(['success' => true], 200),
        ]);
        config(['services.kirimchat.require_webhook_secret' => false]);

        $this->seed(ChatbotRuleSeeder::class);

        $kamar = Kamar::create([
            'kode' => 'KM-PAY',
            'nama' => 'Kamar Pay',
            'tipe' => 'kamar',
            'harga_per_malam' => 100000,
            'status' => 'available',
        ]);

        $reservasi = KamarReservasi::create([
            'kode' => 'RSV-PAYTEST-1',
            'nama_pemesan' => 'Citra',
            'tipe_penyewa' => 'perorangan',
            'phone_number' => '6285600000009',
            'kamar_id' => $kamar->id,
            'tanggal_masuk' => '2026-06-15',
            'tanggal_keluar' => '2026-06-16',
            'durasi_hari' => 1,
            'total_harga' => 100000,
            'status' => 'pending',
            'payment_status' => 'unpaid',
        ]);

        $phone = '6285600000009';
        $send = function (array $data) use ($phone): void {
            $this->postJson('/api/webhooks/kirimchat', array_merge([
                'event_type' => 'message.received',
            ], ['data' => array_merge([
                'customer_phone' => $phone,
                'direction' => 'inbound',
                'channel' => 'whatsapp',
            ], $data)]))->assertStatus(200);
        };

        // Customer care -> kode booking -> unpaid -> tombol Bayar.
        $send(['message_type' => 'text', 'content' => '6']);
        $send(['message_type' => 'text', 'content' => 'RSV-PAYTEST-1']);
        $this->assertSame('pesan_pembayaran', WhatsappSession::where('phone_number', $phone)->value('state'));

        $send(['message_type' => 'text', 'content' => 'bayar']);
        $send(['message_type' => 'text', 'content' => 'bank_bri']);
        $this->assertSame('pesan_upload_bukti', WhatsappSession::where('phone_number', $phone)->value('state'));

        // Kirim foto bukti -> tersimpan + status paid.
        $send(['message_type' => 'image', 'media_url' => 'https://cdn.example.test/bukti.jpg']);

        $reservasi->refresh();
        $this->assertSame('paid', $reservasi->payment_status);
        $this->assertNotNull($reservasi->bukti_pembayaran);
        Storage::disk('public')->assertExists($reservasi->bukti_pembayaran);
        $this->assertSame('main_menu', WhatsappSession::where('phone_number', $phone)->value('state'));
    }

    public function test_sapa_balai_laporan_gangguan_saved_to_db(): void
    {
        Http::fake();
        config(['services.kirimchat.require_webhook_secret' => false]);
        $this->seed(ChatbotRuleSeeder::class);

        $phone = '6285600000002';
        $send = function (string $content) use ($phone): void {
            $this->postJson('/api/webhooks/kirimchat', [
                'event_type' => 'message.received',
                'data' => [
                    'customer_phone' => $phone,
                    'customer_name' => 'Andi',
                    'direction' => 'inbound',
                    'message_type' => 'text',
                    'content' => $content,
                    'channel' => 'whatsapp',
                ],
            ])->assertStatus(200);
        };

        $send('menu');
        $send('3'); // Laporan gangguan
        $send('AC kamar 2 tidak dingin');

        $this->assertDatabaseHas(LayananPengaduan::class, [
            'jenis' => 'gangguan',
            'nama' => 'Andi',
            'phone_number' => $phone,
            'isi' => 'AC kamar 2 tidak dingin',
        ]);
        $this->assertSame('main_menu', WhatsappSession::where('phone_number', $phone)->value('state'));
    }

    public function test_sapa_balai_customer_care_cek_booking(): void
    {
        Http::fake();
        config(['services.kirimchat.require_webhook_secret' => false]);
        $this->seed(ChatbotRuleSeeder::class);

        KamarReservasi::create([
            'kode' => 'RSV-CC-001',
            'nama_pemesan' => 'Citra',
            'tipe_penyewa' => 'perorangan',
            'phone_number' => '628999',
            'status' => 'approved',
            'payment_status' => 'paid',
            'total_harga' => 200000,
        ]);

        $phone = '6285600000003';
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
        $send('6'); // Customer care -> minta kode booking
        $send('RSV-CC-001');

        $this->assertDatabaseHas(WhatsappMessage::class, [
            'phone_number' => $phone,
            'direction' => 'outbound',
            'message_type' => 'interactive',
        ]);
        $this->assertSame('main_menu', WhatsappSession::where('phone_number', $phone)->value('state'));
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

        $billing = RetribusiBilling::create([
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

        $billing = RetribusiBilling::create([
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
        $service = new KamarAvailabilityService;

        $this->assertSame(['2026-06-15', '2026-06-17'], $service->parseDateInput('15-06-2026 sampai 17-06-2026'));
        $this->assertSame(['2026-06-15', '2026-06-16'], $service->parseDateInput('15-06-2026'));
        $this->assertNull($service->parseDateInput('tidak ada tanggal'));
    }

    public function test_availability_only_includes_available_status(): void
    {
        $available = Kamar::create(['kode' => 'AV-1', 'nama' => 'Tersedia', 'tipe' => 'kamar', 'harga_per_malam' => 100000, 'status' => 'available']);
        Kamar::create(['kode' => 'AV-2', 'nama' => 'Penuh', 'tipe' => 'kamar', 'harga_per_malam' => 100000, 'status' => 'full']);
        Kamar::create(['kode' => 'AV-3', 'nama' => 'Perawatan', 'tipe' => 'kamar', 'harga_per_malam' => 100000, 'status' => 'maintenance']);

        $service = new KamarAvailabilityService;
        $rooms = $service->availableRooms('2026-06-15', '2026-06-17');

        $this->assertCount(1, $rooms);
        $this->assertSame($available->id, $rooms->first()->id);
    }

    public function test_admin_can_upload_kamar_photo(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());

        $response = $this->post(route('admin.kamar.store'), [
            'kode' => 'KM-FOTO',
            'nama' => 'Kamar Foto',
            'tipe' => 'kamar',
            'harga_per_malam' => 150000,
            'status' => 'available',
            'foto' => UploadedFile::fake()->create('foto.jpg', 500, 'image/jpeg'),
        ]);

        $response->assertRedirect(route('admin.dashboard', ['section' => 'kamar']));

        $kamar = Kamar::where('kode', 'KM-FOTO')->first();
        $this->assertNotNull($kamar);
        $this->assertNotNull($kamar->foto_path);
        Storage::disk('public')->assertExists($kamar->foto_path);
    }

    public function test_admin_kamar_store_rejects_oversized_photo(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());

        $response = $this->post(route('admin.kamar.store'), [
            'kode' => 'KM-BIG',
            'nama' => 'Kamar Besar',
            'tipe' => 'kamar',
            'harga_per_malam' => 150000,
            'status' => 'available',
            'foto' => UploadedFile::fake()->create('big.jpg', 3000, 'image/jpeg'),
        ]);

        $response->assertSessionHasErrors('foto');
        $this->assertNull(Kamar::where('kode', 'KM-BIG')->first());
    }
}
