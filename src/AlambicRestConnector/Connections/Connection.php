<?php

namespace AlambicRestConnector\Connections;

use AlambicRestConnector\Common\Exceptions\BadRequest400Exception;
use AlambicRestConnector\Common\Exceptions\Forbidden403Exception;
use AlambicRestConnector\Common\Exceptions\MaxRetriesException;
use AlambicRestConnector\Common\Exceptions\Missing404Exception;
use AlambicRestConnector\Common\Exceptions\RequestTimeout408Exception;
use AlambicRestConnector\Common\Exceptions\Conflict409Exception;
use AlambicRestConnector\Common\Exceptions\Curl\CouldNotConnectToHost;
use AlambicRestConnector\Common\Exceptions\Curl\CouldNotResolveHostException;
use AlambicRestConnector\Common\Exceptions\Curl\OperationTimeoutException;
use AlambicRestConnector\Common\Exceptions\ServerErrorResponseException;
use AlambicRestConnector\Common\Exceptions\TransportException;
use AlambicRestConnector\Serializers\SerializerInterface;
use AlambicRestConnector\Transport;
use GuzzleHttp\Ring\Core;
use GuzzleHttp\Ring\Exception\ConnectException;
use GuzzleHttp\Ring\Exception\RingException;
use Psr\Log\LoggerInterface;

/**
 * Class AbstractConnection
 */
class Connection implements ConnectionInterface
{
    /** @var  callable */
    protected $handler;

    /** @var SerializerInterface */
    protected $serializer;

    /**
     * @var string
     */
    protected $transportSchema = 'http';    // TODO depreciate this default

    /**
     * @var string
     */
    protected $host;

    /**
     * @var string || null
     */
    protected $path;

    /**
     * @var LoggerInterface
     */
    protected $log;

    /**
     * @var LoggerInterface
     */
    protected $trace;

    /**
     * @var array
     */
    protected $connectionParams;

