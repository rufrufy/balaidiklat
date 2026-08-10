<?php

namespace App\Console\Commands;

use App\Services\ERetribusiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

#[\Illuminate\Console\Attributes\Signature('bapenda:test {kodebayar?}')]
#[\Illuminate\Console\Attributes\Description('Test Bapenda QRIS API connectivity and response')]
class TestBapendaConnection extends Command
{
    public function handle(): int
    {
        $kodebayar = $this->argument('kodebayar') ?? '741331038569026';

        $this->info('=== Bapenda QRIS Connection Test ===');
        $this->info('Time: '.now()->toDateTimeString());
        $this->info('PHP Version: '.PHP_VERSION);
        $this->info('cURL: '.(extension_loaded('curl') ? 'Yes' : 'No'));
        $this->info('OpenSSL: '.(extension_loaded('openssl') ? 'Yes' : 'No'));
        $this->newLine();

        $baseUrl = (string) config('services.bapenda.qris_base_url');
        $this->info("QRIS Base URL: {$baseUrl}");

        $this->info('Testing network connectivity...');
        try {
            $ping = Http::timeout(10)->get($baseUrl);
            $this->info('Network: OK (HTTP '.$ping->status().')');
        } catch (\Throwable $e) {
            $this->error('Network: FAILED - '.$e->getMessage());
            $this->error('Server TIDAK BISA akses Bapenda. Cek firewall/outbound.');
            return 1;
        }
        $this->newLine();

        $user = (string) config('services.bapenda.qris_user');
        $pass = (string) config('services.bapenda.qris_pass');
        $this->info('QRIS User: '.($user ? 'SET' : 'NOT SET'));
        $this->info('QRIS Pass: '.($pass ? 'SET' : 'NOT SET'));
        $this->newLine();

        $fullKodebayar = '73'.$kodebayar;
        $this->info("Testing getQrisLink: {$fullKodebayar}");

        try {
            $service = app(ERetribusiService::class);
            $result = $service->getQrisLink($kodebayar);

            $this->newLine();
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            if ($result['success']) {
                $this->newLine();
                $this->info('SUCCESS - Link: '.$result['link_qris']);
            } else {
                $this->newLine();
                $this->error('FAILED - '.$result['message']);
            }
        } catch (\Throwable $e) {
            $this->error('Exception: '.$e->getMessage());
            return 1;
        }

        return 0;
    }
}
