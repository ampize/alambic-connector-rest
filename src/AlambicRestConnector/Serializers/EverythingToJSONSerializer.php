<?php

namespace AlambicRestConnector\Serializers;

/**
 * Class EverythingToJSONSerializer
 */
class EverythingToJSONSerializer implements SerializerInterface
{
    /**
     * Serialize assoc array into JSON string
     *
     * @param string|array $data Assoc array to encode into JSON
     *
     * @return string
     */
    public function serialize($data)
    {
        $data = json_encode($data);
        if ($data === '[]') {
            return '{}';
        } else {
            return $data;
        }
    }

    /**
     * Deserialize JSON into an assoc array
     *
     * @param string $data JSON encoded string
     * @param array  $headers Response headers
     *
     * @return array
     */
    public function deserialize($data, $headers)
    {
        return json_decode($data, true);
    }
}
