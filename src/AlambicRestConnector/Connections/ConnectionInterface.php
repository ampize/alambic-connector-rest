<?php

namespace AlambicRestConnector\Connections;

use AlambicRestConnector\Serializers\SerializerInterface;
use AlambicRestConnector\Transport;
use Psr\Log\LoggerInterface;

/**
 * Interface ConnectionInterface
 */
interface ConnectionInterface
{
    /**
     * Constructor
     *
     * @param $handler
     * @param array $hostDetails
     * @param array $connectionParams connection-specific parameters
     * @param \AlambicRestConnector\Serializers\SerializerInterface $serializer
     * @param \Psr\Log\LoggerInterface $log          Logger object
     * @param \Psr\Log\LoggerInterface $trace        Logger object
     */
    public function __construct($handler, $hostDetails, $connectionParams,
                                SerializerInterface $serializer, LoggerInterface $log, LoggerInterface $trace);

    /**
     * Get the transport schema for this connection
     *
     * @return string
     */
    public function getTransportSchema();

    /**
     * @param $method
     * @param $uri
     * @param null $params
     * @param null $body
     * @param array $options
     * @param \AlambicRestConnector\Transport $transport
     * @return mixed
     */
    public function performRequest($method, $uri, $params = null, $body = null, $options = [], Transport $transport);
}
