<?php

namespace Flowtriq\Pterodactyl\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FlowtriqApiClient
{
    private string $baseUrl;
    private string $token;

    public function __construct(string $baseUrl, string $token)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->token = $token;
    }

    /**
     * Test the API connection by fetching workspace info.
     */
    public function testConnection(): array
    {
        return $this->request('GET', '/api/v1/workspace');
    }

    // -- Workspace Management (white-label) -----------------------------------

    public function createWorkspace(string $name, string $email, string $password = ''): array
    {
        $body = [
            'name' => $name,
            'email' => $email,
            'send_welcome_email' => true,
        ];
        if ($password) {
            $body['password'] = $password;
        }

        return $this->request('POST', '/api/v1/workspaces', $body);
    }

    public function getWorkspace(string $uuid): array
    {
        return $this->request('GET', '/api/v1/workspaces/' . urlencode($uuid));
    }

    public function deleteWorkspace(string $uuid): array
    {
        return $this->request('DELETE', '/api/v1/workspaces/' . urlencode($uuid));
    }

    // -- Node Management ------------------------------------------------------

    public function createNode(string $name, string $ip, string $overrideToken = ''): array
    {
        return $this->request('POST', '/api/v1/nodes', [
            'name' => $name,
            'ip' => $ip,
        ], $overrideToken ?: null);
    }

    public function getNode(string $uuid, string $overrideToken = ''): array
    {
        return $this->request('GET', '/api/v1/nodes/' . urlencode($uuid), [], $overrideToken ?: null);
    }

    public function getNodes(string $overrideToken = ''): array
    {
        return $this->request('GET', '/api/v1/nodes', [], $overrideToken ?: null);
    }

    public function updateNode(string $uuid, array $data, string $overrideToken = ''): array
    {
        return $this->request('PATCH', '/api/v1/nodes/' . urlencode($uuid), $data, $overrideToken ?: null);
    }

    public function deleteNode(string $uuid, string $overrideToken = ''): array
    {
        return $this->request('DELETE', '/api/v1/nodes/' . urlencode($uuid), [], $overrideToken ?: null);
    }

    /**
     * Push service port configuration to a Flowtriq node.
     *
     * @param string $nodeUuid  Flowtriq node UUID
     * @param array  $ports     Array of ['protocol' => 'tcp|udp|both', 'port_value' => '25565', 'label' => 'Minecraft']
     * @param string $sensitivity  standard|aggressive|relaxed|custom
     * @param string $responseMode full|pipeline|onnode
     */
    public function updateServicePorts(
        string $nodeUuid,
        array $ports,
        string $sensitivity = 'standard',
        string $responseMode = 'full',
        string $overrideToken = ''
    ): array {
        return $this->updateNode($nodeUuid, [
            'sp_enabled' => true,
            'sp_sensitivity' => $sensitivity,
            'sp_response_mode' => $responseMode,
            'service_ports' => $ports,
        ], $overrideToken);
    }

    // -- Incidents ------------------------------------------------------------

    public function getIncidents(string $nodeUuid = '', int $limit = 25, string $overrideToken = ''): array
    {
        $query = '?limit=' . $limit;
        if ($nodeUuid) {
            $query .= '&node=' . urlencode($nodeUuid);
        }

        return $this->request('GET', '/api/v1/incidents' . $query, [], $overrideToken ?: null);
    }

    // -- HTTP Client ----------------------------------------------------------

    private function request(string $method, string $endpoint, array $body = [], ?string $tokenOverride = null): array
    {
        $url = $this->baseUrl . $endpoint;
        $token = $tokenOverride ?? $this->token;

        try {
            $pending = Http::timeout(30)
                ->connectTimeout(15)
                ->withToken($token)
                ->accept('application/json')
                ->withUserAgent('FlowtriqPterodactyl/1.0');

            $response = match (strtoupper($method)) {
                'GET' => $pending->get($url),
                'POST' => $pending->post($url, $body ?: []),
                'PATCH' => $pending->patch($url, $body ?: []),
                'PUT' => $pending->put($url, $body ?: []),
                'DELETE' => $pending->delete($url),
                default => $pending->get($url),
            };

            $result = $response->json() ?? [];

            if (!is_array($result)) {
                $result = [
                    'ok' => false,
                    'error' => ['code' => 'invalid_response', 'message' => 'Invalid API response (HTTP ' . $response->status() . ')'],
                ];
            }

            $this->log($method, $endpoint, $body, $result, $response->status());

            return $result;
        } catch (\Exception $e) {
            $result = [
                'ok' => false,
                'error' => ['code' => 'connection_error', 'message' => 'Connection failed: ' . $e->getMessage()],
            ];

            $this->log($method, $endpoint, $body, $result, 0);

            return $result;
        }
    }

    private function log(string $method, string $endpoint, array $body, array $result, int $status): void
    {
        $ok = $result['ok'] ?? false;
        $level = $ok ? 'debug' : 'warning';

        Log::channel('single')->$level('[Flowtriq] API ' . $method . ' ' . $endpoint, [
            'status' => $status,
            'ok' => $ok,
            'body_keys' => array_keys($body),
            'error' => $result['error'] ?? null,
        ]);
    }

    /**
     * Extract error message from an API response.
     */
    public static function errorMessage(array $result): string
    {
        if (!empty($result['error']['message'])) {
            return $result['error']['message'];
        }
        if (!empty($result['error']) && is_string($result['error'])) {
            return $result['error'];
        }

        return 'Unknown API error';
    }
}
