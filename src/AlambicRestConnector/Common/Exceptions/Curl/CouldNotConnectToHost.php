<?php

namespace AlambicRestConnector\\Common\Exceptions\Curl;

use AlambicRestConnector\\Common\Exceptions\AlambicRestConnector\Exception;
use AlambicRestConnector\Common\Exceptions\TransportException;

/**
 * Class CouldNotConnectToHost
 */
class CouldNotConnectToHost extends TransportException implements AlambicRestConnector\Exception
{
}
