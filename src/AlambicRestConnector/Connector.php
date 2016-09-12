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

        $host = $baseConfig["baseUrl"] . "/" . $this->injectArgsInSegment($payload["args"], $configs["segment"]);

        $client = ClientBuilder::create()->setHost($host)->build();

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

        if (isset($start) && isset($configs["startFieldName"])) {
            $args[$configs["startFieldName"]] = $start;
        }

        if (isset($limit) && isset($configs["limitFieldName"])) {
            $args[$configs["limitFieldName"]] = $limit;
        }

        if (isset($orderBy) && isset($configs["orderFieldName"])) {
            $args[$configs["orderFieldName"]] = $orderBy;
        }

        if (isset($orderByDirection) && isset($configs["orderDirectionFieldName"])) {
            $args[$configs["orderDirectionFieldName"]] = $orderBy;
        }

        $result = [$client->run($args)];
        if (isset($configs["resultsPath"])) {
            $payload["response"]=$result[0][$configs["resultsPath"]];
        } else {
            $payload["response"]=$result[0];
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
