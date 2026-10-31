<?php

namespace Tests\Feature\Auth;

use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class McpOAuthTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private const ENDPOINT = '/api/mcp/financial';

    private const REDIRECT_URI = 'https://claude.ai/api/mcp/auth_callback';

    protected function setUp(): void
    {
        parent::setUp();

        if (! file_exists(storage_path('oauth-private.key'))) {
            $this->artisan('passport:keys')->assertSuccessful();
        }
    }

    public function test_unauthenticated_mcp_request_points_to_resource_metadata(): void
    {
        $response = $this->postJson(self::ENDPOINT, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']);

        $response->assertUnauthorized();
        $this->assertStringContainsString(
            'resource_metadata="'.url('/.well-known/oauth-protected-resource/api/mcp/financial').'"',
            (string) $response->headers->get('WWW-Authenticate'),
        );
    }

    public function test_discovery_metadata_is_published(): void
    {
        $this->getJson('/.well-known/oauth-protected-resource/api/mcp/financial')
            ->assertOk()
            ->assertJsonPath('resource', url('/api/mcp/financial'))
            ->assertJsonPath('authorization_servers.0', url('/'));

        $this->getJson('/.well-known/oauth-authorization-server')
            ->assertOk()
            ->assertJsonPath('authorization_endpoint', url('/oauth/authorize'))
            ->assertJsonPath('token_endpoint', url('/oauth/token'))
            ->assertJsonPath('registration_endpoint', url('/oauth/register'));
    }

    public function test_registration_rejects_unknown_redirect_domains(): void
    {
        $this->postJson('/oauth/register', [
            'client_name' => 'Evil',
            'redirect_uris' => ['https://evil.example.com/callback'],
        ])->assertStatus(400)->assertJsonPath('error', 'invalid_redirect_uri');

        $this->postJson('/oauth/register', [
            'client_name' => 'Claude Code',
            'redirect_uris' => ['http://localhost:54321/callback'],
        ])->assertCreated();
    }

    public function test_authorize_redirects_guests_to_login(): void
    {
        $clientId = $this->registerClient();

        $this->get('/oauth/authorize?'.http_build_query($this->authorizeParams($clientId, 'x')))
            ->assertRedirect('/login');

        $this->get('/login')->assertOk()->assertSee('Entrar');
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        [, $child] = $this->createOperationalChild();
        $this->createAdmin($child, ['email' => 'admin@example.com', 'password' => 'secret123']);

        $this->post('/login', ['email' => 'admin@example.com', 'password' => 'errada'])
            ->assertSessionHasErrors('email');

        $this->assertGuest('web');
    }

    public function test_full_oauth_flow_grants_mcp_access_with_user_acl(): void
    {
        [, $child] = $this->createOperationalChild();
        $this->createAdmin($child, ['email' => 'admin@example.com', 'password' => 'secret123']);

        $token = $this->obtainAccessToken('admin@example.com', 'secret123');

        $names = $this->toolNames($this->rpc('tools/list', $token));
        $this->assertCount(27, $names);

        $this->rpc('tools/call', $token, ['name' => 'list_payables', 'arguments' => (object) []])
            ->assertOk()
            ->assertJsonMissingPath('error');
    }

    public function test_oauth_token_respects_member_permissions(): void
    {
        [, $child] = $this->createOperationalChild();
        $this->createMember($child, ['email' => 'member@example.com', 'password' => 'secret123']);

        $token = $this->obtainAccessToken('member@example.com', 'secret123');

        $this->assertSame([], $this->toolNames($this->rpc('tools/list', $token)));
    }

    public function test_denying_consent_does_not_issue_code(): void
    {
        [, $child] = $this->createOperationalChild();
        $this->createAdmin($child, ['email' => 'admin@example.com', 'password' => 'secret123']);
        $clientId = $this->registerClient();

        $this->post('/login', ['email' => 'admin@example.com', 'password' => 'secret123']);
        $consent = $this->get('/oauth/authorize?'.http_build_query($this->authorizeParams($clientId, Str::random(64))));
        $consent->assertOk()->assertSee('Autorizar acesso');

        $response = $this->delete('/oauth/authorize', [
            'state' => 'state-123',
            'client_id' => $clientId,
            'auth_token' => $consent->viewData('authToken'),
        ]);

        $this->assertStringContainsString('error=access_denied', (string) $response->headers->get('Location'));
    }

    private function obtainAccessToken(string $email, string $password): string
    {
        $clientId = $this->registerClient();
        $verifier = Str::random(64);

        $this->post('/login', ['email' => $email, 'password' => $password])->assertRedirect();
        $this->assertAuthenticated('web');

        $consent = $this->get('/oauth/authorize?'.http_build_query($this->authorizeParams($clientId, $verifier)));
        $consent->assertOk()->assertSee('Autorizar acesso');

        $approve = $this->post('/oauth/authorize', [
            'state' => 'state-123',
            'client_id' => $clientId,
            'auth_token' => $consent->viewData('authToken'),
        ]);

        $location = (string) $approve->headers->get('Location');
        $this->assertStringStartsWith(self::REDIRECT_URI, $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('state-123', $query['state'] ?? null);

        $tokenResponse = $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'redirect_uri' => self::REDIRECT_URI,
            'code_verifier' => $verifier,
            'code' => $query['code'],
        ])->assertOk();

        $this->assertNotEmpty($tokenResponse->json('refresh_token'));

        // Requisições seguintes simulam o conector: sem sessão, só Bearer.
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        return (string) $tokenResponse->json('access_token');
    }

    private function registerClient(): string
    {
        return (string) $this->postJson('/oauth/register', [
            'client_name' => 'Claude',
            'redirect_uris' => [self::REDIRECT_URI],
        ])->assertCreated()->json('client_id');
    }

    /**
     * @return array<string, string>
     */
    private function authorizeParams(string $clientId, string $verifier): array
    {
        return [
            'client_id' => $clientId,
            'redirect_uri' => self::REDIRECT_URI,
            'response_type' => 'code',
            'scope' => 'mcp:use',
            'state' => 'state-123',
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function rpc(string $method, string $token, array $params = []): TestResponse
    {
        $this->forgetTenantContext();

        return $this->postJson(self::ENDPOINT, [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $method,
            'params' => (object) $params,
        ], ['Authorization' => "Bearer {$token}"]);
    }

    /**
     * @return list<string>
     */
    private function toolNames(TestResponse $response): array
    {
        $response->assertOk();

        return array_column($response->json('result.tools') ?? [], 'name');
    }
}
