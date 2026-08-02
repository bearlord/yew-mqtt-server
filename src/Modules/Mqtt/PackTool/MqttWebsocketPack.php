<?php

namespace App\Modules\Mqtt\PackTool;

use App\Modules\Mqtt\Helpers\Time;
use Yew\Core\Plugins\Logger\GetLogger;
use Yew\Core\Server\Beans\ClientInfo;
use Yew\Core\Server\Config\PortConfig;
use Yew\Coroutine\Server\Server;

use Yew\Mqtt\Packet\PackV3;
use Yew\Mqtt\Packet\PackV5;
use Yew\Mqtt\Packet\UnPackV3;
use Yew\Mqtt\Packet\UnPackV5;
use Yew\Mqtt\Protocol\ProtocolV3;
use Yew\Mqtt\Protocol\ProtocolV5;
use Yew\Mqtt\Protocol\Types;

use Yew\Mqtt\Tools\UnPackTool;
use Yew\Plugins\Connection\GetConnection;
use Yew\Plugins\Pack\ClientData;
use Yew\Plugins\Pack\GetBoostSend;
use Yew\Plugins\Pack\PackTool\AbstractPack;

use Yew\Plugins\Redis\GetRedis;
use Yew\Yew;

/**
 * MQTT over WebSocket pack tool.
 */
class MqttWebsocketPack extends AbstractPack
{
    use GetBoostSend;
    use GetRedis;
    use GetLogger;
    use GetConnection;

    protected array $packMap = [
        3 => PackV3::class,
        4 => PackV3::class,
        5 => PackV5::class
    ];

    protected array $unpackMap = [
        3 => UnPackV3::class,
        4 => UnPackV3::class,
        5 => UnPackV5::class
    ];

    protected array $protocolMap = [
        3 => ProtocolV3::class,
        4 => ProtocolV3::class,
        5 => ProtocolV5::class
    ];

    /** Queue of reassembled MQTT packets from a single frame awaiting dispatch, keyed by fd. */
    private static array $pendingPackets = [];

    public function __construct()
    {
        Server::$instance->getContainer()->injectOn($this);
    }

    protected function getProtocolInstance($protocolLevel): object
    {
        $mapClass = $this->protocolMap[$protocolLevel];
        return Yew::createObject($mapClass);
    }


    public function encode($buffer): mixed
    {
        return $buffer;
    }

    public function decode($buffer): mixed
    {
        return $buffer;
    }

    public function pack($data, PortConfig $portConfig, ?string $topic = null): mixed
    {
        return $data;
    }

    public function unPack(int $fd, $data, PortConfig $portConfig): ?ClientData
    {
        // Drop packets arriving on a dead connection. When the fd has already
        // been closed (client disconnected, keep-alive timeout, will teardown)
        // Swoole's getClientInfo() returns false, which would otherwise crash
        // ClientData construction with a TypeError. Bail out early instead.
        $server = Server::$instance->getServer();
        if ($server === null || !$server->exists($fd)) {
            return null;
        }

        // If a previous frame delivered several MQTT packets at once, dispatch
        // the next queued one before consuming the new data.
        if (!empty(self::$pendingPackets[$fd])) {
            $data = array_shift(self::$pendingPackets[$fd]);
            if (empty(self::$pendingPackets[$fd])) {
                unset(self::$pendingPackets[$fd]);
            }
            return $this->parsePacket($fd, $data, $portConfig);
        }

        if ($portConfig->isOpenRecvBuffer()) {
            // Append the incoming frame to the per-connection receive buffer.
            if (!isset(Server::$buffers[$fd])) {
                Server::$buffers[$fd] = '';
            }
            Server::$buffers[$fd] .= $data;

            // Extract every complete MQTT packet now available in the buffer.
            // The first one is returned this call; any extras are queued for
            // subsequent calls so no reassembled packet is ever lost.
            $firstPacket = null;
            while (($packetLength = $this->decodeMqttPacketLength(Server::$buffers[$fd])) !== null
                && strlen(Server::$buffers[$fd]) >= $packetLength) {
                $packet = substr(Server::$buffers[$fd], 0, $packetLength);
                Server::$buffers[$fd] = substr(Server::$buffers[$fd], $packetLength);

                if ($firstPacket === null) {
                    $firstPacket = $packet;
                } else {
                    self::$pendingPackets[$fd][] = $packet;
                }
            }

            if ($firstPacket === null) {
                // Buffer does not yet hold a full packet; wait for next frame.
                return null;
            }
            $data = $firstPacket;
        }

        return $this->parsePacket($fd, $data, $portConfig);
    }

    /** Parse a single complete MQTT packet and build its ClientData. */
    private function parsePacket(int $fd, string $data, PortConfig $portConfig): ClientData
    {
        //Server::$instance->setClientInfoSnapshot(Server::$instance->getServer()->getClientInfo($fd));

        $type = UnPackTool::getType($data);
        switch ($type) {
            case Types::CONNECT:
                // Protocol version
                $protocolLevel = UnPackTool::getLevel($data);
                // Unpack payload
                $unpackedData = call_user_func([$this->getProtocolInstance($protocolLevel), 'unpack'], $data);
                // Client identifier
                $clientId = $unpackedData['client_id'];

                // Save protocol level and client identifier in memory
                $this->setFdSession($fd, 'protocol_level', $protocolLevel);
                // Save client identifier in memory
                $this->setFdSession($fd, 'client_id', $clientId);
                // Save client identifier-keyed session property (the owning fd) in memory
                $this->setClientSession($clientId, 'fd', $fd);

                break;

            default:
                $fdSessionData = $this->getFdSessionMulti($fd);
                // Protocol level (already known for this connection)
                $protocolLevel = $fdSessionData['protocol_level'];
                // Client identifier
                $clientId = $fdSessionData['client_id'];
                // Unpack payload
                $unpackedData = call_user_func([$this->getProtocolInstance($protocolLevel), 'unpack'], $data);
        }

        $typeName = Types::getType($type);

        return new ClientData(
            $fd,
            $portConfig->getBaseType(),
            sprintf("mqtt-websocket/%s", $typeName),
            [
                'type' => $type,
                'protocol_level' => $protocolLevel,
                'client_id' => $clientId,
                'data' => $unpackedData
            ]);
    }

    /**
     * Decode the MQTT Remaining Length field and return the total packet length.
     * Returns null when the buffer is incomplete or the length field is malformed.
     */
    private function decodeMqttPacketLength(string $buffer): ?int
    {
        if (strlen($buffer) < 2) {
            return null;
        }

        $multiplier = 1;
        $value = 0;
        $offset = 1; // skip the 1-byte fixed header

        do {
            if ($offset - 1 >= 4 || !isset($buffer[$offset])) {
                return null; // malformed or incomplete length field
            }
            $digit = ord($buffer[$offset]);
            $value += ($digit & 0x7F) * $multiplier;
            $multiplier *= 128;
            $offset++;
        } while (($digit & 0x80) !== 0);

        return 1 + ($offset - 1) + $value;
    }

    public static function changePortConfig(PortConfig $portConfig)
    {
    }

}
