<?php

namespace Szymsza\PhpPplCreatePackageLabelApi;

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use Psr\Http\Message\ResponseInterface;

class PPL {
    const ACCESS_TOKEN_URL_DEV = 'https://api-dev.dhl.com/ecs/ppl/myapi2/login/getAccessToken';

    const ACCESS_TOKEN_URL_PROD = 'https://api.dhl.com/ecs/ppl/myapi2/login/getAccessToken';

    const API_ENDPOINT_DEV = 'https://api-dev.dhl.com/ecs/ppl/myapi2/';

    const API_ENDPOINT_PROD = 'https://api.dhl.com/ecs/ppl/myapi2/';

    protected GenericProvider $provider;

    protected bool $isDevelopment;

    protected string $token;

    /**
     * @throws IdentityProviderException
     */
    public function __construct(string $clientId, string $clientSecret, bool $isDevelopment = false) {
        $this->isDevelopment = $isDevelopment;
        $this->provider = new GenericProvider([
            'clientId'                => $clientId,
            'clientSecret'            => $clientSecret,
            'redirectUri'             => 'NOT_NECESSARY',
            'urlAuthorize'            => 'NOT_NECESSARY',
            'urlAccessToken'          => $this->getAccessTokenUrl(),
            'urlResourceOwnerDetails' => 'NOT_NECESSARY'
        ]);
        $this->token = $this->getToken();
    }

    protected function getAccessTokenUrl(): string {
        if ($this->isDevelopment) {
            return self::ACCESS_TOKEN_URL_DEV;
        }
        return self::ACCESS_TOKEN_URL_PROD;
    }

    protected function getApiEndpointUrl(): string {
        if ($this->isDevelopment) {
            return self::API_ENDPOINT_DEV;
        }
        return self::API_ENDPOINT_PROD;
    }

    /**
     * @throws IdentityProviderException
     * @return string
     */
    protected function getToken(): string {
        return $this->provider->getAccessToken('client_credentials');
    }

    /**
     * If the given URL belongs to the API endpoint, only the relative path is returned.
     *
     * @param string $absoluteUrl
     * @return string
     */
    public function relativizeUrl(string $absoluteUrl): string {
        if (substr($absoluteUrl, 0, strlen($this->getApiEndpointUrl())) === $this->getApiEndpointUrl()) {
            return substr($absoluteUrl, strlen($this->getApiEndpointUrl()));
        }

        return $absoluteUrl;
    }

    /**
     *  Sends an authenticated request to the API and returns a response instance.
     *
     *  WARNING: This method does not attempt to catch exceptions caused by HTTP
     *  errors! It is recommended to wrap this method in a try/catch block.
     *
     * @param string $path
     * @param string $method
     * @param array $data
     * @return ResponseInterface
     */
    public function request(string $path, string $method = 'get', array $data = []): ResponseInterface {
        if ($data != []) {
            $options = [
                "headers" => [
                    "content-type" => "application/json-patch+json"
                ],
                "body" => json_encode($data)
            ];
        } else {
            $options = [];
        }

        $request = $this->provider->getAuthenticatedRequest($method, $this->getApiEndpointUrl() . $path, $this->token, $options);
        return $this->provider->getResponse($request);
    }

    /**
     * Sends an authenticated request to the API (see description above) and returns
     * decoded JSON object.
     *
     * @param string $path
     * @param string $method
     * @param array $data
     * @return array|object|null
     */
    public function requestJson(string $path, string $method = 'get', array $data = []) {
        return json_decode($this->request($path, $method, $data)->getBody()->getContents());
    }

    /**
     * Sends an authenticated request to the API (see description above) and returns
     * a single header of the response.
     * If the header value is an API location, the URL is relativized.
     *
     * @param string $path
     * @param string $method
     * @param array $data
     * @param string $header
     * @return string
     */
    public function requestHeader(string $path, string $method = 'get', array $data = [], string $header = 'Location'): string {
        $result = $this->request($path, $method, $data)->getHeader($header)[0];

        if ($header == 'Location') {
            return $this->relativizeUrl($result);
        }

        return $result;
    }

    /**
     * Calls the API to get Swaggger JSON describing the available API endpoints.
     * You can view this JSON by pasting it, e.g., to https://editor.swagger.io/
     *
     * @return string
     */
    public function getSwagger(): string {
        return $this->request('swagger/v1/swagger.json')->getBody()->getContents();
    }

    /**
     * Calls the API to get basic information, such as the API version or the current time.
     * Useful to test your connection.
     *
     * @return object
     */
    public function versionInformation(): object {
        return $this->requestJson('info');
    }
}
