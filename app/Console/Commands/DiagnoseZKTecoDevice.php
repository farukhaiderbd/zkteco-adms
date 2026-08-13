<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\HttpFoundation\ParameterBag;

#[Signature('zkteco:diagnose {serialNumber}')]
#[Description('Diagnose ZKTeco device connectivity and command status')]
class DiagnoseZKTecoDevice extends Command
{
    protected $signature = 'zkteco:diagnose {serialNumber}';
    protected $description = 'Diagnose ZKTeco device connectivity and command status';

    public function handle(): int
    {
        $serialNumber = $this->argument('serialNumber');

        $this->info("=== ZKTeco Device Diagnostic ===");
        $this->info("Device Serial: {$serialNumber}");
        $this->newLine();

        // Check device in package table
        $zktecoDevice = \Syofyanzuhad\FilamentZktecoAdms\Models\Device::where('serial_number', $serialNumber)->first();

        if (! $zktecoDevice) {
            $this->error("❌ Device NOT found in zkteco_devices table");
            $this->warn("→ Device needs to be registered first");
            return self::FAILURE;
        }

        $this->info("✅ Device found in zkteco_devices table");
        $this->line("   ID: {$zktecoDevice->id}");
        $this->line("   Name: {$zktecoDevice->name}");
        $this->line("   IP: {$zktecoDevice->ip_address}");
        $this->line("   Status: {$zktecoDevice->status}");
        $this->line("   Last Activity: {$zktecoDevice->last_activity_at}");
        $this->newLine();

        // Check if device is online
        $isOnline = $zktecoDevice->isOnline();
        if ($isOnline) {
            $this->info("✅ Device is ONLINE (last activity < 10 minutes ago)");
        } else {
            $this->warn("⚠️  Device is OFFLINE (last activity > 10 minutes ago)");
        }
        $this->newLine();

        // Check pending commands
        $pendingCommands = $zktecoDevice->pendingCommands()->count();
        $this->line("📋 Pending Commands: {$pendingCommands}");

        if ($pendingCommands > 0) {
            $this->table(
                ['ID', 'Type', 'Content', 'Created'],
                collect($zktecoDevice->pendingCommands()
                    ->latest()
                    ->take(5)
                    ->get())
                    ->map(fn ($cmd) => [
                        $cmd->id,
                        $cmd->command_type,
                        substr($cmd->command_content, 0, 50) . '...',
                        $cmd->created_at->format('Y-m-d H:i:s')
                    ])
                    ->toArray()
            );
        }
        $this->newLine();

        // Simulate device polling
        $this->info("🔄 Simulating device polling for commands...");

        try {
            $request = \Illuminate\Http\Request::create('/iclock/getrequest?SN=' . $serialNumber, 'GET');

            $controller = app(\Syofyanzuhad\FilamentZktecoAdms\Http\Controllers\GetRequestController::class);
            $response = $controller($request);

            $responseContent = $response->getContent();

            if ($responseContent === 'OK') {
                $this->info("✅ Device would receive: OK (no commands)");
            } else {
                $this->info("✅ Device would receive commands:");
                $this->line("   " . str_replace("\n", "\n   ", $responseContent));
            }
        } catch (\Exception $e) {
            $this->error("❌ Error simulating polling: " . $e->getMessage());
            return self::FAILURE;
        }
        $this->newLine();

        // Check recent logs from custom table
        $this->info("📡 Recent device communication:");
        $recentLogs = \DB::table('zkteco_device_logs')
            ->where('device_serial', $serialNumber)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        if ($recentLogs->isEmpty()) {
            $this->warn("⚠️  No communication logs found");
            $this->line("   → Device may not be connecting to the server");
        } else {
            foreach ($recentLogs as $log) {
                $this->line("   {$log->created_at} - {$log->endpoint} ({$log->log_type})");
            }
        }
        $this->newLine();

        // Connection test URL
        $url = url('/iclock/getrequest?SN=' . $serialNumber);
        $this->info("🌐 Device should connect to:");
        $this->line("   {$url}");
        $this->newLine();

        // Troubleshooting tips
        $this->info("🔧 Troubleshooting:");
        if (! $isOnline && $pendingCommands > 0) {
            $this->warn("   → Commands are pending but device is offline");
            $this->line("      1. Check device is powered on");
            $this->line("      2. Check device network connection");
            $this->line("      3. Verify device URL configuration");
            $this->line("      4. Test device connectivity to server");
        } elseif ($isOnline && $pendingCommands > 0) {
            $this->warn("   → Device is online but commands are still pending");
            $this->line("      1. Device may not be polling frequently");
            $this->line("      2. Check device polling interval settings");
            $this->line("      3. Verify device can reach the server URL");
            $this->line("      4. Check firewall/network restrictions");
        } else {
            $this->line("      → Everything looks good!");
        }

        return self::SUCCESS;
    }
}