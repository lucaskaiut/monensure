<?php

namespace App\Modules\Assistant\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Cliente desacoplado de provedor, compatível com qualquer endpoint
 * que exponha a interface OpenAI Chat Completions.
 */
final class OpenAiClient
{
    /**
     * @param  array{endpoint: string, api_key: string, model: string, temperature: float, max_tokens: ?int}  $config
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array<string, mixed>>  $tools
     * @param  callable(string): void  $onContent
     * @param  array<string, mixed>  $logContext
     * @return array{content: string, tool_calls: list<array{id: string, name: string, arguments: array<string, mixed>}>, finish_reason: ?string}
     */
    public function streamChat(array $config, array $messages, array $tools, callable $onContent, array $logContext = []): array
    {
        $payload = [
            'model' => $config['model'],
            'messages' => $messages,
            'temperature' => $config['temperature'],
            'stream' => true,
        ];

        if ($config['max_tokens'] !== null) {
            $payload['max_tokens'] = $config['max_tokens'];
        }

        if ($tools !== []) {
            $payload['tools'] = $tools;
        }

        $response = $this->request($config, '/chat/completions', $payload);

        if ($response->failed()) {
            $this->logProviderError($response, $config, $payload, 'stream_chat', $logContext);
            throw new RuntimeException($this->describeError($response));
        }

        $content = '';
        $finishReason = null;
        /** @var array<int, array{id: ?string, name: ?string, arguments: string}> $toolCalls */
        $toolCalls = [];

        $stream = $response->toPsrResponse()->getBody();

        try {
            $buffer = '';

            while (! $stream->eof()) {
                $chunk = $stream->read(8192);

                if ($chunk === false || $chunk === '') {
                    break;
                }

                $buffer .= $chunk;

                while (($newline = strpos($buffer, "\n")) !== false) {
                    $line = rtrim(substr($buffer, 0, $newline), "\r");
                    $buffer = substr($buffer, $newline + 1);

                    if ($line === '' || ! str_starts_with($line, 'data:')) {
                        continue;
                    }

                    $data = trim(substr($line, 5));

                    if ($data === '[DONE]') {
                        break 2;
                    }

                    $decoded = json_decode($data, true);

                    if (! is_array($decoded)) {
                        continue;
                    }

                    $choice = $decoded['choices'][0] ?? null;

                    if (! is_array($choice)) {
                        continue;
                    }

                    $delta = $choice['delta'] ?? [];
                    $finishReason = $choice['finish_reason'] ?? $finishReason;

                    if (isset($delta['content']) && $delta['content'] !== '' && $delta['content'] !== null) {
                        $content .= $delta['content'];
                        $onContent($delta['content']);
                    }

                    foreach ($delta['tool_calls'] ?? [] as $call) {
                        $index = (int) ($call['index'] ?? 0);

                        if (! isset($toolCalls[$index])) {
                            $toolCalls[$index] = ['id' => null, 'name' => null, 'arguments' => ''];
                        }

                        if (isset($call['id'])) {
                            $toolCalls[$index]['id'] = $call['id'];
                        }

                        $function = $call['function'] ?? [];

                        if (isset($function['name'])) {
                            $toolCalls[$index]['name'] = $function['name'];
                        }

                        if (isset($function['arguments'])) {
                            $toolCalls[$index]['arguments'] .= $function['arguments'];
                        }
                    }
                }
            }
        } finally {
            $stream->close();
        }

        $normalized = [];

        foreach ($toolCalls as $call) {
            if ($call['name'] === null) {
                continue;
            }

            $normalized[] = [
                'id' => $call['id'] ?? ('call_'.bin2hex(random_bytes(4))),
                'name' => $call['name'],
                'arguments' => $this->decodeArguments($call['arguments']),
            ];
        }

        return [
            'content' => $content,
            'tool_calls' => $normalized,
            'finish_reason' => $finishReason,
        ];
    }

