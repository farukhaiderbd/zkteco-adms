# ZKTeco Live Device Configuration (UFS2252100853)

## Device Information
- **Serial Number:** UFS2252100853
- **Name:** THo Hat
- **Device ID:** 1
- **Current Status:** Offline (last connection: 2026-08-12 21:09:34)
- **Pending Commands:** 2 commands waiting

## Live Server Configuration

**Server URL:** `https://dev.elitemodelsbd.com`

### Device Configuration Settings

Configure your ZKTeco device (UFS2252100853) with these exact settings:

#### Web Interface Access
1. Open device web interface (find device IP from your network)
2. Login with admin credentials
3. Navigate to: **Communication → Server Settings**

#### Server Configuration
```
Server Address: dev.elitemodelsbd.com
Protocol: HTTPS (try HTTP if issues)
Port: 443 (or 80 for HTTP)
Path: /iclock/getrequest
Polling Interval: 30 seconds
Real-time Mode: Enabled
```

### Current Pending Commands

Your device has **2 commands** waiting to execute:

1. **ID: 4 - INFO Command**
   - Type: Device information request
   - Created: 2026-08-13 08:17:15
   - Command format: `C:4:INFO`

2. **ID: 5 - REBOOT Command**
   - Type: Device reboot
   - Created: 2026-08-13 08:19:08
   - Command format: `C:5:REBOOT`

**⚠️ Important:** These commands will execute immediately when device connects!

### Device Connection History

Your device was connecting successfully but stopped:
- Last connection: **2026-08-12 21:09:34** (over 12 hours ago)
- Connection pattern: Device was polling every 15-30 seconds
- Endpoint: `/iclock/cdata` (attendance upload)
- Status: Handshake successful

### Immediate Action Required

**Step 1: Find Your Device**
```bash
# Scan your network for ZKTeco devices
# Common IP ranges to check: 192.168.1.x, 192.168.0.x, 10.0.0.x

# Try accessing common device IPs:
http://192.168.1.201/
http://192.168.1.1/
http://192.168.0.201/
```

**Step 2: Update Device Configuration**
Once you access the device web interface:
1. Update server address from old settings to: `dev.elitemodelsbd.com`
2. Verify path is: `/iclock/getrequest`
3. Set polling interval to 30 seconds
4. Enable real-time mode
5. Save and reboot device

**Step 3: Monitor Connection**
```bash
# Monitor device status
php artisan zkteco:diagnose UFS2252100853

# Watch for device to come online
# Expected: "✅ Device is ONLINE" within 1-2 minutes after reboot
```

### What Will Happen When Device Connects

**Immediate execution sequence:**
1. Device polls: `GET /iclock/getrequest?SN=UFS2252100853`
2. Server responds with pending commands:
   ```
   C:4:INFO
   C:5:REBOOT
   ```
3. Device executes INFO command → sends result back
4. Device executes REBOOT command → device restarts
5. After reboot, device reconnects and continues normal operation

### Expected Timeline

- **0-30 seconds:** Device connects and receives commands
- **30-60 seconds:** INFO command executes
- **60-90 seconds:** REBOOT command executes (device restarts)
- **2-3 minutes:** Device reconnects after reboot
- **Ongoing:** Normal polling and attendance sync

### Troubleshooting

**If device doesn't connect:**
1. Verify device has network connectivity
2. Check device can reach `dev.elitemodelsbd.com`
3. Verify firewall allows outbound HTTPS
4. Try HTTP instead of HTTPS

**If commands don't execute:**
1. Run diagnostic to verify device is online
2. Check device logs for connection errors
3. Verify device serial number matches exactly

**If you don't want device to reboot:**
```bash
# Cancel the reboot command
php artisan tinker --exec '
use \Syofyanzuhad\FilamentZktecoAdms\Models\DeviceCommand;
DeviceCommand::find(5)->delete();
echo "Reboot command cancelled\n";
'
```

### Monitoring Commands

```bash
# Check device status
php artisan zkteco:diagnose UFS2252100853

# View pending commands
php artisan tinker --exec '
use \Syofyanzuhad\FilamentZktecoAdms\Models\Device;
use \Syofyanzuhad\FilamentZktecoAdms\Models\DeviceCommand;
$device = Device::where("serial_number", "UFS2252100853")->first();
echo "Pending commands: " . $device->pendingCommands()->count() . "\n";
$device->pendingCommands->get()->each(function($cmd) {
    echo "ID: {$cmd->id}, Type: {$cmd->command_type}\n";
});
'

# Cancel all pending commands
php artisan tinker --exec '
use \Syofyanzuhad\FilamentZktecoAdms\Models\DeviceCommand;
DeviceCommand::where("status", "pending")->delete();
echo "All pending commands cancelled\n";
'
```

### Server Information

**Production Server:** `https://dev.elitemodelsbd.com`
**Device Serial:** `UFS2252100853`
**Device Name:** THo Hat

**Configuration Summary:**
- Server: `dev.elitemodelsbd.com`
- Protocol: HTTPS
- Port: 443
- Path: `/iclock/getrequest`
- Polling: Every 30 seconds

Once your device connects to this server with these settings, it will immediately execute the pending commands and resume normal attendance syncing!