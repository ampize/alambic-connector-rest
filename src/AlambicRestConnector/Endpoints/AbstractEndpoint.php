<?php

namespace AlambicRestConnector\Endpoints;

use AlambicRestConnector\Common\Exceptions\UnexpectedValueException;
use AlambicRestConnector\Serializers\SerializerInterface;
use AlambicRestConnector\Transport;
use Exception;
use GuzzleHttp\Ring\Future\FutureArrayInterface;

/**
 * Class AbstractEndpoint
 */
abstract class AbstractEndpoint
{
    /** @var array  */
    protected $params = array();

    /** @var  string|int */
    protected $id = null;

    /** @var  string */
    protected $method = null;

    /** @var  array */
    protected $body = null;

    /** @var array  */
    private $options = [];

    /** @var  SerializerInterface */
    protected $serializer;

    /**
     * @return string[]
     */
    abstract public function getParamWhitelist();

    /**
     * @return string
     */
    abstract public function getURI();

    /**
     * @return string
     */
    abstract public function getMethod();


    /**
     * Set the parameters for this endpoint
     *
     * @param string[] $params Array of parameters
     * @return $this
     */
    public function setParams($params)
    {
        if (is_object($params) === true) {
            $params = (array) $params;
        }

        //$this->checkUserParams($params);
        $params = $this->convertCustom($params);
        $this->extractOptions($params);
        $this->params = $this->convertArraysToStrings($params);

        return $this;
    }

    /**
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @param int|string $docID
     *
     * @return $this
     */
    public function setID($docID)
    {
        if ($docID === null) {
            return $this;
        }

        $this->id = urlencode($docID);

        return $this;
    }

    /**
     * @return array
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @param array $params
     *
     * @throws \AlambicRestConnector\Common\Exceptions\UnexpectedValueException
     */
    private function checkUserParams($params)
    {
        if (isset($params) !== true) {
            return; //no params, just return.
        }

        $whitelist = array_merge($this->getParamWhitelist(), array('client', 'custom', 'filter_path'));

        foreach ($params as $key => $value) {
            if (array_search($key, $whitelist) === false) {
                throw new UnexpectedValueException(sprintf(
                    '"%s" is not a valid parameter. Allowed parameters are: "%s"',
                    $key,
                    implode('", "', $whitelist)
                ));
            }
        }
    }

    /**
     * @param $params       Note: this is passed by-reference!
     */
    private function extractOptions(&$params)
    {
        // Extract out client options, then start transforming
        if (isset($params['client']) === true) {
            $this->options['client'] = $params['client'];
            unset($params['client']);
        }

        $ignore = isset($this->options['client']['ignore']) ? $this->options['client']['ignore'] : null;
        if (isset($ignore) === true) {
            if (is_string($ignore)) {
                $this->options['client']['ignore'] = explode(",", $ignore);
            } elseif (is_array($ignore)) {
                $this->options['client']['ignore'] = $ignore;
            } else {
                $this->options['client']['ignore'] = [$ignore];
            }
        }
    }

    private function convertCustom($params)
    {
        if (isset($params['custom']) === true) {
            foreach ($params['custom'] as $k => $v) {
                $params[$k] = $v;
            }
            unset($params['custom']);
        }

        return $params;
    }

    private function convertArraysToStrings($params)
    {
        foreach ($params as $key => &$value) {
            if (!($key === 'client' || $key == 'custom') && is_array($value) === true) {
                if ($this->isNestedArray($value) !== true) {
                    $value = implode(",", $value);
                }
            }
        }

        return $params;
    }

    private function isNestedArray($a)
    {
        foreach ($a as $v) {
            if (is_array($v)) {
                return true;
            }
        }

        return false;
    }
}
