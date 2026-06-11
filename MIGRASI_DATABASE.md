# Migrasi Database Balaidiklat

Dokumen ini berisi langkah migrasi database untuk fitur KirimChat webhook, login admin, manajemen kamar, reservasi, aturan chatbot, dan rekap bulanan.

## Konfigurasi Database

Pastikan `.env` memakai database lokal:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=balaidiklat
DB_USERNAME=root
DB_PASSWORD=
```

Pastikan database `balaidiklat` sudah dibuat di MySQL.

## Migrasi Yang Ditambahkan

File migrasi:

- `database/migrations/2026_06_10_000000_create_whatsapp_sessions_table.php`
- `database/migrations/2026_06_10_000001_create_whatsapp_messages_table.php`
- `database/migrations/2026_06_10_000002_create_kamars_table.php`
- `database/migrations/2026_06_10_000003_create_kamar_reservasis_table.php`
- `database/migrations/2026_06_10_000004_create_chatbot_rules_table.php`

Tabel hasil migrasi:

- `whatsapp_sessions`: menyimpan nomor WhatsApp, state menu chatbot, context, dan waktu pesan terakhir.
- `whatsapp_messages`: menyimpan log pesan inbound/outbound dari webhook dan KirimChat API.
- `kamars`: menyimpan data kamar asli, status, kapasitas, slot tersedia, dan path foto kamar.
- `kamar_reservasis`: menyimpan reservasi kamar, pemesan, tanggal, alokasi kamar, status, dan catatan admin.
- `chatbot_rules`: menyimpan keyword, tipe pencocokan, state, prioritas, status aktif, dan teks balasan otomatis webhook.

## Jalankan Migrasi

```bash
php artisan migrate
```

## Buat User Admin

Seeder admin tersedia di `database/seeders/AdminUserSeeder.php`.

```bash
php artisan db:seed --class=AdminUserSeeder
```

Akun awal:

- Username: `admin`
- Password: `semarang1`

## Storage Foto Kamar

Foto kamar disimpan di disk `public`, folder `storage/app/public/kamar`.

Jalankan symbolic link storage:

```bash
php artisan storage:link
```

## Webhook KirimChat

Endpoint aktif:

```text
https://balaidiklat.xruffy.me/api/webhooks/kirimchat
```

Konfigurasi `.env`:

```env
KIRIMCHAT_BASE_URL=https://api-prod.kirim.chat/api/v1/public
KIRIMCHAT_API_KEY=isi_api_key_kirimchat
KIRIMCHAT_WEBHOOK_SECRET=
KIRIMCHAT_REQUIRE_WEBHOOK_SECRET=false
```

`KIRIMCHAT_REQUIRE_WEBHOOK_SECRET=false` membuat test webhook KirimChat tidak ditolak 401 ketika KirimChat tidak mengirim signature.

Jika nanti ingin memaksa validasi signature KirimChat:

```env
KIRIMCHAT_WEBHOOK_SECRET=secret_yang_sama_dengan_kirimchat
KIRIMCHAT_REQUIRE_WEBHOOK_SECRET=true
```

Lalu clear cache config:

```bash
php artisan config:clear
```

## URL Admin

- Login admin: `/admin/login`
- Dashboard admin: `/admin/dashboard`
- Tambah kamar: form pada menu `Manajemen Kamar`
- Edit kamar: tombol `Edit` pada kartu/tabel kamar
- Hapus kamar: tombol `Hapus` pada kartu/tabel kamar
- Rekap bulanan: `/admin/rekap-bulanan`

Dashboard admin wajib login. Landing page `/` menampilkan data kamar dari tabel `kamars`, bukan data dummy.

## Fitur Dashboard Tambahan

- Manajemen kamar: tambah, edit, hapus, upload foto, dan duplikat kamar dengan kode baru.
- Manajemen reservasi: tambah reservasi, edit reservasi, alokasi kamar, update status `pending`, `approved`, atau `rejected`, dan hapus reservasi.
- Aturan Chatbot: tambah, edit, hapus, aktif/nonaktif aturan balasan otomatis berdasarkan keyword dan state.
- WhatsApp Chat: payload KirimChat `message.received` dari channel `whatsapp` disimpan ke `whatsapp_messages` dan `whatsapp_sessions` agar tampil realtime dengan polling otomatis di dashboard.
- Rekap bulanan: pilih bulan di `/admin/rekap-bulanan`, lalu klik `Export PDF` untuk mencetak atau menyimpan sebagai PDF lewat browser.
- Lacak Booking: form publik di landing page mencari data reservasi berdasarkan kode booking dan nomor WhatsApp opsional.
