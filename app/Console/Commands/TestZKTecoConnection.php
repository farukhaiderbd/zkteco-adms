<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestZKTecoConnection extends Command
{
    protected $signature = 'zkteco:test-connection';
    protected $description = 'Test ZKTeco server connectivity and configuration';

    public function handle(): int
    {
        $this->info('=== ZKTeco Server Connection Test ===');
        $this->newLine();

        // Get server URL
        $serverUrl = config('app.url');
        $this->info("🌐 Server URL: {$serverUrl}");

        // Test endpoints
        $endpoints = [
            '/iclock/getrequest' => 'Device command polling',
            '/iclock/cdata' => 'Attendance data upload',
            '/iclock/devicecmd' => 'Command execution results',
            '/iclock/ping' => 'Device heartbeat',
        ];

        $this->newLine();
        $this->info('🔗 Testing Server Endpoints:');

        foreach ($endpoints as $endpoint => $description) {
            $this->line("   Testing {$endpoint} ({$description})...");

            try {
                $response = Http::get($serverUrl . $endpoint, ['SN' => 'TEST123456']);

                if ($response->successful()) {
                    $this->info("   ✅ {$endpoint} - {$response->status()}");
                    $this->line("      Response: " . substr($response->body(), 0, 50));
                } else {
                    $this->warn("   ⚠️  {$endpoint} - {$response->status()}");
                }
            } catch (\Exception $e) {
                $this->error("   ❌ {$endpoint} - Error: {$e->getMessage()}");
            }

            $this->newLine();
        }

        // Test with actual device serial
        $this->info('🎯 Testing with Your Device:');

        $testSerial = 'TEST123456';
        $this->line("   Device Serial: {$testSerial}");

        try {
            $response = Http::get($serverUrl . '/iclock/getrequest', ['SN' => $testSerial]);

            $this->info("   ✅ Connection successful");
            $this->line("   Response: " . $response->body());
        } catch (\Exception $e) {
            $this->error("   ❌ Connection failed: {$e->getMessage()}");
        }

        $this->newLine();

        // Configuration recommendations
        $this->info('📋 Device Configuration:');
        $this->line('   Server Address: ' . parse_url($serverUrl, PHP_URL_HOST));
        $this->line('   Protocol: ' . (parse_url($serverUrl, PHP_URL_SCHEME) === 'https' ? 'HTTPS' : 'HTTP'));
        $this->line('   Port: ' . (parse_url($serverUrl, PHP_URL_PORT) ?: ((parse_url($serverUrl, PHP_URL_SCHEME) === 'https') ? 443 : 80)));
        $this->line('   Path: /iclock/getrequest');
        $this->line('   Method: GET');
        $this->line('   Polling Interval: 30 seconds');

        $this->newLine();

        // Network test
        $this->info('🌐 Network Accessibility:');

        $ip = gethostbyname(parse_url($serverUrl, PHP_URL_HOST));
        if ($ip !== parse_url($serverUrl, PHP_URL_HOST)) {
            $this->info("   ✅ DNS Resolution: {$serverUrl} → {$ip}");
        } else {
            $this->warn("   ⚠️  DNS Resolution failed for {$serverUrl}");
            $this->line("      Try using IP address directly");
        }

        $this->newLine();

        // Next steps
        $this->info('🚀 Next Steps:');
        $this->line('   1. Configure device with above settings');
        $this->line('   2. Test connection from device web interface');
        $this->line('   3. Reboot device to apply settings');
        $this->line('   4. Monitor with: php artisan zkteco:diagnose TEST123456');
        $this->line('   5. Check admin panel: /admin');

        $this->newLine();

        // Quick test command
        $this->info('🧪 Quick Test:');
        $this->line('   Run this command to simulate device connection:');
        $this->line('   curl "' . $serverUrl . '/iclock/getrequest?SN=TEST123456"');

        return self::SUCCESS;
    }
}