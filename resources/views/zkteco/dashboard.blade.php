<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZKTeco Biometric Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-8">ZKTeco Biometric Device Management</h1>

        <!-- Flash Messages -->
        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                {{ session('error') }}
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Device Management Section -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Device Management</h2>

                <form action="{{ route('zkteco.store.device') }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Device Name</label>
                        <input type="text" name="device_name" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2"
                            placeholder="Main Entrance">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Serial Number</label>
                        <input type="text" name="serial_number" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2"
                            placeholder="BJHQ203160001">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Device IP</label>
                        <input type="text" name="device_ip" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2"
                            placeholder="192.168.1.100">
                    </div>
                    <button type="submit"
                        class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700">
                        Add Device
                    </button>
                </form>

                <!-- Devices List -->
                <div class="mt-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Registered Devices</h3>
                    @if($devices->count() > 0)
                        <div class="space-y-2">
                            @foreach($devices as $device)
                                <div class="border rounded-lg p-4">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <p class="font-medium">{{ $device->device_name }}</p>
                                            <p class="text-sm text-gray-600">{{ $device->device_ip }}</p>
                                            <p class="text-sm text-gray-600">SN: {{ $device->serial_number }}</p>
                                        </div>
                                        <div class="text-right">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                {{ $device->isOnline() ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                {{ $device->isOnline() ? 'Online' : 'Offline' }}
                                            </span>
                                            <form action="{{ route('zkteco.test.connection') }}" method="POST" class="mt-2">
                                                @csrf
                                                <input type="hidden" name="device_id" value="{{ $device->id }}">
                                                <button type="submit" class="text-sm text-blue-600 hover:text-blue-800">
                                                    Test Connection
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-gray-500">No devices registered yet.</p>
                    @endif
                </div>
            </div>

            <!-- Employee Management Section -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Employee Management</h2>

                <form action="{{ route('zkteco.store.employee') }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Employee ID (for Device)</label>
                        <input type="text" name="biometric_employee_id" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2"
                            placeholder="001">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Card Number (Optional)</label>
                        <input type="text" name="card_number"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2"
                            placeholder="1234567890">
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" name="has_fingerprint" id="has_fingerprint" value="1"
                            class="rounded border-gray-300">
                        <label for="has_fingerprint" class="ml-2 block text-sm text-gray-900">
                            Has Fingerprint
                        </label>
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" name="has_photo" id="has_photo" value="1"
                            class="rounded border-gray-300">
                        <label for="has_photo" class="ml-2 block text-sm text-gray-900">
                            Has Photo
                        </label>
                    </div>
                    <button type="submit"
                        class="w-full bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700">
                        Add Employee
                    </button>
                </form>

                <!-- Employees List -->
                <div class="mt-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Registered Employees</h3>
                    @if($employees->count() > 0)
                        <div class="space-y-2 max-h-64 overflow-y-auto">
                            @foreach($employees as $employee)
                                <div class="border rounded-lg p-3">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <p class="font-medium">Employee ID: {{ $employee->biometric_employee_id }}</p>
                                            @if($employee->user)
                                                <p class="text-sm text-gray-600">User: {{ $employee->user->name }}</p>
                                            @endif
                                            @if($employee->card_number)
                                                <p class="text-sm text-gray-600">Card: {{ $employee->card_number }}</p>
                                            @endif
                                            @if($employee->has_fingerprint)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                                    FP
                                                </span>
                                            @endif
                                        </div>
                                        <div>
                                            @if($employee->has_fingerprint)
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    Fingerprint
                                                </span>
                                            @endif
                                            @if($employee->has_photo)
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                                    Photo
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-gray-500">No employees registered yet.</p>
                    @endif
                </div>
            </div>

            <!-- User Creation Command Section -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Create User on Device</h2>

                @if($devices->count() > 0 && $employees->count() > 0)
                    <form action="{{ route('zkteco.command.user') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Select Device</label>
                            <select name="device_serial_number" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2">
                                @foreach($devices as $device)
                                    <option value="{{ $device->serial_number }}">
                                        {{ $device->device_name }} ({{ $device->device_ip }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Select Employee</label>
                            <select name="biometric_employee_id" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2">
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->id }}">
                                        Employee {{ $employee->biometric_employee_id }}
                                        @if($employee->user) - {{ $employee->user->name }} @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit"
                            class="w-full bg-purple-600 text-white py-2 px-4 rounded-md hover:bg-purple-700">
                            Send Create User Command
                        </button>
                    </form>
                @else
                    <p class="text-gray-500">Please add devices and employees first.</p>
                @endif

                <!-- Pending Commands -->
                @if($pendingCommands->count() > 0)
                    <div class="mt-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-3">Pending Commands</h3>
                        <div class="space-y-2">
                            @foreach($pendingCommands as $command)
                                <div class="border border-yellow-200 bg-yellow-50 rounded-lg p-3">
                                    <p class="text-sm font-medium">{{ $command->command_type }}</p>
                                    <p class="text-xs text-gray-600">{{ $command->command_id }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <!-- Recent Attendance Section -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Recent Attendance Records</h2>

                @if($recentAttendances->count() > 0)
                    <div class="space-y-2 max-h-96 overflow-y-auto">
                        @foreach($recentAttendances as $attendance)
                            <div class="border rounded-lg p-3">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <p class="font-medium">Employee ID: {{ $attendance->employee_id }}</p>
                                        <p class="text-sm text-gray-600">Device: {{ $attendance->device_name }}</p>
                                        @if($attendance->device)
                                            <p class="text-sm text-gray-600">SN: {{ $attendance->device->serial_number }}</p>
                                        @endif
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-medium">{{ $attendance->timestamp->format('Y-m-d H:i:s') }}</p>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            {{ $attendance->status1 == 0 ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' }}">
                                            {{ $attendance->status1 == 0 ? 'Clock In' : 'Clock Out' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-gray-500">No attendance records yet.</p>
                @endif
            </div>
        </div>

        <!-- API Endpoints Information -->
        <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
            <h2 class="text-xl font-semibold text-blue-900 mb-4">Device Configuration Endpoints</h2>
            <p class="text-blue-800 mb-4">Configure your ZKTeco device to point to these endpoints:</p>
            <div class="space-y-2 text-sm">
                <div class="flex items-center">
                    <span class="font-medium w-40">Handshake:</span>
                    <code class="bg-white px-2 py-1 rounded">GET {{ url('iclock/cdata') }}?SN=YOUR_SERIAL</code>
                </div>
                <div class="flex items-center">
                    <span class="font-medium w-40">Attendance:</span>
                    <code class="bg-white px-2 py-1 rounded">POST {{ url('iclock/cdata') }}</code>
                </div>
                <div class="flex items-center">
                    <span class="font-medium w-40">Commands:</span>
                    <code class="bg-white px-2 py-1 rounded">GET {{ url('iclock/getrequest') }}?SN=YOUR_SERIAL</code>
                </div>
                <div class="flex items-center">
                    <span class="font-medium w-40">Results:</span>
                    <code class="bg-white px-2 py-1 rounded">POST {{ url('iclock/devicecmd') }}</code>
                </div>
            </div>
        </div>
    </div>
</body>
</html>