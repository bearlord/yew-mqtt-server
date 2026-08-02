<?php

namespace App\Modules\Mqtt\Services;

/** Project MQTT authentication helper against the mqtt_user table. */
class MqttAuthService
{
    public function __construct(
        private readonly MqttUserService $userService = new MqttUserService(),
    ) {
    }

    /**
     * Authenticate a CONNECT request; empty username is accepted (open).
     *
     * @param string $username CONNECT username (empty = anonymous, always accepted).
     * @param string $password CONNECT password.
     */
    public function auth(string $username, string $password): bool
    {
        if ($username === '') {
            return true;
        }

        return $this->userService->verifyPassword($username, $password);
    }
}
