<?php

namespace AlambicRestConnector\Common\Exceptions\Curl;

use AlambicRestConnector\Common\Exceptions\AlambicRestConnectorException;
use AlambicRestConnector\Common\Exceptions\TransportException;

/**
 * Class OperationTimeoutException
  */
class OperationTimeoutException extends TransportException implements AlambicRestConnectorException
{
}
