# Panduan Deployment - Balai Diklat (balaidiklat.bkpp.dev)

Dokumen ini menjelaskan cara deploy aplikasi ke Portainer dengan auto-deploy dari GitHub ke `balaidiklat.bkpp.dev`.

---

## Arsitektur Deployment

``
GitHub (branch: master)
   │
   ▼ push
GitHub Actions: Build & Deploy Docker Image
   │
   ├─ Build Docker image (multi-stage)
   ├─ Push ke GHCR: ghcr.io/rufrufy/balaidiklat:latest
   ├─ Push tag SHA: ghcr.io/rufrufy/balaidiklat:sha-xxxx
   └─ Trigger Portainer Webhook
                       │
                       ▼
             Portainer Stack
             ├─ Pull image terbaru
             ├─ Restart container
             └─ Traefik reverse proxy
                       │
                       ▼
        Cloudflare DNS → balaidiklat.bkpp.dev
``

**Alur auto-deploy:**
1. Developer push/merge ke branch `master`
2. GitHub Actions membangun image Docker & push ke GitHub Container Registry (GHCR)
3. GitHub Actions memangil webhook Portainer
4. Portainer pull image terbaru & restart container
5. Traefik me-route traffic `balaidiklat.bkpp.dev` ke container
6. Cloudflare DNS mengarahkan domain ke server

---

## Prasyarat

