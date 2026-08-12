<?php

namespace App\ZKTeco\Traits;

use Illuminate\Support\Facades\Log;

trait HasLogging
{
    /**
     * Log info message if logging is enabled
     */
    protected function logInfo(string $message, array $context = []): void
    {
        if ($this->isLoggingEnabled()) {
            Log::channel(config('zkteco-biometric.logging.channel', 'daily'))
                ->info("[ZKTeco] {$message}", $context);
        }
    }

    /**
     * Log error message if logging is enabled
     */
    protected function logError(string $message, array $context = []): void
    {
        if ($this->isLoggingEnabled()) {
            Log::channel(config('zkteco-biometric.logging.channel', 'daily'))
                ->error("[ZKTeco] {$message}", $context);
        }
    }

    /**
     * Log warning message if logging is enabled
     */
    protected function logWarning(string $message, array $context = []): void
    {
        if ($this->isLoggingEnabled()) {
            Log::channel(config('zkteco-biometric.logging.channel', 'daily'))
                ->warning("[ZKTeco] {$message}", $context);
        }
    }

    /**
     * Log debug message if logging is enabled
     */
    protected function logDebug(string $message, array $context = []): void
    {
        if ($this->isLoggingEnabled() && config('app.debug', false)) {
            Log::channel(config('zkteco-biometric.logging.channel', 'daily'))
                ->debug("[ZKTeco] {$message}", $context);
        }
    }

    /**
     * Log database operations if enabled
     */
    protected function logDatabaseOperation(string $operation, string $model, array $data = []): void
    {
        if ($this->isLoggingEnabled() && config('zkteco-biometric.logging.log_database_operations', false)) {
            $this->logDebug("Database operation: {$operation} on {$model}", $data);
        }
    }

    /**
     * Log API requests if enabled
     */
    protected function logApiRequest(string $endpoint, array $data = []): void
    {
        if ($this->isLoggingEnabled() && config('zkteco-biometric.logging.log_api_requests', true)) {
            $this->logDebug("API request to {$endpoint}", $data);
        }
    }

    /**
     * Determine if logging is enabled based on configuration and debug mode
     */
    protected function isLoggingEnabled(): bool
    {
        $loggingEnabled = config('zkteco-biometric.logging.enabled', true);
        $respectAppDebug = config('zkteco-biometric.logging.respect_app_debug', true);

        if ($respectAppDebug) {
            return $loggingEnabled && config('app.debug', false);
        }

        return $loggingEnabled;
    }
}
