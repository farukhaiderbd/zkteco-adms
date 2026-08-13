# ZKTeco Device Configuration Guide

## Server Connection Details

**Your Server:** `https://lara-bio.test`

**Device Serial:** `TEST123456`

**Device IP:** `192.168.1.201`

## ZKTeco Device Setup Instructions

### Method 1: Web Interface Configuration (Recommended)

#### Step 1: Access Device Web Interface
1. Open browser and navigate to: `http://192.168.1.201/`
2. Login with default credentials:
   - Username: `admin`
   - Password: (usually empty or `123456`)

#### Step 2: Configure Network Settings
Navigate to: **Options → Communication → Network**

**Basic Network Configuration:**
- IP Address: `192.168.1.201`
- Subnet Mask: `255.255.255.0`
- Gateway: Your router IP (e.g., `192.168.1.1`)
- DNS Server: `8.8.8.8` or your local DNS

#### Step 3: Configure Server Communication
Navigate to: **Options → Communication → Server Settings**

**Server Configuration:**
```
Server Address: lara-bio.test
Server Port: 443
Protocol: HTTPS
Path: /iclock/getrequest
Method: GET
Polling Interval: 30 seconds
Connection Timeout: 10 seconds
```

**Alternative (if HTTPS doesn't work):**
```
Server Address: lara-bio.test
Server Port: 80
Protocol: HTTP
Path: /iclock/getrequest
Method: GET
Polling Interval: 30 seconds
```

#### Step 4: Configure Data Transmission
Navigate to: **Options → Communication → Data Transmission**

**Transmission Settings:**
```
Real-time Mode: Enabled
Transaction Interval: 1 second
Max Records per Transmission: 50
Auto-send on Boot: Enabled
```

#### Step 5: Test Connection
1. Navigate to: **Maintenance → Test Network**
2. Click "Test Connection" to server
3. Check for successful connection message

#### Step 6: Save and Reboot
1. Click **Save** or **Apply** settings
2. Navigate to: **Maintenance → Reboot Device**
3. Confirm reboot to apply new settings

---

### Method 2: ZKSoftware Biometric Management Software

#### Step 1: Install ZKSoftware
Download and install ZKSoftware Biometric Management from ZKTeco website.

#### Step 2: Add Device
1. Open ZKSoftware application
2. Click **Add Device**
3. Enter device IP: `192.168.1.201`
4. Port: `4370` (default ZKTeco port)
5. Password: (if configured)

#### Step 3: Configure Communication
1. Right-click device → **Communication Settings**
2. Set server URL to: `https://lara-bio.test`
3. Enable **Real-time Attendance**
4. Set **Upload Interval** to 30 seconds

#### Step 4: Apply Settings
1. Click **Apply** to save settings
2. Click **Sync Now** to test connection

---

### Method 3: Command Line Configuration (Advanced)

#### Using Telnet/SSH
```bash
# Connect to device
telnet 192.168.1.201

# Configure network settings
~SET NETWORK~IP=192.168.1.201
~SET NETWORK~MASK=255.255.255.0
~SET NETWORK~GATEWAY=192.168.1.1

# Configure server
~SET SERVER~ADDRESS=lara-bio.test
~SET SERVER~PORT=443
~SET SERVER~PROTOCOL=HTTPS
~SET SERVER~PATH=/iclock/getrequest

# Save and reboot
~REBOOT
```

---

## Verification Steps

### 1. Check Server Connectivity
After configuration, verify your device can reach the server:

```bash
# From your computer, test server accessibility
curl -I https://lara-bio.test/iclock/getrequest?SN=TEST123456

# Should return: HTTP/1.1 200 OK
```

### 2. Run Device Diagnostic
```bash
php artisan zkteco:diagnose TEST123456
```

**Expected Output:**
```
✅ Device is ONLINE (last activity < 10 minutes ago)
📋 Pending Commands: 0 (or commands will execute)
✅ Device would receive: OK (no new commands)
```

### 3. Monitor Command Execution
1. Create a test command in Filament admin panel
2. Watch device logs: **Admin Panel → ZKTeco → Device Logs**
3. Command should execute within 30-60 seconds

---

## Common Issues & Solutions

### Issue 1: "Connection Refused"
**Solution:**
- Check device can reach internet: Ping `8.8.8.8` from device
- Verify firewall allows outgoing HTTPS (port 443)
- Try HTTP instead of HTTPS: `http://lara-bio.test`

### Issue 2: "Certificate Error"
**Solution:**
- Use HTTP instead of HTTPS: `http://lara-bio.test`
- Or add SSL certificate to device (advanced)
- Set device to ignore certificate errors (if available)

### Issue 3: Device Not Polling
**Solution:**
- Check polling interval is set (30 seconds recommended)
- Verify path is correct: `/iclock/getrequest`
- Enable "Real-time Mode" in device settings
- Reboot device after configuration

### Issue 4: Commands Still Pending
**Solution:**
1. Run diagnostic: `php artisan zkteco:diagnose TEST123456`
2. Check device is marked as "online" in diagnostic
3. Verify server URL is accessible from device network
4. Check device logs for connection errors

### Issue 5: Wrong Device Serial
**Solution:**
- Verify device serial number in: **Device Info → Serial Number**
- Update command with correct serial if needed
- Serial numbers are case-sensitive (use uppercase)

---

## Advanced Configuration

### Multiple Devices
For multiple devices, configure each device individually:

```bash
# Check all devices
php artisan tinker --exec 'use \Syofyanzuhad\FilamentZktecoAdms\Models\Device; Device::all()->each(function($d) { echo "Serial: {$d->serial_number}, IP: {$d->ip_address}\n"; });'

# Diagnose specific device
php artisan zkteco:diagnose DEVICE_SERIAL_HERE
```

### Firewall Configuration
Ensure your server allows connections from device IPs:

```bash
# On your server, allow device IP
sudo ufw allow from 192.168.1.201 to any port 443
sudo ufw allow from 192.168.1.201 to any port 80
```

### DNS Configuration
If using domain name, ensure DNS resolves:

```bash
# Test DNS resolution
nslookup lara-bio.test
ping lara-bio.test

# If DNS doesn't work, use direct IP address
# Get server IP:
php artisan tinker --exec 'echo gethostbyname("lara-bio.test");'
```

---

## Testing Without Physical Device

### Manual Connection Test
```bash
# Simulate device connection
curl "https://lara-bio.test/iclock/getrequest?SN=TEST123456"

# Should return: OK (or commands if pending)
```

### Simulate Command Execution
```bash
# 1. Create test command
php artisan tinker --exec '
use App\Services\ZKTecoCommandService;
$s = app(ZKTecoCommandService::class);
$cmd = $s->createUserCommand("TEST123456", ["pin" => "999", "name" => "Test"]);
echo "Command ID: " . $cmd->id . "\n";
'

# 2. Simulate device polling (should return command)
curl "https://lara-bio.test/iclock/getrequest?SN=TEST123456"

# 3. Simulate command acknowledgment
curl "https://lara-bio.test/iclock/devicecmd?SN=TEST123456&ID=X&Return=0"

# 4. Check status
php artisan tinker --exec '
use \Syofyanzuhad\FilamentZktecoAdms\Models\DeviceCommand;
$c = DeviceCommand::latest()->first();
echo "Status: " . $c->status . "\n";
'
```

---

## Quick Configuration Checklist

- [ ] Device IP configured: `192.168.1.201`
- [ ] Network gateway set correctly
- [ ] Server address: `lara-bio.test`
- [ ] Protocol: HTTPS (or HTTP if issues)
- [ ] Port: 443 (or 80 for HTTP)
- [ ] Path: `/iclock/getrequest`
- [ ] Polling interval: 30 seconds
- [ ] Real-time mode: Enabled
- [ ] Firewall allows outbound HTTPS
- [ ] Device can reach internet
- [ ] Settings saved and device rebooted
- [ ] Test connection successful
- [ ] Device shows "online" in diagnostic

---

## Post-Configuration Monitoring

### Real-Time Monitoring
```bash
# Watch device status
watch -n 5 'php artisan zkteco:diagnose TEST123456'

# Monitor command execution
php artisan tinker --exec '
use \Syofyanzuhad\FilamentZktecoAdms\Models\DeviceCommand;
DeviceCommand::where("status", "pending")->count();
'
```

### Filament Admin Monitoring
1. Navigate to: `/admin`
2. Check **ZKTeco → Devices** - should show device as online
3. Check **ZKTeco → Device Commands** - monitor command execution
4. Check **ZKTeco → Device Logs** - view communication history

### Automated Testing
```bash
# Create test command and monitor execution
php artisan tinker --exec '
use App\Services\ZKTecoCommandService;
$s = app(ZKTecoCommandService::class);
$cmd = $s->rebootDevice("TEST123456");
echo "Test command created: " . $cmd->id . "\n";
echo "Monitor with: php artisan zkteco:diagnose TEST123456\n";
'
```

---

## Troubleshooting Commands

```bash
# Check device status
php artisan zkteco:diagnose TEST123456

# View recent device logs
php artisan tinker --exec '
use \DB::Table;
DB::table("zkteco_device_logs")
  ->where("device_serial", "TEST123456")
  ->orderBy("created_at", "desc")
  ->take(10)
  ->get();
'

# Test server endpoint
curl -v https://lara-bio.test/iclock/getrequest?SN=TEST123456

# Check pending commands
php artisan tinker --exec '
use \Syofyanzuhad\FilamentZktecoAdms\Models\DeviceCommand;
DeviceCommand::where("status", "pending")->get();
'

# View device configuration
php artisan tinker --exec '
use \Syofyanzuhad\FilamentZktecoAdms\Models\Device;
$d = Device::where("serial_number", "TEST123456")->first();
print_r($d->toArray());
'
```

---

## Support Resources

- **Admin Panel:** https://lara-bio.test/admin
- **Device Status:** `php artisan zkteco:diagnose TEST123456`
- **Device Web Interface:** http://192.168.1.201/
- **Documentation:** `/docs/ZKTECO_INTEGRATION.md`

**Expected Timeline After Configuration:**
- **Immediate:** Device connects and starts polling
- **Within 1 minute:** Pending commands execute
- **Ongoing:** Real-time attendance data syncs automatically

Once configured, your device will connect to the server every 30 seconds, execute pending commands, and sync attendance data automatically!