### 1. Server dengan Docker + Portainer
- Docker Engine terinstall
- Portainer CE/BE terinstall dan berjalan
- Traefik reverse proxy sudah berjalan dengan TLS (Let's Encrypt)

### 2. Cloudflare DNS
- Domain `bkpp.dev` dikelola di Cloudflare
- DNS record untuk `balaidiklat` sudah mengarah ke IP server
- SSL/TLS mode: **Full (Strict)** atau **Full**

### 3. GitHub Repository
- Repository: `github.com/rufrufy/balaidiklat`
- Branch deploy: `master`
- Repository visibility: Public (atau Private dengan PAT untuk GHCR)

---

## Langkah 1: Setup Network Traefik di Server

Jika belum ada, buat network bernama `traefik`:

```bash
docker network create traefik
``

Verifikasi Traefik sudah berjalan dan terhubung ke network ini.

---

## Langkah 2: Generate APP_KEY

Generate Laravel application key untuk production:

```bash
php artisan key:generate --show
``

Simpan output (dimulai dengan `base64:...`) - akan digunakan di Portainer.

---

## Langkah 3: Setup Stack di Portainer

1. Login ke Portainer
2. Buka **Stacks** → **Add stack**
3. Pilih **Web editor**
4. Beri nama stack: `balaidiklat`
5. Paste isi file `docker-compose.yml` dari repository

### Konfigurasi Environment Variables

Di tab **Environment variables**, masukkan variabel berikut (lihat `.env.production.example` untuk referensi lengkap):

| Variable | Value | Keterangan |
|----------|-------|------------|
| `APP_IMAGE` | `ghcr.io/rufrufy/balaidiklat:latest` | Image dari GHCR |
| `APP_NAME` | `Balai Diklat` | Nama aplikasi |
| `APP_ENV` | `production` | Environment |
| `APP_KEY` | `base64:...` | Dari Langkah 2 |
| `APP_DEBUG` | `false` | Matikan debug di production |
| `DB_HOST` | `103.101.52.17` | Host MySQL |
| `DB_PORT` | `33064` | Port MySQL |
| `DB_DATABASE` | `balaidiklat` | Nama database |
| `DB_USERNAME` | `root` | Username DB |
| `DB_PASSWORD` | `your-strong-password` | Password DB |
| `RUN_MIGRATIONS` | `true` | Auto migrate saat container start |
| `KIRIMCHAT_API_KEY` | *(isi jika ada)* | API key KirimChat |
| `ERETRIBUSI_API_KEY` | *(isi jika ada)* | API key eRetribusi |

6. Klik **Deploy the stack**

---

## Langkah 4: Aktifkan Webhook di Portainer

Agar Portainer otomatis pull image baru saat GitHub Actions memangil:

1. Buka stack `balaidiklat` di Portainer
2. Klik tab **Update** atau **Editor**
3. Aktifkan **Service webhook** (toggle ON)
4. Portainer akan menampilkan URL webhook seperti:
  ```
  http://your-server-ip:900/api/webhooks/xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxx
  ```
5. **Salin URL webhook ini** - akan digunakan di GitHub

> **Catan:** Pastikan Portainer webhook endpoint dapat diakses. Jika Portainer di belakang firewall, pastikan port 900 (atau port Portainer Anda) terbuka, atau gunakan reverse proxy untuk endpoint webhook.

---

## Langkah 5: Konfigurasi GitHub Actions Variable

Tambahkan webhook URL Portainer ke GitHub repository variables:

1. Buka repository di GitHub → **Settings** → **Secrets and variables** → **Actions**
2. Pilih tab **Variables** (bukan Secrets - karena URL webhook tidak sensitif)
3. Klik **New repository variable**:
  - **Name:** `PORTAINER_WEBHOOK_URL`
  - **Value:** `http://your-server-ip:900/api/webhooks/xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxx`
4. Klik **Add variable**

> **Mengapa Variable bukan Secret?** Karena URL webhook Portainer tidak mengandung credentials sensitif dan perlu digunakan dalam `if` condition. GitHub Secrets tidak bisa digunakan di `if` expressions dengan mudah.

---

## Langkah 6: Verifikasi Auto-Deploy

Test alur auto-deploy:

1. Buat perubahan kecil di repository (misal update README)
2. Commit & push ke branch `master`:
  ```bash
  git add -A
  git commit -m "test: verify auto-deploy"
  git push origin master
  ```
3. Cek **GitHub Actions** tab - workflow "Build & Deploy Docker Image" harus berjalan:
  - Step "Generate image tags" ✓
  - Step "docker/login-action" ✓
  - Step "docker/build-push-action" ✓ (push ke GHCR)
  - Step "Trigger Portainer webhook" ✓
4. Cek **Portainer** → stack `balaidiklat` → container akan restart otomatis
5. Akses `https://balaidiklat.bkpp.dev` - aplikasi harus live!

---

## Konfigurasi Cloudflare (DNS + SSL)

### DNS Record

Pastikan ada A record (atau CNAME) untuk `balaidiklat`:

| Type | Name | Content | Proxy |
|------|------|---------|-------|
| A | `balaidiklat` | `IP_SERVER_ANDA` | Proxied (orange cloud) |

### SSL/TLS Settings

1. Buka **SSL/TLS** → **Overview**
2. Set encryption mode: **Full (Strict)**
3. (Opsional) Aktifkan **Always Use HTTPS** = ON
4. (Opsional) Aktifkan **Automatic HTTPS Rewrites** = ON

### Mengapa Full (Strict)?

Karena Traefik di server menangani TLS dengan Let's Encrypt (lihat `docker-compose.yml`):
```yaml
traefik.http.routers.balaidiklat.tls=true
traefik.http.routers.balaidiklat.tls.certresolver=letsencrypt
``
Cloudflare akan memvalidasi sertifikat Let's Encrypt Traefik.

---

## Image Taging & Rollback

Workflow GitHub Actions membangun 2 tag untuk setiap push:

| Tag | Format | Kegunan |
|-----|--------|----------|
| `:latest` | `ghcr.io/rufrufy/balaidiklat:latest` | Tag utama untuk production |
| `:sha-xxxx` | `ghcr.io/rufrufy/balaidiklat:sha-a1b2c3d` | Tag unik per commit untuk rollback |

### Cara Rollback

Jika versi terbaru bermasalah:

1. Cek commit history di GitHub, cari commit stabil terakhir
2. Cat 7-digit SHA (misal: `a1b2c3d`)
3. Update variable di Portainer stack:
  - `APP_IMAGE` = `ghcr.io/rufrufy/balaidiklat:sha-a1b2c3d`
4. Deploy ulang stack
5. Setelah stabil, kembalikan `APP_IMAGE` ke `:latest`

---

## Troubleshooting

### Container tidak start / error 500

```bash
# Cek logs container di Portainer atau via CLI:
docker logs <container_name>

# Atau exec ke container:
docker exec -it <container_name> bash
php artisan migrate:status
php artisan config:clear
``

### Webhook Portainer tidak ter-trigger

1. Cek GitHub Actions log - apakah step "Trigger Portainer webhook" sukses?
2. Jika skipped: variable `PORTAINER_WEBHOOK_URL` belum diset di GitHub
3. Jika error (non-2xx): cek apakah URL webhook benar dan Portainer reachable
4. Test manual:
  ```bash
  curl -X POST "http://your-server:900/api/webhooks/xxx"
  ``

### Database migration gagal

1. Cek logs container untuk error detail
2. Pastikan `DB_HOST`, `DB_PORT`, `DB_PASSWORD` benar
3. Test koneksi dari server:
  ```bash
  docker exec -it <container_name> php artisan db:show
  ```
4. Jika perlu, jalankan manual:
  ```bash
  docker exec -it <container_name> php artisan migrate --force
  ``

### SSL/TLS error di browser

1. Cek Cloudflare SSL mode = Full (Strict)
2. Cek Traefik logs untuk error Let's Encrypt
3. Pastikan port 80 & 443 terbuka di firewall server (untuk ACME challenge)

### Assets (CSS/JS) tidak load

1. Cek `public/build/` ada di dalam container:
  ```bash
  docker exec -it <container_name> ls -la public/build
  ```
2. Jika kosong, rebuild image (mungkin step Vite build gagal di GitHub Actions)

---

## File Konfigurasi Deployment

| File | Fungsi |
|------|--------|
| `Dockerfile` | Multi-stage build: Node (assets) → Composer (PHP deps) → PHP 8.3 Apache |
| `docker-compose.yml` | Portainer stack definition dengan Traefik labels |
| `.github/workflows/docker.yml` | CI/CD: build, push ke GHCR, trigger Portainer webhook |
| `.env.production.example` | Template environment variables untuk Portainer |
| `.dockerignore` | Exclude file yang tidak perlu di Docker image |
| `docker/entrypoint.sh` | Auto migrate, key:generate, storage:link, config cache |
| `docker/apache/000-default.conf` | Apache vhost dengan docroot Laravel |
| `docker/php.ini` | PHP production settings |
| `docker/php/opcache.ini` | OPcache settings untuk performance |

---

## Quick Reference

### Deploy manual (tanpa auto-deploy)

```bash
# Build & push manual
docker build -t ghcr.io/rufrufy/balaidiklat:latest .
docker push ghcr.io/rufrufy/balaidiklat:latest

# Pull & restart di server
docker compose pull
docker compose up -d
``

### Cek status deployment

```bash
# Cek container status
docker ps | grep balaidiklat

# Cek image yang digunakan
docker inspect <container_name> | grep -A5 "Config.Image"

# Cek Traefik routing
docker logs traefik 2>&1 | grep balaidiklat
``

---

## Lisensi

ENOWX-0Y35H-EB5EP-2P0IN-COQPJ
