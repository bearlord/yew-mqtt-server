<?php

namespace App\Modules\Mqtt\Infrastructure;

use App\Modules\Mqtt\Services\MqttUserService;
use Yew\Mqtt\Hex\ReasonCode;

/**
 * SASL SCRAM-SHA-256 mechanism (RFC 5802 / RFC 7677), two client rounds.
 *
 *   step 0 (client-first  "n=,r=")    -> server-first  "r=,s=,i="
 *   step 1 (client-final "c=,r=,p=")  -> server-final "v="
 *
 * Verification follows RFC 5802: the server never sees the password. It proves
 * the client knows the password by checking ClientProof against the stored
 * StoredKey, then proves itself to the client via ServerSignature (derived
 * from ServerKey). Credentials come from MqttUserService::getScramCredential.
 */
final class SaslScramSha256Mechanism implements SaslMechanismInterface
{
    private const GS2_HEADER = 'n,,'; // no channel binding

    /** @var array<int, array{
     *     step: int,
     *     client_first_bare: string,
     *     combined_nonce: string,
     *     salt: string,
     *     iterations: int,
     *     stored_key: string,
     *     server_key: string,
     *     username: string}>
     */
    private array $state = [];

    public function __construct(
        private readonly MqttUserService $userService,
    )
    {
    }

    /**
     * Start the SCRAM exchange. Client-first has not arrived yet, so the server
     * sends nothing and waits for the first AUTH packet.
     *
     * @param int $fd Client connection fd (used as the per-connection state key).
     */
    public function begin(int $fd): SaslResult
    {
        return new SaslResult(ReasonCode::CONTINUE_AUTHENTICATION, '', false, null);
    }

    /**
     * Advance the SCRAM exchange by one client round.
     *
     * Routes to client-first handling (first AUTH packet) or client-final
     * handling (second AUTH packet) based on whether per-connection state exists.
     *
     * @param int    $fd       Client connection fd (state key).
     * @param string $authData Raw SASL data from the AUTH packet.
     * @param string $clientId Client identifier (currently unused; reserved for logging).
     */
    public function step(int $fd, string $authData, string $clientId): SaslResult
    {
        if (!isset($this->state[$fd])) {
            return $this->handleClientFirst($fd, $authData);
        }

        return $this->handleClientFinal($fd, $authData);
    }

    /**
     * Parse client-first, look up the SCRAM credential, and build server-first.
     *
     * @param int    $fd       Client connection fd (state key).
     * @param string $authData Raw SASL data from the AUTH packet (gs2-header + client-first-bare).
     */
    private function handleClientFirst(int $fd, string $authData): SaslResult
    {
        // client-first = gs2-header + client-first-bare; bare starts after "n,,"
        if (!str_starts_with($authData, self::GS2_HEADER)) {
            return new SaslResult(ReasonCode::BAD_AUTHENTICATION_METHOD, '', true, null);
        }

        $clientFirstBare = substr($authData, strlen(self::GS2_HEADER));
        parse_str(str_replace(',', '&', $clientFirstBare), $cf);
        if (!isset($cf['n']) || !isset($cf['r'])) {
            return new SaslResult(ReasonCode::BAD_AUTHENTICATION_METHOD, '', true, null);
        }

        // authcid must not contain '=' or ',' (RFC 5802).
        if (strpbrk($cf['n'], '=,') !== false) {
            return new SaslResult(ReasonCode::BAD_AUTHENTICATION_METHOD, '', true, null);
        }

        $cred = $this->userService->getScramCredential((string)$cf['n']);
        if ($cred === null) {
            // Unknown / disabled account, or no SCRAM credential configured.
            return new SaslResult(ReasonCode::NOT_AUTHORIZED, '', true, null);
        }

        $combined         = $cf['r'] . substr(base64_encode(random_bytes(18)), 0, 24);
        $this->state[$fd] = [
            'step' => 1,
            'client_first_bare' => $clientFirstBare,
            'combined_nonce' => $combined,
            'salt' => $cred['salt'],
            'iterations' => $cred['iterations'],
            'stored_key' => $cred['storedKey'],
            'server_key' => $cred['serverKey'],
            'username' => $cred['username'],
        ];

        $serverFirst = sprintf(
            'r=%s,s=%s,i=%d',
            $combined,
            $cred['salt'],
            $cred['iterations']
        );

        return new SaslResult(ReasonCode::CONTINUE_AUTHENTICATION, $serverFirst, false, null);
    }

