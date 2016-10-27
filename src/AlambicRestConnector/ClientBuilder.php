<?php

namespace AlambicRestConnector;


use AlambicRestConnector\Common\Exceptions\InvalidArgumentException;
use AlambicRestConnector\Common\Exceptions\RuntimeException;
use AlambicRestConnector\Connections\Connection;
use AlambicRestConnector\Connections\ConnectionFactory;
use AlambicRestConnector\Connections\ConnectionFactoryInterface;
use AlambicRestConnector\Serializers\SerializerInterface;
use AlambicRestConnector\Serializers\SmartSerializer;
use GuzzleHttp\Ring\Client\CurlHandler;
use GuzzleHttp\Ring\Client\CurlMultiHandler;
use GuzzleHttp\Ring\Client\Middleware;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Class ClientBuilder
 */
class ClientBuilder
{
    /** @var Transport */
    private $transport;

    /** @var callback */
    private $endpoint;

    /** @var  ConnectionFactoryInterface */
    private $connectionFactory;

    private $handler;

    /** @var  LoggerInterface */
    private $logger;

    /** @var  LoggerInterface */
    private $tracer;

    /** @var  string */
    private $serializer = '\AlambicRestConnector\Serializers\SmartSerializer';

    /** @var array */
    private $host;

    /** @var null|array  */
    private $sslCert = null;

    /** @var null|array  */
    private $sslKey = null;

    /** @var null|bool|string */
    private $sslVerification = null;

    /** @var  string */
    private $multivalued = false;

    /**
     * @return ClientBuilder
     */
    public static function create()
    {
        return new static();
    }

    /**
     * Build a new client from the provided config.  Hash keys
     * should correspond to the method name e.g. ['connectionPool']
     * corresponds to setConnectionPool().
     *
     * Missing keys will use the default for that setting if applicable
     *
     * Unknown keys will throw an exception by default, but this can be silenced
     * by setting `quiet` to true
     *
     * @param array $config hash of settings
     * @param bool $quiet False if unknown settings throw exception, true to silently
     *                    ignore unknown settings
     * @throws Common\Exceptions\RuntimeException
     * @return \AlambicRestConnector\Client
     */
    public static function fromConfig($config, $quiet = false)
    {
        $builder = new self;
        foreach ($config as $key => $value) {
            $method = "set$key";
            if (method_exists($builder, $method)) {
                $builder->$method($value);
                unset($config[$key]);
            }
        }

        if ($quiet === false && count($config) > 0) {
            $unknown = implode(array_keys($config));
            throw new RuntimeException("Unknown parameters provided: $unknown");
        }
        return $builder->build();
    }

    /**
     * @param array $singleParams
     * @param array $multiParams
     * @throws \RuntimeException
     * @return callable
     */
    public static function defaultHandler($multiParams = [], $singleParams = [])
    {
        $future = null;
        if (extension_loaded('curl')) {
            $config = array_merge([ 'mh' => curl_multi_init() ], $multiParams);
            if (function_exists('curl_reset')) {
                $default = new CurlHandler($singleParams);
                $future = new CurlMultiHandler($config);
            } else {
                $default = new CurlMultiHandler($config);
            }
        } else {
            throw new \RuntimeException('AlambicRestConnector-PHP requires cURL, or a custom HTTP handler.');
        }

        return $future ? Middleware::wrapFuture($default, $future) : $default;
    }

    /**
     * @param array $params
     * @throws \RuntimeException
     * @return CurlMultiHandler
     */
    public static function multiHandler($params = [])
    {
        if (function_exists('curl_multi_init')) {
            return new CurlMultiHandler(array_merge([ 'mh' => curl_multi_init() ], $params));
        } else {
            throw new \RuntimeException('CurlMulti handler requires cURL.');
        }
    }

    /**
     * @return CurlHandler
     * @throws \RuntimeException
     */
    public static function singleHandler()
    {
        if (function_exists('curl_reset')) {
            return new CurlHandler();
        } else {
            throw new \RuntimeException('CurlSingle handler requires cURL.');
        }
    }

    /**
     * @param \AlambicRestConnector\Connections\ConnectionFactoryInterface $connectionFactory
     * @return $this
     */
    public function setConnectionFactory(ConnectionFactoryInterface $connectionFactory)
    {
        $this->connectionFactory = $connectionFactory;

        return $this;
    }

    /**
     * @param callable $endpoint
     * @return $this
     */
    public function setEndpoint($endpoint)
    {
        $this->endpoint = $endpoint;

        return $this;
    }

    /**
     * @param \AlambicRestConnector\Transport $transport
     * @return $this
     */
    public function setTransport($transport)
    {
        $this->transport = $transport;

        return $this;
    }

    /**
     * @param mixed $handler
     * @return $this
     */
    public function setHandler($handler)
    {
        $this->handler = $handler;

        return $this;
    }

    /**
     * @param \Psr\Log\LoggerInterface $logger
     * @return $this
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * @param \Psr\Log\LoggerInterface $tracer
     * @return $this
     */
    public function setTracer($tracer)
    {
        $this->tracer = $tracer;

        return $this;
    }

