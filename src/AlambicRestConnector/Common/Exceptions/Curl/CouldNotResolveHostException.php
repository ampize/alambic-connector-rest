<?php

namespace AlambicRestConnector\Common\Exceptions\Curl;

use AlambicRestConnector\Common\Exceptions\AlambicRestConnectorException;
use AlambicRestConnector\Common\Exceptions\TransportException;

/**
 * Class CouldNotResolveHostException
  */
class CouldNotResolveHostException extends TransportException implements AlambicRestConnectorException
{
}
