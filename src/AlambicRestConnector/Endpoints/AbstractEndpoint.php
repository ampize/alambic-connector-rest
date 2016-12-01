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
        $this->params = $params;

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
