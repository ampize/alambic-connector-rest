<?php

namespace AlambicRestConnector;

use \Exception;

class Connector
{
    public function __invoke($payload=[])
    {
        if (isset($payload['response'])) {
            return $payload;
        }

        $configs=isset($payload["configs"]) ? $payload["configs"] : [];

        $baseConfig=isset($payload["connectorBaseConfig"]) ? $payload["connectorBaseConfig"] : [];

        if (empty($baseConfig["baseUrl"])) {
            throw new Exception('Base url required');
        }

        if (!isset($configs["segment"])) {
            throw new Exception('Endpoint segment required');
        }

        if (isset($baseConfig["args"])) {
            $payload["args"] = array_merge($baseConfig["args"],$payload["args"]);
        }

        if (isset($baseConfig["authQueryParams"])) {
            $authQueryParams = [];
            foreach ($baseConfig["authQueryParams"] as $param) {
                $authQueryParams[$param["property"]] = $param["value"];
            }
            $payload["args"] = array_merge($authQueryParams,$payload["args"]);
        }

        if (isset($baseConfig["authUsername"]) && isset($baseConfig["authPassword"])) {
            $protocol = parse_url($baseConfig["baseUrl"], PHP_URL_SCHEME);
            switch ($protocol) {
                case "http":
                    $baseConfig["baseUrl"] = "http://".$baseConfig["authUsername"].":".$baseConfig["authPassword"]."@".substr($baseConfig["baseUrl"],7);
                    break;
                case "https":
                    $baseConfig["baseUrl"] = "https://".$baseConfig["authUsername"].":".$baseConfig["authPassword"]."@".substr($baseConfig["baseUrl"],8);
                    break;
            }
        }
        
        $host = $baseConfig["baseUrl"] . "/" . $this->injectArgsInSegment($payload["args"], $configs["segment"]);

        $authHeaderParams = isset($baseConfig["authHeaderParams"]) ? $baseConfig["authHeaderParams"] : null;

        $client = ClientBuilder::create()->setHost($host)->setAuthHeaderParams($authHeaderParams)->build();

        return $payload["isMutation"] ? $this->execute($payload, $client) : $this->resolve($payload, $client, $configs);
    }

    public function resolve($payload=[],$client,$configs){

        $args=isset($payload["args"]) ? $payload["args"] : [];

        $args['multivalued']=isset($payload["multivalued"]) ? $payload["multivalued"] : false;
        if (!empty($payload['pipelineParams']['orderBy'])) {
            $direction = !empty($payload['pipelineParams']['orderByDirection']) && ($payload['pipelineParams']['orderByDirection'] == -'desc') ? "revsortby" : "sortby";
            $args[$direction]=$payload['pipelineParams']['orderBy'];
        }
        $start = !empty($payload['pipelineParams']['start']) ? $payload['pipelineParams']['start'] : null;
        $limit = !empty($payload['pipelineParams']['limit']) ? $payload['pipelineParams']['limit'] : null;
        $orderBy = !empty($payload['pipelineParams']['orderBy']) ? $payload['pipelineParams']['orderBy'] : null;
        $orderByDirection = !empty($payload['pipelineParams']['orderByDirection']) ? $payload['pipelineParams']['orderByDirection'] : null;

        if (isset($start) && isset($configs["startFieldName"])) {
            $args[$configs["startFieldName"]] = $start;
        }

        if (isset($limit) && isset($configs["limitFieldName"])) {
            $args[$configs["limitFieldName"]] = $limit;
        }

        if (isset($orderBy) && isset($configs["orderByFieldName"])) {
            $args[$configs["orderByFieldName"]] = $orderBy;
        }

        if (isset($orderByDirection) && isset($configs["orderByDirectionFieldName"])) {
            switch ($orderByDirection) {
                case "ASC":
                    $order = isset($configs["ascendantValue"]) ? $configs["ascendantValue"] : $orderByDirection;
                    break;
                case "DESC":
                    $order = isset($configs["descendantValue"]) ? $configs["descendantValue"] : $orderByDirection;
                    break;
            }
            $args[$configs["orderByDirectionFieldName"]] = $order;
        }

        $result = [$client->run($args)];

        $payload["response"]=$result[0];
        if (isset($configs["resultsPath"])) {
            $paths = explode('.',$configs["resultsPath"]);
            foreach($paths as $path) {
                $payload["response"]=$payload["response"][$path];
            }
        }
        return $payload;
    }

    public function execute($payload=[],$diffbot){
        throw new Exception('WIP');
    }

    private function injectArgsInSegment(&$args, $segment) {
        foreach($args as $key => $value) {
            $segment = str_replace("{".$key."}", $value, $segment, $count);
            if ($count>0) unset($args[$key]);
        }
        return $segment;
    }

}