    /**
     * @param  array{endpoint: string, api_key: string, model: string}  $config
     * @return array{ok: bool, status: string, message: string}
     */
    public function testConnection(array $config): array
    {
        $payload = [
            'model' => $config['model'],
            'messages' => [['role' => 'user', 'content' => 'ping']],
            'max_tokens' => 1,
        ];

        try {
            $response = $this->request($config, '/chat/completions', $payload, withStream: false);
        } catch (Throwable $e) {
            return ['ok' => false, 'status' => 'endpoint', 'message' => $e->getMessage()];
        }

        if ($response->successful()) {
            return ['ok' => true, 'status' => 'valid', 'message' => 'Conexão válida.'];
        }

        $this->logProviderError($response, $config, $payload, 'test_connection');

        $detail = $this->extractErrorDetail($response);

        $message = match ($response->status()) {
            401, 403 => 'Erro de autenticação. Verifique a chave da API.',
            404 => 'Modelo não encontrado no endpoint informado.',
            429 => 'Limite de requisições atingido. Tente novamente em instantes.',
            default => 'Endpoint indisponível ou configurado incorretamente.',
        };

        return [
            'ok' => false,
            'status' => match ($response->status()) {
                401, 403 => 'auth',
                404 => 'model',
                default => 'endpoint',
            },
            'message' => $detail !== null ? $message.' '.$detail : $message,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function request(array $config, string $path, array $payload, bool $withStream = true): Response
    {
        try {
            $request = Http::withToken($config['api_key'])
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => $withStream ? 'text/event-stream' : 'application/json',
                ]);

            $options = ['json' => $payload, 'connect_timeout' => 30];

            if ($withStream) {
                $options['stream'] = true;
                $options['timeout'] = 300;
            } else {
                $options['timeout'] = 60;
            }

            return $request->send('POST', $config['endpoint'].$path, $options);
        } catch (ConnectionException $e) {
            $this->logTransportError($config, $path, $payload, 'connection', $e);

            throw new RuntimeException('Não foi possível conectar ao endpoint de IA configurado. Verifique a URL e a disponibilidade do serviço.');
        } catch (Throwable $e) {
            $this->logTransportError($config, $path, $payload, 'transport', $e);

            throw new RuntimeException('Falha ao comunicar com o provedor de IA: '.$e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeArguments(string $arguments): array
    {
        if (trim($arguments) === '') {
            return [];
        }

        $decoded = json_decode($arguments, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function describeError(Response $response): string
    {
        $status = $response->status();
        $detail = $this->extractErrorDetail($response);

        $base = match ($status) {
            400 => 'Requisição inválida para o provedor de IA.',
            401, 403 => 'Erro de autenticação com o provedor de IA. Verifique a chave da API.',
            404 => 'Modelo não encontrado no endpoint configurado.',
            429 => 'Limite de requisições do provedor atingido. Tente novamente em instantes.',
            default => 'O provedor de IA retornou um erro (HTTP '.$status.').',
        };

        return $detail !== null ? $base.' '.$detail : $base;
    }

    private function extractErrorDetail(Response $response): ?string
    {
        try {
            $data = $response->json();

            if (! is_array($data)) {
                return null;
            }

            $message = data_get($data, 'error.message')
                ?? data_get($data, 'message')
                ?? data_get($data, 'error');

            if (is_string($message) && trim($message) !== '') {
                return $message;
            }
        } catch (Throwable) {
            // corpo não é JSON legível
        }

        return null;
    }

    /**
     * @param  array{endpoint: string, api_key: string, model: string, temperature?: float, max_tokens?: ?int}  $config
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     */
    private function logProviderError(Response $response, array $config, array $payload, string $operation, array $context = []): void
    {
        Log::warning('Assistente de IA: erro do provedor', array_merge($context, [
            'operation' => $operation,
            'http_status' => $response->status(),
            'endpoint' => $this->sanitizeEndpoint($config['endpoint']),
            'model' => $config['model'],
            'error_detail' => $this->extractErrorDetail($response),
            'response_body' => $this->extractResponseBody($response),
            'request' => $this->summarizePayload($payload),
        ]));
    }

    /**
     * @param  array{endpoint: string, api_key: string, model: string}  $config
     * @param  array<string, mixed>  $payload
     */
    private function logTransportError(array $config, string $path, array $payload, string $kind, Throwable $exception): void
    {
        Log::warning('Assistente de IA: falha de comunicação com o provedor', [
            'kind' => $kind,
            'endpoint' => $this->sanitizeEndpoint($config['endpoint'].$path),
            'model' => $config['model'],
            'error' => $exception->getMessage(),
            'exception' => $exception::class,
            'request' => $this->summarizePayload($payload),
        ]);
    }

    private function sanitizeEndpoint(string $endpoint): string
    {
        return rtrim($endpoint, '/');
    }

    private function extractResponseBody(Response $response): ?string
    {
        try {
            $body = $response->body();

            if ($body === '') {
                return null;
            }

            return Str::limit($body, 4000);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function summarizePayload(array $payload): array
    {
        $messages = [];

        foreach ($payload['messages'] ?? [] as $message) {
            if (! is_array($message)) {
                continue;
            }

            $entry = ['role' => $message['role'] ?? 'unknown'];
            $content = $message['content'] ?? null;

            if (is_string($content)) {
                $entry['content_length'] = strlen($content);
                $entry['content_preview'] = Str::limit($content, 120);
            } elseif ($content === null) {
                $entry['content_length'] = 0;
            } else {
                $encoded = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
                $entry['content_length'] = strlen($encoded);
                $entry['content_preview'] = Str::limit($encoded, 120);
            }

            if (isset($message['tool_calls']) && is_array($message['tool_calls'])) {
                $entry['tool_calls'] = array_map(static function (array $call): array {
                    return [
                        'id' => $call['id'] ?? null,
                        'name' => data_get($call, 'function.name'),
                    ];
                }, $message['tool_calls']);
            }

            if (isset($message['tool_call_id'])) {
                $entry['tool_call_id'] = $message['tool_call_id'];
            }

            if (isset($message['name'])) {
                $entry['name'] = $message['name'];
            }

            $messages[] = $entry;
        }

        $tools = $payload['tools'] ?? [];

        return [
            'model' => $payload['model'] ?? null,
            'stream' => $payload['stream'] ?? null,
            'temperature' => $payload['temperature'] ?? null,
            'max_tokens' => $payload['max_tokens'] ?? null,
            'message_count' => count($payload['messages'] ?? []),
            'messages' => $messages,
            'tool_count' => is_array($tools) ? count($tools) : 0,
            'tool_names' => is_array($tools)
                ? array_values(array_filter(array_map(
                    static fn (mixed $tool): ?string => is_array($tool) ? data_get($tool, 'function.name') : null,
                    $tools,
                )))
                : [],
        ];
    }
}
