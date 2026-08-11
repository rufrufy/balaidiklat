<?php

namespace Database\Seeders;

use App\Models\ChatbotTemplate;
use Illuminate\Database\Seeder;

class ChatbotTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            // === PROMPT (minta input) ===
            [
                'key' => 'prompt_jumlah_unit',
                'label' => 'Minta jumlah unit (kamar)',
                'category' => 'prompt',
                'content' => "Jenis kelas terpilih: *{{jenis_kelas}}*\nTarif: Rp{{harga}}/{{harga_label}}\nTersedia: {{tersedia}} unit\nFasilitas: {{fasilitas}}\n\nSilakan kirim *Jumlah unit* yang ingin dipesan.\nContoh: 1",
                'description' => 'Dikirim setelah user pilih jenis kamar (is_kamar=true)',
            ],
            [
                'key' => 'prompt_jumlah_hari',
                'label' => 'Minta jumlah hari (non-kamar)',
                'category' => 'prompt',
                'content' => "Jenis terpilih: *{{jenis_kelas}}*\nTarif: Rp{{harga}}/{{harga_label}}\nTersedia: {{tersedia}} unit\nFasilitas: {{fasilitas}}\n\nSilakan kirim *Jumlah hari* yang ingin dipesan.\nContoh: 3",
                'description' => 'Dikirim setelah user pilih ruang non-kamar (is_kamar=false)',
            ],
            [
                'key' => 'prompt_tanggal_masuk',
                'label' => 'Minta tanggal masuk',
                'category' => 'prompt',
                'content' => "Jumlah unit: *{{jumlah}}*\n\nSilakan kirim *Tanggal Mulai* sewa dengan format DD-MM-YYYY (tanggal-bulan-tahun).\nContoh: 17-08-2026",
                'description' => 'Dikirim setelah user input jumlah',
            ],
            [
                'key' => 'prompt_tanggal_keluar',
                'label' => 'Minta tanggal keluar',
                'category' => 'prompt',
                'content' => "Tanggal mulai: *{{tanggal_masuk}}*\n\nSilakan kirim *Tanggal Selesai* sewa dengan format DD-MM-YYYY (tanggal-bulan-tahun).\nContoh: 18-08-2026",
                'description' => 'Dikirim setelah user input tanggal masuk (kamar)',
            ],
            [
                'key' => 'prompt_tanggal_masuk_non_kamar',
                'label' => 'Minta tanggal masuk (non-kamar)',
                'category' => 'prompt',
                'content' => "Tanggal: *{{tanggal_masuk}} s/d {{tanggal_keluar}}* ({{jumlah_hari}} hari)\n\nSilakan kirim *Nama* pemesan.\nContoh: Budi Santoso",
                'description' => 'Dikirim setelah user input tanggal (non-kamar, auto-hitung keluar)',
            ],
            [
                'key' => 'prompt_nama',
                'label' => 'Minta nama pemesan',
                'category' => 'prompt',
                'content' => "Tanggal selesai: *{{tanggal_keluar}}*\n\nSilakan kirim *Nama* pemesan.\nContoh: Budi Santoso",
                'description' => 'Dikirim setelah user input tanggal keluar (kamar)',
            ],
            [
                'key' => 'prompt_no_hp',
                'label' => 'Minta nomor HP',
                'category' => 'prompt',
                'content' => "Nama pemesan: *{{nama}}*\n\nTerakhir, silakan kirim *No. WhatsApp/HP* yang bisa dihubungi.\nKetik *sama* untuk menggunakan nomor ini ({{phone_number}}).",
                'description' => 'Dikirim setelah user input nama',
            ],
            [
                'key' => 'prompt_upload_bukti',
                'label' => 'Minta upload bukti bayar',
                'category' => 'prompt',
                'content' => "Silakan kirim *foto bukti pembayaran* (maksimal 2MB) langsung ke chat ini. Setelah bukti diterima, sistem akan cek status pembayaran ke e-Retribusi Bapenda secara otomatis. Ketik *menu* untuk kembali.",
                'description' => 'Dikirim saat state=pesan_upload_bukti (dari rule)',
            ],
            [
                'key' => 'prompt_tanggal_cek',
                'label' => 'Minta tanggal cek ketersediaan',
                'category' => 'prompt',
                'content' => "Mohon kirim tanggal yang ingin dicek.\nContoh: 15-06-2026 atau 15-06-2026 sampai 17-06-2026.",
                'description' => 'Dikirim saat action=check_availability dan input tidak terparse',
            ],

            // === ERROR ===
            [
                'key' => 'error_invalid_jumlah',
                'label' => 'Error: jumlah unit tidak valid',
                'category' => 'error',
                'content' => "Mohon kirim *Jumlah unit* yang valid (angka minimal 1).\nContoh: 1",
            ],
            [
                'key' => 'error_invalid_jumlah_hari',
                'label' => 'Error: jumlah hari tidak valid',
                'category' => 'error',
                'content' => "Mohon kirim *Jumlah hari* yang valid (angka minimal 1).\nContoh: 3",
            ],
            [
                'key' => 'error_invalid_date_masuk',
                'label' => 'Error: format tanggal masuk salah',
                'category' => 'error',
                'content' => "Format tanggal tidak sesuai. Silakan kirim *Tanggal Mulai* dengan format DD-MM-YYYY (tanggal-bulan-tahun).\nContoh: 17-08-2026",
            ],
            [
                'key' => 'error_invalid_date_keluar',
                'label' => 'Error: format tanggal keluar salah',
                'category' => 'error',
                'content' => "Format tanggal tidak sesuai. Silakan kirim *Tanggal Selesai* dengan format DD-MM-YYYY (tanggal-bulan-tahun).\nContoh: 18-08-2026",
            ],
            [
                'key' => 'error_date_before_today',
                'label' => 'Error: tanggal sebelum hari ini',
                'category' => 'error',
                'content' => "Tanggal mulai tidak boleh sebelum hari ini ({{today}}). Silakan kirim *Tanggal Mulai* yang benar.\nContoh: {{today}}",
            ],
            [
                'key' => 'error_date_keluar_before_masuk',
                'label' => 'Error: tanggal selesai < tanggal masuk',
                'category' => 'error',
                'content' => "Tanggal selesai harus setelah tanggal mulai ({{tanggal_masuk}}). Silakan kirim *Tanggal Selesai* yang benar.\nContoh: 18-08-2026",
            ],
            [
                'key' => 'error_invalid_choice',
                'label' => 'Error: pilihan tidak dikenali',
                'category' => 'error',
                'content' => "Maaf, pilihan tidak dikenali.\nSilakan ketik *menu* atau tekan tombol di bawah untuk kembali ke menu utama.",
            ],
            [
                'key' => 'error_invalid_nama',
                'label' => 'Error: nama tidak valid',
                'category' => 'error',
                'content' => "Silakan kirim *Nama* pemesan.\nContoh: Budi Santoso",
            ],
            [
                'key' => 'error_kamar_not_found',
                'label' => 'Error: pilihan jenis kamar tidak dikenali',
                'category' => 'error',
                'content' => "Pilihan tidak dikenali. Ketik nomor jenis kelas yang ada di daftar, atau kembali ke menu utama.",
            ],
            [
                'key' => 'error_reservasi_not_found',
                'label' => 'Error: reservasi tidak ditemukan',
                'category' => 'error',
                'content' => "Maaf, data reservasi tidak ditemukan. Ketik *menu* untuk kembali ke menu utama.",
            ],
            [
                'key' => 'error_stok_tidak_cukup',
                'label' => 'Error: stok kamar tidak cukup',
                'category' => 'error',
                'content' => "Maaf, jumlah pemesanan kamar melebihi ketersediaan.\n\nJenis Kelas: {{jenis_kelas}}\nTanggal: {{tanggal_masuk}} s/d {{tanggal_keluar}}\nDiminta: {{jumlah}} unit\nTersedia: {{tersedia}} unit\n\nSilakan kurangi jumlah unit atau pilih tanggal lain. Ketik *menu* untuk kembali.",
            ],
            [
                'key' => 'error_stok_tidak_cukup_landing',
                'label' => 'Error: stok tidak cukup (landing)',
                'category' => 'error',
                'content' => "Maaf, jumlah pemesanan kamar melebihi ketersediaan.\n\nJenis Kelas: {{jenis_kelas}}\nTanggal: {{tanggal_masuk}} s/d {{tanggal_keluar}}\nDiminta: {{jumlah}} unit\nTersedia: {{tersedia}} unit\n\nSilakan kurangi jumlah unit atau pilih tanggal/kamar lain. Ketik *menu* untuk kembali.",
            ],
            [
                'key' => 'error_stok_konfirmasi_landing',
                'label' => 'Error: stok berkurang saat konfirmasi',
                'category' => 'error',
                'content' => "Maaf, saat konfirmasi ketersediaan kamar berkurang.\n\nJenis Kelas: {{jenis_kelas}}\nTersedia: {{tersedia}} unit\nDiminta: {{jumlah}} unit\n\nSilakan ulangi pemesanan dengan jumlah yang lebih kecil. Ketik *menu* untuk kembali.",
            ],
            [
                'key' => 'error_foto_invalid',
                'label' => 'Error: bukti bayar bukan gambar',
                'category' => 'error',
                'content' => "Maaf, file yang dikirim bukan gambar yang valid. Silakan kirim foto bukti pembayaran asli (JPG/PNG/WebP).",
            ],
            [
                'key' => 'error_foto_too_large',
                'label' => 'Error: foto > 2MB',
                'category' => 'error',
                'content' => "Ukuran foto melebihi 2MB. Silakan kirim foto bukti pembayaran yang lebih kecil.",
            ],
            [
                'key' => 'error_foto_process_failed',
                'label' => 'Error: foto gagal diproses',
                'category' => 'error',
                'content' => "Maaf, bukti pembayaran gagal diproses. Silakan kirim ulang fotonya.",
            ],
            [
                'key' => 'error_booking_not_found',
                'label' => 'Error: kode booking tidak ditemukan',
                'category' => 'error',
                'content' => "Kode booking \"{{kode}}\" tidak ditemukan. Pastikan kode benar, atau kembali ke menu utama.",
            ],
            [
                'key' => 'error_no_reservasi_bukti',
                'label' => 'Error: reservasi tidak ada saat upload bukti',
                'category' => 'error',
                'content' => "Maaf, kami tidak menemukan reservasi terkait. Silakan kembali ke menu utama.",
            ],
            [
                'key' => 'error_invalid_rating',
                'label' => 'Error: rating tidak valid',
                'category' => 'error',
                'content' => "Mohon berikan rating antara 1 sampai 5:\n\n1 \u{2B50} Sangat Tidak Puas\n2 \u{2B50}\u{2B50} Tidak Puas\n3 \u{2B50}\u{2B50}\u{2B50} Cukup\n4 \u{2B50}\u{2B50}\u{2B50}\u{2B50} Puas\n5 \u{2B50}\u{2B50}\u{2B50}\u{2B50}\u{2B50} Sangat Puas",
            ],

            // === INFO ===
            [
                'key' => 'info_kamar_list',
                'label' => 'Info: daftar kamar tersedia',
                'category' => 'info',
                'content' => "INFORMASI LAYANAN BALAI DIKLAT KOTA SEMARANG\n\nBalai Diklat menyediakan layanan sewa kamar dan ruang kelas untuk kegiatan diklat, rapat, maupun kegiatan resmi lainnya.\n\nKetersediaan hari ini ({{today}}):\n\n{{kamar_list}}\n\nKetik nomor jenis layanan yang ingin dipesan, atau ketik *menu* untuk kembali.",
                'description' => 'Dikirim saat user pilih info layanan (action=list_kamar)',
            ],
            [
                'key' => 'info_kamar_empty',
                'label' => 'Info: tidak ada kamar tersedia',
                'category' => 'info',
                'content' => "Mohon maaf, belum ada data jenis kelas yang tersedia saat ini.",
            ],
            [
                'key' => 'info_availability_empty',
                'label' => 'Info: tidak ada kamar di tanggal tsb',
                'category' => 'info',
                'content' => "Maaf, tidak ada kamar/kelas yang Tersedia pada {{tanggal_masuk}} s/d {{tanggal_keluar}}.\nSilakan kirim tanggal lain, atau kembali ke menu utama.",
            ],
            [
                'key' => 'info_availability_list',
                'label' => 'Info: kamar tersedia di tanggal tsb',
                'category' => 'info',
                'content' => "Kamar/kelas Tersedia {{tanggal_masuk}} s/d {{tanggal_keluar}}:\n{{kamar_list}}\n\nSilakan isi data pemesanan dengan format:\nNama, Instansi, Kegiatan, Jumlah peserta\nContoh: Budi, BKPP, Diklat ASN, 20",
            ],

            // === SUCCESS ===
            [
                'key' => 'success_reservasi_created',
                'label' => 'Success: reservasi dibuat',
                'category' => 'success',
                'content' => "Reservasi berhasil dibuat!\n\nKode booking: {{kode}}\n{{detail}}Total: Rp{{total}}\nStatus: menunggu konfirmasi & pembayaran.\n\nTerima kasih telah memesan di Balai Diklat Kota Semarang.",
                'description' => 'Dikirim setelah reservasi tersimpan di DB',
            ],
            [
                'key' => 'success_laporan_saved',
                'label' => 'Success: laporan gangguan tersimpan',
                'category' => 'success',
                'content' => "Laporan gangguan Anda sudah kami terima dan akan ditindaklanjuti. Terima kasih.",
            ],
            [
                'key' => 'success_saran_saved',
                'label' => 'Success: saran tersimpan',
                'category' => 'success',
                'content' => "Saran Anda sudah kami terima dan akan ditindaklanjuti. Terima kasih.",
            ],
            [
                'key' => 'success_survey_saved',
                'label' => 'Success: survey tersimpan',
                'category' => 'success',
                'content' => "Terima kasih atas survey kepuasan Anda! Masukan Anda sangat berarti untuk peningkatan layanan kami. \u{1F64F}",
            ],

            // === PAYMENT ===
            [
                'key' => 'payment_processing',
                'label' => 'Info: sedang proses billing',
                'category' => 'info',
                'content' => "Sedang memproses pembayaran Anda ke sistem e-Retribusi Bapenda...\n\nMohon tunggu sebentar.",
            ],
            [
                'key' => 'payment_billing_failed',
                'label' => 'Error: billing Bapenda gagal',
                'category' => 'error',
                'content' => "Maaf, terjadi kesalahan saat membuat billing e-Retribusi. Tim kami akan menghubungi Anda shortly.\n\nKetik *menu* untuk kembali, atau hubungi admin langsung.",
            ],
            [
                'key' => 'payment_qris_caption',
                'label' => 'Caption QRIS',
                'category' => 'info',
                'content' => "Pembayaran via QRIS\n\nReservasi: {{kode}}\nNominal: Rp{{total}}\nBerlaku sampai: {{expired}}\n\n{{link}}\n\nScan QR code di atas atau klik link untuk membayar.\n\nSetelah membayar, *kirim foto bukti pembayaran* langsung ke chat ini. Terima kasih.",
            ],
            [
                'key' => 'payment_qris_no_image',
                'label' => 'Info QRIS tanpa gambar',
                'category' => 'info',
                'content' => "Pembayaran via QRIS\n\nReservasi: {{kode}}\nNominal: Rp{{total}}\nBerlaku sampai: {{expired}}\n\n{{link}}\n\nLink QRIS belum tersedia. Silakan ketik *bayar* lagi beberapa saat lagi atau hubungi admin.\n\nSetelah membayar via QRIS, *kirim foto bukti pembayaran* langsung ke chat ini. Terima kasih.",
            ],
            [
                'key' => 'payment_transfer_instruction',
                'label' => 'Instruksi transfer bank',
                'category' => 'info',
                'content' => "Pembayaran via {{bank_label}}\n\nReservasi: {{kode}}\nNominal: Rp{{total}}\nKode bayar: {{kode_bayar}}\n\n{{instruksi_bank}}\n\nSetelah transfer, *kirim foto bukti pembayaran* langsung ke chat ini. Sistem akan menyimpan bukti dan cek status pembayaran ke e-Retribusi Bapenda secara otomatis. Terima kasih.",
            ],
            [
                'key' => 'payment_check_status',
                'label' => 'Info: cek status bayar',
                'category' => 'info',
                'content' => "Sedang memeriksa status pembayaran...",
            ],
            [
                'key' => 'payment_status_lunas',
                'label' => 'Info: pembayaran lunas',
                'category' => 'success',
                'content' => "Status pembayaran: *LUNAS*\nTanggal bayar: {{tanggal_bayar}}\nKode booking: {{kode}}\n\nTerima kasih telah melakukan pembayaran.",
            ],
            [
                'key' => 'payment_status_belum',
                'label' => 'Info: pembayaran belum lunas',
                'category' => 'info',
                'content' => "Status pembayaran: *BELUM LUNAS*\nKode booking: {{kode}}\n\nSilakan lanjutkan pembayaran.",
            ],
            [
                'key' => 'payment_no_billing',
                'label' => 'Info: belum ada billing',
                'category' => 'info',
                'content' => "Belum ada billing e-Retribusi untuk reservasi ini.",
            ],

            // === BUKTI BAYAR ===
            [
                'key' => 'bukti_verified_lunas',
                'label' => 'Bukti bayar: terverifikasi lunas',
                'category' => 'success',
                'content' => "Terima kasih! Bukti pembayaran untuk booking *{{kode}}* sudah kami terima.\n\nStatus pembayaran: *LUNAS* \u2705\nPembayaran Anda telah terverifikasi otomatis oleh sistem.",
            ],
            [
                'key' => 'bukti_diterima_pending',
                'label' => 'Bukti bayar: diterima menunggu verif',
                'category' => 'info',
                'content' => "Terima kasih! Bukti pembayaran untuk booking *{{kode}}* sudah kami terima.\n\nBukti pembayaran Anda akan *diverifikasi oleh admin*.\nKami akan mengonfirmasi setelah pembayaran diverifikasi.\n\nKetik *menu* untuk kembali ke menu utama.",
            ],

            // === MENU UTAMA ===
            [
                'key' => 'menu_utama_body',
                'label' => 'Body menu utama',
                'category' => 'menu',
                'content' => "Halo, {{customer_name}} Selamat Datang di SAPA BALAI \u{1F44B}.\nSmart Chatbot Layanan Balai Diklat Kota Semarang.\n\nSilakan pilih menu layanan di bawah ini.",
            ],

            // === GANGGUAN ===
            [
                'key' => 'gangguan_prompt_nomor',
                'label' => 'Minta nomor kamar gangguan',
                'category' => 'prompt',
                'content' => "Laporan Gangguan.\nMohon kirim nomor kamar yang mengalami gangguan.\nContoh: A-101",
            ],
            [
                'key' => 'gangguan_prompt_isi',
                'label' => 'Minta isi laporan gangguan',
                'category' => 'prompt',
                'content' => "Nomor kamar: *{{nomor_kamar}}*\n\nSilakan kirimkan detail gangguan yang terjadi.",
            ],
            [
                'key' => 'gangguan_empty_nomor',
                'label' => 'Error: nomor kamar kosong',
                'category' => 'error',
                'content' => "Mohon kirim nomor kamar yang mengalami gangguan.\nContoh: A-101",
            ],

            // === SARAN ===
            [
                'key' => 'saran_prompt',
                'label' => 'Minta saran',
                'category' => 'prompt',
                'content' => "Saran.\nSilakan tuliskan saran Anda untuk pelayanan Balai Diklat.",
            ],

            // === SURVEY ===
            [
                'key' => 'survey_prompt_rating',
                'label' => 'Minta rating survey',
                'category' => 'prompt',
                'content' => "Survey Kepuasan.\nSeberapa puas Anda dengan layanan kami? Balas dengan angka 1 (kurang) sampai 5 (sangat puas).",
            ],
            [
                'key' => 'survey_prompt_komentar',
                'label' => 'Minta komentar setelah rating',
                'category' => 'prompt',
                'content' => "Rating Anda: {{stars}} ({{rating}}/5)\n\nSilakan kirim masukan atau komentar Anda tentang layanan kami.",
            ],

            // === CUSTOMER CARE ===
            [
                'key' => 'customer_care_handoff',
                'label' => 'Customer care handoff',
                'category' => 'info',
                'content' => "Percakapan Anda akan diteruskan ke tim *Customer Care* kami.\nMohon tunggu, tim kami akan segera membalas pesan Anda.",
            ],
            [
                'key' => 'customer_care_prompt_booking',
                'label' => 'Minta kode booking (customer care)',
                'category' => 'prompt',
                'content' => "Customer Care.\nMasukkan kode booking Anda untuk melihat detail pemesanan.\nContoh: BKPP-20260611120000-123",
            ],

            // === CEK BOOKING ===
            [
                'key' => 'cek_booking_found',
                'label' => 'Detail booking ditemukan',
                'category' => 'info',
                'content' => "Detail booking {{kode}}:\n\nPemesan: {{nama}}\nJenis kelas: {{jenis_kelas}}\nTanggal: {{tanggal_masuk}} s/d {{tanggal_keluar}}\nTotal: Rp{{total}}\nStatus reservasi: {{status}}\nStatus pembayaran: {{payment_status}}",
            ],

            // === LANDING FORM ===
            [
                'key' => 'error_landing_unknown',
                'label' => 'Error: form landing tidak dikenali',
                'category' => 'error',
                'content' => "Maaf, format pemesanan dari landing tidak dikenali. Silakan kirim ulang formulir dari halaman web atau ketik *menu*.",
            ],
            [
                'key' => 'error_landing_incomplete',
                'label' => 'Error: data landing tidak lengkap',
                'category' => 'error',
                'content' => "Data pemesanan tidak lengkap (jenis kelas/tanggal belum diisi). Silakan lengkapi formulir di landing page.",
            ],
            [
                'key' => 'landing_konfirmasi',
                'label' => 'Konfirmasi pemesanan landing',
                'category' => 'info',
                'content' => "RINGKASAN PEMESANAN (dari Landing Page)\n\nNama: {{nama}}\nNo WA: {{phone_number}}\nTipe Penyewa: {{tipe_penyewa}}\n{{detail}}Total: Rp{{total}}\n\nApakah Anda yakin ingin memesan?",
            ],
            [
                'key' => 'landing_success',
                'label' => 'Success: reservasi landing dibuat',
                'category' => 'success',
                'content' => "Reservasi berhasil dibuat!\n\nKode booking: {{kode}}\n{{detail}}Total: Rp{{total}}\nStatus: menunggu pembayaran.\n\nPilih metode pembayaran atau kembali ke menu utama.",
            ],
        ];

        foreach ($templates as $t) {
            ChatbotTemplate::updateOrCreate(['key' => $t['key']], $t);
        }
    }
}
