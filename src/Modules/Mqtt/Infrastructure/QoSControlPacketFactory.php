<?php

namespace App\Modules\Mqtt\Infrastructure;

use Yew\Mqtt\Hex\ReasonCode;
use Yew\Mqtt\Message\PubAck;
use Yew\Mqtt\Message\PubComp;
use Yew\Mqtt\Message\PubRec;
use Yew\Mqtt\Message\PubRel;
use Yew\Mqtt\Protocol\Types;

/**
 * Factory for the QoS 1/2 control packets exchanged during a publish handshake.
 *
 * Each control packet differs only by its MQTT type and an optional Reason
 * Code, so a single factory keeps every call site free of the repetitive
 * `new PubX(); $p->setMessageId(...); $p->setCode(...);` ceremony.
 *
 * @method PubAck create(int $type, int $messageId, int $reasonCode = ReasonCode::SUCCESS)
 */
final class QoSControlPacketFactory
{
    /** @var array<int, class-string<PubAck|PubRec|PubRel|PubComp>> */
    private const TYPES = [
        Types::PUBACK => PubAck::class,
        Types::PUBREC => PubRec::class,
        Types::PUBREL => PubRel::class,
        Types::PUBCOMP => PubComp::class,
    ];

    /**
     * Build a QoS control packet for the given type and packet id.
     *
     * @param int $type        One of Types::PUBACK / PUBREC / PUBREL / PUBCOMP.
     * @param int $messageId   Packet identifier echoed from the peer.
     * @param int $reasonCode  MQTT 5 reason code (default Success).
     * @return PubAck|PubRec|PubRel|PubComp
     */
    public function create(int $type, int $messageId, int $reasonCode = ReasonCode::SUCCESS)
    {
        if (!isset(self::TYPES[$type])) {
            throw new \InvalidArgumentException(sprintf('Unsupported QoS control packet type: %d', $type));
        }

        /** @var class-string<PubAck|PubRec|PubRel|PubComp> $class */
        $class = self::TYPES[$type];
        /** @var PubAck|PubRec|PubRel|PubComp $packet */
        $packet = new $class();
        $packet->setMessageId($messageId);
        $packet->setCode($reasonCode);

        return $packet;
    }
}
