<?php

namespace AlambicRestConnector;

use AlambicRestConnector\Common\Exceptions;
use AlambicRestConnector\Connections\Connection;
use AlambicRestConnector\Connections\ConnectionInterface;
use GuzzleHttp\Ring\Future\FutureArrayInterface;
use Psr\Log\LoggerInterface;

/**
 * Class Transport
 */
class Transport
{

    public $connection;

    /**
     * @var LoggerInterface
     */
    private $log;

    /**
     * Transport class is responsible for sending requests to the
     * underlying connection
     *
     * @param \Psr\Log\LoggerInterface $log    Monolog logger object
     */
    public function __construct($connection, LoggerInterface $log)
    {
        $this->log = $log;
        $this->connection = $connection;
    }

    /**
     * Perform a request to the Cluster
     *
     * @param string $method     HTTP method to use
     * @param string $uri        HTTP URI to send request to
     * @param null $params     Optional query parameters
     * @param null $body       Optional query body
     * @param array $options
     *
     * @throws Common\Exceptions\NoNodesAvailableException|\Exception
     * @return FutureArrayInterface
     */
    public function performRequest($method, $uri, $params = null, $body = null, $options = [])
    {
        $connection = $this->connection;

        $response             = array();
        $caughtException      = null;

        $future = $connection->performRequest(
            $method,
            $uri,
            $params,
            $body,
            $options,
            $this
        );

        $future->promise()->then(
            //onSuccess
            function ($response) {
                // Note, this could be a 4xx or 5xx error
            },
            //onFailure
            function ($response) {
                // log stuff
            });

        return $future;
    }

    /**
     * @param FutureArrayInterface $result  Response of a request (promise)
     * @param array                $options Options for transport
     *
     * @return callable|array
     */
    public function resultOrFuture($result, $options = [])
    {
        $response = null;
        $async = isset($options['client']['future']) ? $options['client']['future'] : null;
        if (is_null($async) || $async === false) {
            do {
                $result = $result->wait();
            } while ($result instanceof FutureArrayInterface);

            return $result;
        } elseif ($async === true || $async === 'lazy') {
            return $result;
        }
    }

}