    /**
     * Constructor
     *
     * @param $handler
     * @param array $hostDetails
     * @param array $connectionParams Array of connection-specific parameters
     * @param \AlambicRestConnector\Serializers\SerializerInterface $serializer
     * @param \Psr\Log\LoggerInterface $log              Logger object
     * @param \Psr\Log\LoggerInterface $trace
     */
    public function __construct($handler, $hostDetails, $connectionParams,
                                SerializerInterface $serializer, LoggerInterface $log, LoggerInterface $trace)
    {
        if (isset($hostDetails['port']) !== true) {
            $hostDetails['port'] = 80;
        }

        if (isset($hostDetails['scheme'])) {
            $this->transportSchema = $hostDetails['scheme'];
        }

        if (isset($hostDetails['user']) && isset($hostDetails['pass'])) {
            $connectionParams['client']['curl'][CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
            $connectionParams['client']['curl'][CURLOPT_USERPWD] = $hostDetails['user'].':'.$hostDetails['pass'];
        }

        $connectionParams['client']['curl'][CURLOPT_FOLLOWLOCATION]=1;
        
        $host = $hostDetails['host'];
        $path = null;
        if (isset($hostDetails['path']) === true) {
            $path = $hostDetails['path'];
        }
        $this->host             = $host;
        $this->path             = $path;
        $this->log              = $log;
        $this->trace            = $trace;
        $this->connectionParams = $connectionParams;
        $this->serializer       = $serializer;

        $this->handler = $this->wrapHandler($handler, $log, $trace);
    }

    /**
     * @param $method
     * @param $uri
     * @param null $params
     * @param null $body
     * @param array $options
     * @param \AlambicRestConnector\Transport $transport
     * @return mixed
     */
    public function performRequest($method, $uri, $params = null, $body = null, $options = [], Transport $transport = null)
    {
        if (isset($body) === true) {
            $body = $this->serializer->serialize($body);
        }

        $request = [
            'http_method' => $method,
            'scheme'      => $this->transportSchema,
            'uri'         => $this->getURI($uri, $params),
            'body'        => $body,
            'headers'     => [
                'host'  => [$this->host]
            ]

        ];
        $request = array_merge_recursive($request, $this->connectionParams, $options);

        $handler = $this->handler;
        $future = $handler($request, $this, $transport, $options);

        return $future;
    }

    /** @return string */
    public function getTransportSchema()
    {
        return $this->transportSchema;
    }

    private function wrapHandler(callable $handler, LoggerInterface $logger, LoggerInterface $tracer)
    {
        return function (array $request, Connection $connection, Transport $transport = null, $options) use ($handler, $logger, $tracer) {

            $this->lastRequest = [];
            $this->lastRequest['request'] = $request;

            // Send the request using the wrapped handler.
            $response =  Core::proxy($handler($request), function ($response) use ($connection, $transport, $logger, $tracer, $request, $options) {

                $this->lastRequest['response'] = $response;

                if (isset($response['error']) === true) {
                    if ($response['error'] instanceof ConnectException || $response['error'] instanceof RingException) {
                        $this->log->warning("Curl exception encountered.");

                        $exception = $this->getCurlRetryException($request, $response);

                        $this->logRequestFail(
                            $request['http_method'],
                            $response['effective_url'],
                            $request['body'],
                            $request['headers'],
                            $response['status'],
                            $response['body'],
                            $response['transfer_stats']['total_time'],
                            $exception
                        );

                        $host = $connection->getHost();
                        $this->log->warning("Connection $host dead.");
                        throw $exception;
                    } else {
                        // Something went seriously wrong, bail
                        $exception = new TransportException($response['error']->getMessage());
                        $this->logRequestFail(
                            $request['http_method'],
                            $response['effective_url'],
                            $request['body'],
                            $request['headers'],
                            $response['status'],
                            $response['body'],
                            $response['transfer_stats']['total_time'],
                            $exception
                        );
                        throw $exception;
                    }
                } else {

                    if (isset($response['body']) === true) {
                        $response['body'] = stream_get_contents($response['body']);
                        $this->lastRequest['response']['body'] = $response['body'];
                    }

                    if ($response['status'] >= 400 && $response['status'] < 500) {
                        $ignore = isset($request['client']['ignore']) ? $request['client']['ignore'] : [];
                        $this->process4xxError($request, $response, $ignore);
                    } elseif ($response['status'] >= 500) {
                        $ignore = isset($request['client']['ignore']) ? $request['client']['ignore'] : [];
                        $this->process5xxError($request, $response, $ignore);
                    }

                    // No error, deserialize
                    $response['body'] = $this->serializer->deserialize($response['body'], $response['transfer_stats']);
                }
                $this->logRequestSuccess(
                    $request['http_method'],
                    $response['effective_url'],
                    $request['body'],
                    $request['headers'],
                    $response['status'],
                    $response['body'],
                    $response['transfer_stats']['total_time']
                );

                return isset($request['client']['verbose']) && $request['client']['verbose'] === true ? $response : $response['body'];

            });

            return $response;
        };
    }

    /**
     * @param string $uri
     * @param array $params
     *
     * @return string
     */
    private function getURI($uri, $params)
    {
        if (isset($params) === true && !empty($params)) {
            array_walk($params, function (&$value, &$key) {
                if ($value === true) {
                    $value = 'true';
                } else if ($value === false) {
                    $value = 'false';
                }
            });

            $uri .= '?' . http_build_query($params);
        }

        if ($this->path !== null) {
            $uri = $this->path . $uri;
        }

        return $uri;
    }

    /**
     * Log a successful request
     *
     * @param string $method
     * @param string $fullURI
     * @param string $body
     * @param array  $headers
     * @param string $statusCode
     * @param string $response
     * @param string $duration
     *
     * @return void
     */
    public function logRequestSuccess($method, $fullURI, $body, $headers, $statusCode, $response, $duration)
    {
        $this->log->debug('Request Body', array($body));
        $this->log->info(
            'Request Success:',
            array(
                'method'    => $method,
                'uri'       => $fullURI,
                'headers'   => $headers,
                'HTTP code' => $statusCode,
                'duration'  => $duration,
            )
        );
        $this->log->debug('Response', array($response));

        // Build the curl command for Trace.
        $curlCommand = $this->buildCurlCommand($method, $fullURI, $body);
        $this->trace->info($curlCommand);
        $this->trace->debug(
            'Response:',
            array(
                'response'  => $response,
                'method'    => $method,
                'uri'       => $fullURI,
                'HTTP code' => $statusCode,
                'duration'  => $duration,
            )
        );
    }

    /**
     * Log a a failed request
     *
     * @param string $method
     * @param string $fullURI
     * @param string $body
     * @param array $headers
     * @param null|string $statusCode
     * @param null|string $response
     * @param string $duration
     * @param \Exception|null $exception
     *
     * @return void
     */
    public function logRequestFail($method, $fullURI, $body, $headers, $statusCode, $response, $duration, \Exception $exception)
    {
        $this->log->debug('Request Body', array($body));
        $this->log->warning(
            'Request Failure:',
            array(
                'method'    => $method,
                'uri'       => $fullURI,
                'headers'   => $headers,
                'HTTP code' => $statusCode,
                'duration'  => $duration,
                'error'     => $exception->getMessage(),
            )
        );
        $this->log->warning('Response', array($response));

        // Build the curl command for Trace.
        $curlCommand = $this->buildCurlCommand($method, $fullURI, $body);
        $this->trace->info($curlCommand);
        $this->trace->debug(
            'Response:',
            array(
                'response'  => $response,
                'method'    => $method,
                'uri'       => $fullURI,
                'HTTP code' => $statusCode,
                'duration'  => $duration,
            )
        );
    }
    /**
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * @param $request
     * @param $response
     * @return \Elasticsearch\Common\Exceptions\Curl\CouldNotConnectToHost|\Elasticsearch\Common\Exceptions\Curl\CouldNotResolveHostException|\Elasticsearch\Common\Exceptions\Curl\OperationTimeoutException|\Elasticsearch\Common\Exceptions\MaxRetriesException
     */
    protected function getCurlRetryException($request, $response)
    {
        $exception = null;
        $message = $response['error']->getMessage();
        $exception = new MaxRetriesException($message);
        switch ($response['curl']['errno']) {
            case 6:
                $exception = new CouldNotResolveHostException($message, null, $exception);
                break;
            case 7:
                $exception = new CouldNotConnectToHost($message, null, $exception);
                break;
            case 28:
                $exception = new OperationTimeoutException($message, null, $exception);
                break;
        }

        return $exception;
    }

    /**
     * Construct a string cURL command
     *
     * @param string $method HTTP method
     * @param string $uri    Full URI of request
     * @param string $body   Request body
     *
     * @return string
     */
    private function buildCurlCommand($method, $uri, $body)
    {
        $curlCommand = 'curl -X' . strtoupper($method);
        $curlCommand .= " '" . $uri . "'";

        if (isset($body) === true && $body !== '') {
            $curlCommand .= " -d '" . $body . "'";
        }

        return $curlCommand;
    }

    /**
     * @param $request
     * @param $response
     * @param $ignore
     * @throws \AlambicRestConnector\Common\Exceptions\BadRequest400Exception|\AlambicRestConnector\Common\Exceptions\Conflict409Exception|\AlambicRestConnector\Common\Exceptions\Forbidden403Exception|\AlambicRestConnector\Common\Exceptions\Missing404Exception|\AlambicRestConnector\Common\Exceptions\ScriptLangNotSupportedException|null
     */
    private function process4xxError($request, $response, $ignore)
    {
        $statusCode = $response['status'];
        $responseBody = $response['body'];

        /** @var \Exception $exception */
        $exception = $this->tryDeserialize400Error($response);

        if (array_search($response['status'], $ignore) !== false) {
            return;
        }

        switch ($statusCode) {
            case 400:
                $exception = new BadRequest400Exception($responseBody, $statusCode);
                break;
            case 403:
                $exception = new Forbidden403Exception($responseBody, $statusCode);
                break;
            case 404:
                $exception = new Missing404Exception($responseBody, $statusCode);
                break;
            case 408:
                $exception = new RequestTimeout408Exception($responseBody, $statusCode);
                break;
            case 409:
                $exception = new Conflict409Exception($responseBody, $statusCode);
                break;
        }

        $this->logRequestFail(
            $request['http_method'],
            $response['effective_url'],
            $request['body'],
            $request['headers'],
            $response['status'],
            $response['body'],
            $response['transfer_stats']['total_time'],
            $exception
        );

        throw $exception;
    }

    /**
     * @param $request
     * @param $response
     * @param $ignore
     * @throws \AlambicRestConnector\Common\Exceptions\RoutingMissingException|\AlambicRestConnector\Common\Exceptions\ServerErrorResponseException
     */
    private function process5xxError($request, $response, $ignore)
    {
        $statusCode = $response['status'];
        $responseBody = $response['body'];

        /** @var \Exception $exception */
        $exception = $this->tryDeserialize500Error($response);

        $exceptionText = "[$statusCode Server Exception] ".$exception->getMessage();
        $this->log->error($exceptionText);
        $this->log->error($exception->getTraceAsString());

        if (array_search($statusCode, $ignore) !== false) {
            return;
        }

        $this->logRequestFail(
            $request['http_method'],
            $response['effective_url'],
            $request['body'],
            $request['headers'],
            $response['status'],
            $response['body'],
            $response['transfer_stats']['total_time'],
            $exception
        );

        throw $exception;
    }

    private function tryDeserialize400Error($response)
    {
        return $this->tryDeserializeError($response, 'AlambicRestConnector\Common\Exceptions\BadRequest400Exception');
    }

    private function tryDeserialize500Error($response)
    {
        return $this->tryDeserializeError($response, 'AlambicRestConnector\Common\Exceptions\ServerErrorResponseException');
    }

    private function tryDeserializeError($response, $errorClass)
    {
        return new $errorClass($response['body'], $response['status']);
    }
}
