<?php

namespace AlambicRestConnector;

/**
 * Class Client
 */
class Client
{
    /**
     * @var Transport
     */
    public $transport;

    /** @var  callback */
    protected $endpoints;

    /**
     * Client constructor
     *
     * @param Transport $transport
     * @param callable $endpoint
     * @param AbstractNamespace[] $registeredNamespaces
     */
    public function __construct(Transport $transport, callable $endpoint)
    {
        $this->transport = $transport;
        $this->endpoints = $endpoint;
    }

    /**
     * $params['body'] = (array|string)
     *
     * @param $params array Associative array of parameters
     *
     * @return array
     */
    public function run($args = array())
    {
        $body = $this->extractArgument($args, 'body');

        /** @var callback $endpointBuilder */
        $endpointBuilder = $this->endpoints;

        if ($args['multivalued']) {
            $endpoint = $endpointBuilder('MultiEndPoint');
        } else {
            $endpoint = $endpointBuilder('SingleEndPoint');
        }

        $endpoint->setBody($body);
        $endpoint->setParams($args);

        return $this->performRequest($endpoint);
    }

    /**
     * @param $endpoint AbstractEndpoint
     *
     * @throws \Exception
     * @return array
     */
    private function performRequest($endpoint)
    {
        $promise =  $this->transport->performRequest(
            $endpoint->getMethod(),
            $endpoint->getURI(),
            $endpoint->getParams(),
            $endpoint->getBody(),
            $endpoint->getOptions()
        );

        return $this->transport->resultOrFuture($promise, $endpoint->getOptions());
    }

    /**
     * @param array $params
     * @param string $arg
     *
     * @return null|mixed
     */
    public function extractArgument(&$params, $arg)
    {
        if (is_object($params) === true) {
            $params = (array) $params;
        }

        if (isset($params[$arg]) === true) {
            $val = $params[$arg];
            unset($params[$arg]);

            return $val;
        } else {
            return null;
        }
    }
}
