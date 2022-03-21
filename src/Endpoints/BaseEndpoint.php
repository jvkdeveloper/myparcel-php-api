<?php

namespace Mvdnbrk\MyParcel\Endpoints;

use Mvdnbrk\MyParcel\Client;
use Mvdnbrk\MyParcel\Exceptions\MyParcelException;

abstract class BaseEndpoint
{
    /** @var \Mvdnbrk\MyParcel\Client */
    protected $apiClient;

    public function __construct(Client $client)
    {
        $this->apiClient = $client;

        $this->boot();
    }

    protected function boot(): void
    {
    }

    protected function buildQueryString(array $filters): string
    {
        if (empty($filters)) {
            return '';
        }

        return '?'.http_build_query($filters);
    }

    /**
     * Performs a HTTP call to the API endpoint.
     *
     * @param  string  $httpMethod
     * @param  string  $apiMethod
     * @param  string|null  $httpBody
     * @param  array  $requestHeaders
     * @return string|object|null
     *
     * @throws \Mvdnbrk\MyParcel\Exceptions\MyParcelException
     */
    protected function performApiCall(string $httpMethod, string $apiMethod, ?string $httpBody = null, array $requestHeaders = [])
    {
        $response = $this->apiClient->performHttpCall($httpMethod, $apiMethod, $httpBody, $requestHeaders);

        if (collect($response->getHeader('Content-Type'))->first() == 'application/pdf') {
            return $response->getBody()->getContents();
        }

        $body = $response->getBody()->getContents();

        if (empty($body)) {
            if ($response->getStatusCode() === Client::HTTP_STATUS_NO_CONTENT) {
                return;
            }

            throw new MyParcelException('No response body found.');
        }

        $object = @json_decode($body);

        if (json_last_error() != JSON_ERROR_NONE) {
            throw new MyParcelException("Unable to decode MyParcel response: '{$body}'.");
        }

        if ($response->getStatusCode() >= 400) {
            $error = collect(collect($object->errors)->first());

            $messageBag = collect('Error executing API call');


            if($error->count() && isset($error->first()->fields) && is_array($error->first()->fields)) {
                $messageBag->push(': '.collect($error->first()->human[0]));
            }

            if ($error->has('code')) {
                $messageBag->push('('.$error->get('code').')');
            }

            if ($error->has('message')) {
                $messageBag->push(': '.$error->get('message'));
            }

            if ($error->has('human')) {
                $messageBag->push(': '.collect($error->get('human'))->first());
            }

            throw new MyParcelException($messageBag->implode(' '), $response->getStatusCode());
        }

        return $object;
    }
}
