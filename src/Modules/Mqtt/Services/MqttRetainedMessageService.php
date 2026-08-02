<?php

namespace App\Modules\Mqtt\Services;

use App\Models\Extension\MqttRetainedMessage;

class MqttRetainedMessageService
{
    /**
     * Persist (or replace) a retained message for a topic.
     *
     * @param array<string, mixed> $data Retained message attributes (topic, payload, qos).
     */
    public function saveRetainedMessage(array $data): bool
    {
        $model = MqttRetainedMessage::findOne(['topic' => $data['topic']]);
        if ($model === null) {
            $model = new MqttRetainedMessage();
        }
        $model->setAttributes($data, false);
        $model->save(false);

        return true;
    }

    /**
     * Return every retained message whose topic matches the given filter.
     * Wildcard-free filters take a fast direct lookup; '+'/'#' filters fall
     * back to scanning all rows via matchTopicFilter().
     *
     * @param string $filter Topic filter (may contain '+' or '#').
     */
    public function getRetainedByFilter(string $filter): array
    {
        // Fast path: a plain topic (no wildcards) maps 1:1 to a retained row.
        if (strpos($filter, '+') === false && strpos($filter, '#') === false) {
            $row = MqttRetainedMessage::findOne(['topic' => $filter]);
            return $row === null ? [] : [$row];
        }

        $all = MqttRetainedMessage::find()->all();
        $matched = [];
        foreach ($all as $row) {
            if (self::matchTopicFilter($filter, $row->getAttribute('topic'))) {
                $matched[] = $row;
            }
        }
        return $matched;
    }

    /**
     * Match an MQTT topic filter against a concrete topic name.
     * Supports '+' (one level) and '#' (zero+ trailing levels, must be last).
     *
     * @param string $filter Topic filter (may contain '+' or '#').
     * @param string $topic Concrete topic name.
     */
    public static function matchTopicFilter(string $filter, string $topic): bool
    {
        if ($topic === '') {
            return false;
        }

        $filterLevels = explode('/', $filter);
        $topicLevels = explode('/', $topic);
        $filterCount = count($filterLevels);
        $topicCount = count($topicLevels);

        foreach ($filterLevels as $i => $level) {
            if ($level === '#') {
                // '#' matches the remaining levels (including zero levels), so
                // any topic that reaches this point is a match.
                return true;
            }

            if ($i >= $topicCount) {
                // Filter has more levels than the topic and no '#': no match.
                return false;
            }

            if ($level === '+') {
                // '+' matches exactly one level; move on to the next level.
                continue;
            }

            if ($level !== $topicLevels[$i]) {
                return false;
            }
        }

        // All filter levels consumed: match only when the level counts align.
        return $filterCount === $topicCount;
    }
}
