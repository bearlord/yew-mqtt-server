<?php

namespace App\Modules\Mqtt\Services;

use App\Models\Extension\MqttUser;

/** Business service for MQTT user accounts (the mqtt_user table). */
class MqttUserService
{
    /** SCRAM default iteration count (RFC 5802 recommends >= 4096). */
    public const SCRAM_ITERATIONS = 4096;

    /**
     * Verify a CONNECT's username/password against the stored password_hash.
     *
     * @param string $username CONNECT username (empty username is rejected).
     * @param string $password CONNECT password (plaintext, checked via password_verify).
     */
    public function verifyPassword(string $username, string $password): bool
    {
        // No username → nothing to match against.
        if ($username === '') {
            return false;
        }

        /** @var MqttUser $user */
        $user = MqttUser::find()
            ->where([
                'user_name' => $username,
            ])
            ->one();
        if (empty($user)) {
            return false;
        }

        // Disabled accounts must not authenticate, even with a correct password.
        if (empty($user->is_active)) {
            return false;
        }

        return password_verify($password, $user->password_hash);
    }

    /**
     * Create a new MQTT user account.
     *
     * Stores both a bcrypt hash (for PLAIN / CONNECT) and RFC 5802 SCRAM-SHA-256
     * credentials (StoredKey / ServerKey) so the account works with either method.
     *
     * @param string $username User name (unique).
     * @param string $password Plaintext password (hashed/derived before storage).
     * @param int $isActive Whether the account is enabled (1 = active, 0 = disabled).
     */
    public function createUser(string $username, string $password, int $isActive = 1): MqttUser
    {
        $scram = $this->createScramCredential($password);

        $user = new MqttUser();
        $user->user_name = $username;
        $user->password_hash = password_hash($password, PASSWORD_DEFAULT);
        $user->is_active = $isActive;
        $user->scram_salt = $scram['salt'];
        $user->scram_iterations = $scram['iterations'];
        $user->scram_stored_key = $scram['storedKey'];
        $user->scram_server_key = $scram['serverKey'];
        $user->save();

        return $user;
    }

    /**
     * Derive RFC 5802 SCRAM-SHA-256 credentials from a plaintext password.
     *
     * SaltedPassword = PBKDF2(HMAC-SHA-256, password, salt, i)
     * StoredKey     = H(ClientKey),  ClientKey = HMAC(SaltedPassword, "Client Key")
     * ServerKey     = HMAC(SaltedPassword, "Server Key")
     *
     * Only StoredKey/ServerKey are persisted; the plaintext and SaltedPassword
     * are discarded, so the server can verify a ClientProof without ever holding
     * the password.
     *
     * @param string $password Plaintext password.
     * @return array{salt: string, iterations: int, storedKey: string, serverKey: string}
     */
    public function createScramCredential(string $password): array
    {
        $salt = random_bytes(16);
        $iterations = self::SCRAM_ITERATIONS;

        $saltedPassword = hash_pbkdf2('sha256', $password, $salt, $iterations, 0, true);

        $clientKey = hash_hmac('sha256', 'Client Key', $saltedPassword, true);
        $storedKey = hash('sha256', $clientKey, true);

        $serverKey = hash_hmac('sha256', 'Server Key', $saltedPassword, true);

        return [
            'salt' => base64_encode($salt),
            'iterations' => $iterations,
            'storedKey' => base64_encode($storedKey),
            'serverKey' => base64_encode($serverKey),
        ];
    }

    /**
     * Fetch the SCRAM credential material for a user, or null if the account is
     * missing, disabled, or has no SCRAM credential set up.
     *
     * @param string $username Client-supplied authentication identity.
     * @return array{salt: string, iterations: int, storedKey: string, serverKey: string, username: string}|null
     */
    public function getScramCredential(string $username): ?array
    {
        if ($username === '') {
            return null;
        }

        /** @var MqttUser $user */
        $user = MqttUser::find()
            ->where(['user_name' => $username])
            ->one();
        if (empty($user) || empty($user->is_active)) {
            return null;
        }

        if ($user->scram_salt === null || $user->scram_stored_key === null || $user->scram_server_key === null) {
            return null;
        }

        return [
            'salt' => $user->scram_salt,
            'iterations' => (int)$user->scram_iterations,
            'storedKey' => $user->scram_stored_key,
            'serverKey' => $user->scram_server_key,
            'username' => $user->user_name,
        ];
    }
}