    /**
     * @param \AlambicRestConnector\Serializers\SerializerInterface|string $serializer
     * @throws \InvalidArgumentException
     * @return $this
     */
    public function setSerializer($serializer)
    {
        $this->parseStringOrObject($serializer, $this->serializer, 'SerializerInterface');

        return $this;
    }

    /**
     * @param array $hosts
     * @return $this
     */
    public function setHost($host)
    {
        $this->host = $host;

        return $this;
    }

    /**
     * @param $cert
     * @param null|string $password
     * @return $this
     */
    public function setSSLCert($cert, $password = null)
    {
        $this->sslCert = [$cert, $password];

        return $this;
    }

    /**
     * @param $key
     * @param null|string $password
     * @return $this
     */
    public function setSSLKey($key, $password = null)
    {
        $this->sslKey = [$key, $password];

        return $this;
    }

    /**
     * @param bool|string $value
     * @return $this
     */
    public function setSSLVerification($value = true)
    {
        $this->sslVerification = $value;

        return $this;
    }

    /**
     * @return Client
     */
    public function build()
    {
        $this->buildLoggers();

        if (is_null($this->handler)) {
            $this->handler = ClientBuilder::defaultHandler();
        }

        $sslOptions = null;
        if (isset($this->sslKey)) {
            $sslOptions['ssl_key'] = $this->sslKey;
        }
        if (isset($this->sslCert)) {
            $sslOptions['cert'] = $this->sslCert;
        }
        if (isset($this->sslVerification)) {
            $sslOptions['verify'] = $this->sslVerification;
        }

        if (!is_null($sslOptions)) {
            $sslHandler = function (callable $handler, array $sslOptions) {
                return function (array $request) use ($handler, $sslOptions) {
                    // Add our custom headers
                    foreach ($sslOptions as $key => $value) {
                        $request['client'][$key] = $value;
                    }
                    // Send the request using the handler and return the response.
                    return $handler($request);
                };
            };
            $this->handler = $sslHandler($this->handler, $sslOptions);
        }

        if (is_null($this->serializer)) {
            $this->serializer = new SmartSerializer();
        } elseif (is_string($this->serializer)) {
            $this->serializer = new $this->serializer;
        }

        if (is_null($this->connectionFactory)) {
            $connectionParams = [];
            if (isset($this->authHeaderParams)) {
                foreach($this->authHeaderParams as $authHeaderParam) {
                    $connectionParams['headers'][$authHeaderParam['property']] = [$authHeaderParam['value']];
                }
            }
            $this->connectionFactory = new ConnectionFactory($this->handler, $connectionParams, $this->serializer, $this->logger, $this->tracer);
        }

        if (is_null($this->host)) {
            $this->host = $this->getDefaultHost();
        }

        $this->buildTransport();

        if (is_null($this->endpoint)) {
            $serializer = $this->serializer;
            $this->endpoint = function ($class) use ($serializer) {
                $path = '\\AlambicRestConnector\\Endpoints\\'.$class;
                return new $path();
            };
        }

        return $this->instantiate($this->transport, $this->endpoint);

    }

    /**
     * @param Transport $transport
     * @param callable $endpoint
     * @param Object[] $registeredNamespaces
     * @return Client
     */
    protected function instantiate(Transport $transport, callable $endpoint)
    {
        return new Client($transport, $endpoint);
    }

    private function buildLoggers()
    {
        if (is_null($this->logger)) {
            $this->logger = new NullLogger();
        }

        if (is_null($this->tracer)) {
            $this->tracer = new NullLogger();
        }
    }

    private function buildTransport()
    {
        $connection = $this->buildConnectionFromHost($this->host);

        if (is_null($this->transport)) {
            $this->transport = new Transport($connection, $this->logger);
        }
    }

    private function parseStringOrObject($arg, &$destination, $interface)
    {
        if (is_string($arg)) {
            $destination = new $arg;
        } elseif (is_object($arg)) {
            $destination = $arg;
        } else {
            throw new InvalidArgumentException("Serializer must be a class path or instantiated object implementing $interface");
        }
    }

    /**
     * @return array
     */
    private function getDefaultHost()
    {
        return 'localhost';
    }

    /**
     * @param array $hosts
     *
     * @throws \InvalidArgumentException
     * @return \AlambicRestConnector\Connections\Connection[]
     */
    private function buildConnectionFromHost($host)
    {
        $host = $this->prependMissingScheme($host);
        $host = $this->extractURIParts($host);
        return $this->connectionFactory->create($host);

    }

    /**
     * @param array $host
     *
     * @throws \InvalidArgumentException
     * @return array
     */
    private function extractURIParts($host)
    {
        $parts = parse_url($host);

        if ($parts === false) {
            throw new InvalidArgumentException("Could not parse URI");
        }

        if (isset($parts['port']) !== true) {
            $parts['port'] = 80;
        }

        return $parts;
    }

    /**
     * @param string $host
     *
     * @return string
     */
    private function prependMissingScheme($host)
    {
        if (!filter_var($host, FILTER_VALIDATE_URL, FILTER_FLAG_SCHEME_REQUIRED)) {
            $host = 'http://' . $host;
        }

        return $host;
    }
}
