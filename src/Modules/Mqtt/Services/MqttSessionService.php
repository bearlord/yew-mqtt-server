<?php

namespace App\Modules\Mqtt\Services;

use App\Models\Extension\MqttSession;
use Yew\Coroutine\Server\Server;

/**
 * Persistence layer for MQTT sessions backed by the mqtt_session table.
 *
 * The in-memory GetConnection map (fd <-> clientId) remains the hot path for
 * routing, while this service mirrors each session into the database so that
 * broker restarts / other workers can recover which clients hold a persistent
 * (clean_start = 0) session and which are currently online.
 */
class MqttSessionService
{
    /**
     * Current broker node identifier (process name), written to every row.
     */
    public function node(): string
    {
        return Server::$instance
            ? Server::$instance->getProcessManager()->getCurrentProcess()->getProcessName()
            : 'unknown';
    }

    /**
     * Open (or reuse) a session row for a connecting client.
     *
     * A persistent session (clean_start = 0) is upserted keyed by client_id so
     * reconnects keep the same row; a clean session is also recorded but will be
     * dropped on disconnect. Stored Will id (if any) is attached for later publish.
     *
     * @param string $clientId Connecting client identifier.
     * @param int $fd Connection file descriptor (used only to mint a session id for fresh sessions).
     * @param array<string, mixed> $data Session attributes (clean_start, session_expiry, will_id, ...).
     * @return MqttSession The persisted session model.
     */
    public function open(string $clientId, int $fd, array $data = []): MqttSession
    {
        $row = MqttSession::find()->where(['client_id' => $clientId])->one();

        if ($row === null) {
            $row = new MqttSession();
            $row->client_id = $clientId;
            $row->session_id = $this->sessionId($clientId, $fd);
        }

        $row->clean_start = (int)($data['clean_start'] ?? 0);
        $row->session_expiry = $data['session_expiry'] ?? null;
        $row->is_online = 1;
        $row->node = $this->node();
        $row->connected_at = date('Y-m-d H:i:s.u');
        if (!empty($data['will_id'])) {
            $row->will_id = (int)$data['will_id'];
        }
        $row->save(false);

        return $row;
    }

    /**
     * Mark a session offline and stamp the disconnection time.
     *
     * @param string $clientId Disconnecting client identifier.
     * @param int $fd Connection file descriptor.
     * @param bool $dropWhenClean When true and the session was clean (clean_start = 1), the row is deleted instead of kept.
     */
    public function close(string $clientId, int $fd, bool $dropWhenClean = true): void
    {
        $row = MqttSession::find()->where(['client_id' => $clientId])->one();
        if ($row === null) {
            return;
        }

        // Clean sessions carry no durable state; drop the row on disconnect.
        if ($dropWhenClean && $row->clean_start == 1) {
            $row->delete();
            return;
        }

        $row->is_online = 0;
        $row->disconnected_at = date('Y-m-d H:i:s.u');
        $row->save(false);
    }

    /**
     * Refresh the heartbeat / last activity timestamp of an online session.
     *
     * @param string $clientId Client identifier.
     */
    public function refresh(string $clientId): void
    {
        MqttSession::updateAll(
            ['is_online' => 1, 'connected_at' => date('Y-m-d H:i:s.u')],
            ['client_id' => $clientId]
        );
    }

    /**
     * Fetch the active (online) session row for a client, if any.
     *
     * @param string $clientId Client identifier.
     */
    public function findActiveByClientId(string $clientId): ?MqttSession
    {
        return MqttSession::find()
            ->where(['client_id' => $clientId, 'is_online' => 1])
            ->one();
    }

    /**
     * Build a broker-side session id (clientId@fd) for a fresh connection.
     *
     * @param string $clientId Client identifier.
     * @param int $fd Connection file descriptor.
     */
    private function sessionId(string $clientId, int $fd): string
    {
        return $clientId . '@' . $fd;
    }
}
