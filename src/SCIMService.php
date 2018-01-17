<?php
namespace KWRI\LaravelGluuWrapper;

use GuzzleHttp\Client as HttpClient;

class SCIMService {

    private $client;
    private $scimToken;
    private $baseSCIMEndpoint;

    public function __construct() {
        $this->client = new HttpClient();
        $this->scimToken = config('gluu-wrapper.scim_token');
        $this->baseSCIMEndpoint = config('gluu-wrapper.scim_endpoint');
    }

    public function browseUsers($searchParams = []) {
        $searchParams["access_token"] = $this->scimToken;
        $body = $this->client->request("GET", $this->makeRoute("Users"), [
            "query" => $searchParams,
        ])->getBody();
        return json_decode($body, true);
    }

    public function hasSCIMToken() {
        return trim($this->scimToken) != "";
    }


    public function searchByKwuid($kwuid) {

        $searchParams = [
            "filter" => 'PersistentId eq "'.$kwuid.'"',
            "count" => 1,
        ];

        $users = $this->browseUsers($searchParams);
        if (!isset($users["totalResults"])) {
            return false;
        }
        return $users;
    }


    private function makeRoute($path) {
        return $this->baseSCIMEndpoint."/".$path;
    }
}
