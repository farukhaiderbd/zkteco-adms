<?php

namespace App\ZKTeco\Http\Middleware;

use App\ZKTeco\Traits\HasLogging;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogZKTecoRequests
{
    use HasLogging;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        // Log the incoming request if API logging is enabled
        if (config('zkteco-biometric.logging.log_api_requests', true) && $this->isLoggingEnabled()) {
            $logData = [
                'method' => $request->method(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'full_url' => $request->fullUrl(),
                'query_params' => $request->query(),
                'content_length' => strlen($request->getContent()),
                'timestamp' => now()->toISOString(),
            ];

            // Add headers if enabled
            if (config('zkteco-biometric.logging.log_request_headers', true)) {
                $logData['headers'] = $this->sanitizeHeaders($request->headers->all());
            }

            $this->logApiRequest($request->getPathInfo(), $logData);
        }

        $response = $next($request);

        // Log the response if response details logging is enabled
        if (config('zkteco-biometric.logging.log_response_details', true) && $this->isLoggingEnabled()) {
            $responseData = [
                'path' => $request->getPathInfo(),
                'status_code' => $response->getStatusCode(),
                'response_length' => strlen($response->getContent()),
            ];

            // Add processing time if enabled
            if (config('zkteco-biometric.logging.log_processing_time', true)) {
                $responseData['processing_time'] = round((microtime(true) - $startTime) * 1000, 2).'ms';
            }

            $this->logDebug('ZKTeco API response', $responseData);
        }

        return $response;
    }

    /**
     * Sanitize headers to remove sensitive information
     */
    private function sanitizeHeaders(array $headers): array
    {
        $sensitiveHeaders = ['authorization', 'cookie', 'x-api-key', 'x-auth-token'];

        foreach ($sensitiveHeaders as $header) {
            if (isset($headers[$header])) {
                $headers[$header] = ['[REDACTED]'];
            }
        }

        return $headers;
    }
}
