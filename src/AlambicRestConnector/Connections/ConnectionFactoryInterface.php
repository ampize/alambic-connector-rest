<?php

namespace AlambicRestConnector\Connections;

use AlambicRestConnector\Serializers\SerializerInterface;
use Psr\Log\LoggerInterface;

/**
 * Class AbstractConnection
 */
interface ConnectionFactoryInterface
{
    /**
     * @param $handler
     * @param array $connectionParams
     * @param SerializerInterface $serializer
     * @param LoggerInterface $logger
     * @param LoggerInterface $tracer
     */
    public function __construct(callable $handler, array $connectionParams, SerializerInterface $serializer, LoggerInterface $logger, LoggerInterface $tracer);

    /**
     * @param $hostDetails
     *
     * @return ConnectionInterface
     */
    public function create($hostDetails);
}