    /**
     * Parse client-final, verify ClientProof against StoredKey, build server-final.
     *
     * @param int    $fd       Client connection fd (state key).
     * @param string $authData Raw SASL data from the AUTH packet (client-final "c=,r=,p=").
     */
    private function handleClientFinal(int $fd, string $authData): SaslResult
    {
        $state = &$this->state[$fd];

        parse_str(str_replace(',', '&', $authData), $cl);
        if (!isset($cl['c']) || !isset($cl['r']) || !isset($cl['p'])) {
            $this->clear($fd);
            return new SaslResult(ReasonCode::BAD_AUTHENTICATION_METHOD, '', true, null);
        }

        // combined nonce must match what we sent.
        if ($cl['r'] !== $state['combined_nonce']) {
            $this->clear($fd);
            return new SaslResult(ReasonCode::BAD_AUTHENTICATION_METHOD, '', true, null);
        }

        $channelBinding = base64_decode($cl['c']);
        if ($channelBinding === false || !str_starts_with($channelBinding, self::GS2_HEADER)) {
            $this->clear($fd);
            return new SaslResult(ReasonCode::BAD_AUTHENTICATION_METHOD, '', true, null);
        }

        $clientFinalNoProof = sprintf('c=%s,r=%s', $cl['c'], $cl['r']);
        $authMessage        = $state['client_first_bare'] . ',' . $this->serverFirstForAuth($state) . ',' . $clientFinalNoProof;

        $clientProof = base64_decode($cl['p']);
        if ($clientProof === false || strlen($clientProof) !== 32) { // SHA-256 = 32 bytes
            $this->clear($fd);
            return new SaslResult(ReasonCode::BAD_AUTHENTICATION_METHOD, '', true, null);
        }

        $storedKey = base64_decode($state['stored_key']);
        $serverKey = base64_decode($state['server_key']);

        // ClientSignature = HMAC(StoredKey, AuthMessage); ClientKey = ClientProof XOR ClientSignature.
        $clientSignature = hash_hmac('sha256', $authMessage, $storedKey, true);
        $clientKey       = $clientProof ^ $clientSignature;

        // Verify the client really knows the password: H(ClientKey) must equal StoredKey.
        if (!hash_equals(hash('sha256', $clientKey, true), $storedKey)) {
            $this->clear($fd);
            return new SaslResult(ReasonCode::NOT_AUTHORIZED, '', true, null);
        }

        // Prove to the client we hold ServerKey: v = base64(HMAC(ServerKey, AuthMessage)).
        $serverSignature = hash_hmac('sha256', $authMessage, $serverKey, true);
        $serverFinal     = 'v=' . base64_encode($serverSignature);

        $username = $state['username'];
        $this->clear($fd);

        return new SaslResult(ReasonCode::SUCCESS, $serverFinal, true, $username);
    }

    /**
     * Reconstruct the server-first string used inside AuthMessage (mirrors
     * handleClientFirst's output).
     *
     * @param array $state Per-connection SCRAM state (combined_nonce, salt, iterations).
     */
    private function serverFirstForAuth(array $state): string
    {
        return sprintf('r=%s,s=%s,i=%d', $state['combined_nonce'], $state['salt'], $state['iterations']);
    }

    /**
     * Drop the per-connection SCRAM state (called on success or any failure).
     *
     * @param int $fd Client connection fd (state key).
     */
    public function clear(int $fd): void
    {
        unset($this->state[$fd]);
    }
}
