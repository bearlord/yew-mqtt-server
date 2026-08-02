<?php

namespace App\Modules\Mqtt\Services;

use App\Models\Extension\MqttClient;
use App\Modules\Mqtt\Services\MqttSaslAuthenticationService;
use Yew\Plugins\Connection\GetConnection;
use Yew\Plugins\Pack\GetBoostSend;
use Yew\Plugins\Uid\GetUid;

/**
 * Business service for MQTT client records and connection lifecycle.
 *
 * Owns the CONNECT / DISCONNECT / PINGREQ flows: persists the client row,
 * registers the in-memory session (via GetConnection), binds the fd to the
 * subscriber uid, delivers buffered offline messages and publishes the Will
 * Message when a session ends.
 */
class MqttClientService
{
    use GetConnection;
    use GetUid;
    use GetBoostSend;

    /**
     * Fetch a client record by its primary key.
     *
     * @param int|string $id Client primary key.
     */
    public function getItemById($id): ?array
    {
        $row = MqttClient::find()->where(['id' => $id])->asArray()->one();
        return $row === null ? null : $row;
    }

    /**
     * Resolve a client's client_id from its primary key.
     *
     * @param int $id Client primary key.
     */
    public function getClientIdById(int $id): ?string
    {
        $row = $this->getItemById($id);
        return $row['client_id'] ?? null;
    }

    /**
     * Fetch a client record by its client_id.
     *
     * @param string $clientId Client identifier.
     */
    public function getItemByClientId(string $clientId): ?array
    {
        $row = MqttClient::find()->where(['client_id' => $clientId])->asArray()->one();
        return $row === null ? null : $row;
    }

    /**
     * Insert a new client row or update the existing one for the given client_id.
     *
     * @param string $clientId Client identifier (unique key).
     * @param array<string, mixed> $data Client attributes (is_active, last_connected_time, ...).
     */
    public function saveOrUpdateMqttClient(string $clientId, array $data): int
    {
        $row = MqttClient::find()->where(['client_id' => $clientId])->one();

        if ($row === null) {
            $row = new MqttClient();
            $data['client_id'] = $clientId;
        }

        $row->setAttributes($data, false);
        $row->save(false);

        return $row->id;
    }

    /**
     * Update an existing client record by client_id.
     *
     * @param string $clientId Client identifier.
     * @param array<string, mixed> $data Client attributes to apply.
     */
    public function updateMqttClient(string $clientId, array $data): void
    {
        $row = MqttClient::find()->where(['client_id' => $clientId])->one();
        if ($row === null) {
            return;
        }
        $row->setAttributes($data, false);
        $row->save(false);
    }

    /**
     * Handle CONNECT: persist client, register session, bind uid, deliver offline, send CONNACK.
     *
     * @param string $clientId Connecting client identifier.
     * @param int $fd Connection fd.
     * @param int $protocolLevel MQTT protocol version.
     * @param array<string, mixed> $data CONNECT options (clean_start, will, ...).
     */
    public function connectProcess(
        string $clientId,
        int    $fd,
        int    $protocolLevel,
        array  $data): void
    {
        $this->saveOrUpdateMqttClient($clientId, [
            'last_connected_time' => date('Y-m-d H:i:s'),
            'is_active' => 1,
        ]);

        // Register the active session (fd -> clientId, clientId -> session).
        $this->setFdSession($fd, 'uid', $clientId);
        $this->setClientSession($clientId, 'uid', $fd);
        $this->setClientSession($clientId, 'protocol_level', $protocolLevel);
        $this->bindUid($fd, $clientId);

        // Persist the Will (if any) so it survives a broker restart, then point
        // the session row at it. The Will is published later on disconnect.
        $willId = MqttServices::will()->saveWill($this->getFdSessionMulti($fd)['will'] ?? null, $clientId);

        // Mirror the session into the database so it survives broker restarts.
        // MQTT clean_start = 1 means "discard the session", so store its inverse.
        MqttServices::session()->open($clientId, $fd, [
            'clean_start' => !empty($data['session_start']) ? 0 : 1,
            'session_expiry' => $data['session_expiry_interval'] ?? null,
            'will_id' => $willId,
        ]);

        // Replay buffered messages for a persistent session.
        if (($data['session_start'] ?? 0) == 0) {
            MqttServices::offlineDelivery()->deliverOfflineMessages($clientId, $fd, $protocolLevel);
        }

        $this->autoBoostSend($fd, $this->buildConnAck(0, $protocolLevel));
    }

    /**
     * Handle DISCONNECT: mark inactive, publish Will, drop clean-session state, send DISCONNECT, clear session.
     *
     * @param int   $fd   Connection fd.
     * @param array $data Decoded disconnect payload. Expected keys:
     *                    client_id, protocol_level, data[code].
     */
    public function disconnectProcess(int $fd, array $data): void
    {
        $clientId = (string)($data['client_id'] ?? '');
        $code = isset($data['data']['code']) ? (int)$data['data']['code'] : null;

        $this->updateMqttClient($clientId, ['is_active' => 0]);

        $protocolLevel = (int)($data['protocol_level'] ?? null)
            ?? (int)($this->getClientSession($clientId, 'protocol_level') ?? 4);

        // Publish the stored Will (if any) on unexpected/clean termination.
        $session = $this->getFdSessionMulti($fd) ?? [];
        if (!empty($session['will'])) {
            MqttServices::will()->publishWill($session['will'], $clientId, $protocolLevel);
        }

        // Resolve the persisted will id before the session row is closed/expired.
        $willId = MqttServices::session()->findActiveByClientId($clientId)?->will_id;

        $this->unBindUid($fd);
        $this->clearFdSession($fd);
        $this->clearClientSession($clientId);

        // Drop the persisted session (clean sessions are deleted, persistent ones kept offline).
        MqttServices::session()->close($clientId, $fd);

        // The Will has been published (or had none); the persisted will row is consumed.
        MqttServices::will()->consumeWill($willId);

        if ($code !== null) {
            $this->autoBoostSend($fd, $this->buildDisconnect($code, $protocolLevel));
        }

        // Drop any half-finished enhanced (SASL) auth state / CONNECT context.
        MqttSaslAuthenticationService::clearState($fd);
        $this->setFdSession($fd, 'enhanced_auth', null);
    }

    /**
     * Handle PINGREQ: refresh heartbeat, mark active, reply PINGRESP.
     *
     * @param string $clientId Pinging client identifier.
     * @param int $fd Connection fd.
     */
    public function pingreqProcess(string $clientId, int $fd): void
    {
        $now = date('Y-m-d H:i:s');
        $this->updateMqttClient($clientId, ['last_connected_time' => $now]);
        $this->setClientSession($clientId, 'last_ping_time', $now);
        MqttServices::session()->refresh($clientId);

        $this->autoBoostSend($fd, new \Yew\Mqtt\Message\PingResp());
    }

    /** Build a CONNACK packet. */
    private function buildConnAck(int $code, int $protocolLevel): object
    {
        $packet = new \Yew\Mqtt\Message\ConnAck();
        $packet->setCode($code);
        $packet->setProtocolLevel($protocolLevel);
        if (method_exists($packet, 'setSessionPresent')) {
            $packet->setSessionPresent(false);
        }
        return $packet;
    }

    /** Build a DISCONNECT packet. */
    private function buildDisconnect(int $code, int $protocolLevel): object
    {
        $packet = new \Yew\Mqtt\Message\DisConnect();
        $packet->setCode($code);
        $packet->setProtocolLevel($protocolLevel);
        return $packet;
    }
}
