<?php

namespace Vivait\Chronofy;

use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\FilesystemCache;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vivait\Chronofy\Exception\CacheException;
use Vivait\Chronofy\Exception\FileException;
use Vivait\Chronofy\Exception\HttpException;
use Vivait\Chronofy\Exception\TokenExpiredException;
use Vivait\Chronofy\Exception\TokenInvalidException;
use Vivait\Chronofy\Exception\UnauthorizedException;
use Vivait\Chronofy\Http\GuzzleAdapter;
use Vivait\Chronofy\Http\HttpAdapter;

class Chronofy
{
    /**
     * @var OptionsResolver
     */
    protected $optionsResolver;

    /**
     * @var HttpAdapter
     */
    protected $http;

    /**
     * @var string
     */
    protected $clientSecret;

    /**
     * @var string
     */
    protected $clientId;

    /**
     * @var array
     */
    protected $options;

    /**
     * @var Cache
     */
    private $cache;

    /**
     * @param null $clientId
     * @param null $clientSecret
     * @param array $options
     * @param HttpAdapter $http
     * @param Cache $cache
     */
    public function __construct($token, array $options = [], HttpAdapter $http = null, Cache $cache = null)
    {
        $this->optionsResolver = new OptionsResolver();
        $this->setOptions($options);

        if ($http) {
            $this->http = $http;
        } else {
            $this->http = new GuzzleAdapter();
        }

        if ($cache) {
            $this->cache = $cache;
        } else {
            $this->cache = new FilesystemCache(sys_get_temp_dir());
        }

        $this->token = $token;

        $this->http->setUrl($this->options['url']);
    }

    public function setOptions(array $options = [])
    {
        $this->configureOptions($this->optionsResolver);
        $this->options = $this->optionsResolver->resolve($options);
    }

    protected function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'token_refresh' => true,
            'cache_key' => 'token',
//            'oauth_url' => 'https://api.cronofy.com/',
            'url' => 'https://api.cronofy.com/v1/',
        ]);
    }

    /**
     * @param $resource
     * @param array $request
     * @param array $headers
     * @param int $returnType
     * @return array|mixed|string|resource
     */
    protected function get($resource, array $request = [], array $headers = [], $returnType = HttpAdapter::RETURN_TYPE_JSON)
    {
        return $this->performRequest('get', $resource, $request, $headers, $returnType);
    }

    /**
     * @param $resource
     * @param array $request
     * @param array $headers
     * @param int $returnType
     * @return array|mixed|string|resource
     */
    protected function post($resource, array $request = [], array $headers = [], $returnType = HttpAdapter::RETURN_TYPE_JSON)
    {
        return $this->performRequest('post', $resource, $request, $headers, $returnType);
    }

    /**
     * @param $method
     * @param $resource
     * @param array $request
     * @param array $headers
     * @param int $returnType
     * @return array|mixed|string|resource
     */
    protected function performRequest($method, $resource, array $request, array $headers, $returnType = HttpAdapter::RETURN_TYPE_JSON)
    {
//        if ($this->cache->contains($this->options['cache_key'])) {
//            $accessToken = $this->cache->fetch($this->options['cache_key']);
//        } else {
//            $accessToken = $this->authorize();
//            $this->cache->save($this->options['cache_key'], $accessToken);
//        }

        try {
            $headers['Authorization'] = 'Bearer '. $this->token;

            return $this->http->$method($resource, $request, $headers, $returnType);

        } catch (UnauthorizedException $e) {

            if(!$this->cache->delete($this->options['cache_key'])){
                throw new CacheException('Could not delete the key in the cache. Do you have permission?');
            }

            if ($e instanceof TokenExpiredException || $e instanceof TokenInvalidException) {
                if ($this->options['token_refresh']) {
                    return $this->$method($resource, $request, $headers, $returnType);
                }
            }

            throw $e;
        }
    }

    /**
     * @return string
     */
    public function authorize()
    {
        $this->http->setUrl($this->options['oauth_url']);

        $response = $this->http->post(
            'oauth/token', [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'authorization_code'
            ],
            [],
            HttpAdapter::RETURN_TYPE_JSON
        );

        $code = $this->http->getResponseCode();

        $this->http->setUrl($this->options['url']);

        if ($code == 200 && array_key_exists('access_token', $response)) {
            return $response['access_token'];
        } else {
            throw new HttpException("No access token was provided in the response", $code);
        }
    }

    /**
     * @return array
     */
    public function getCalendars()
    {
        return $this->get('calendars');
    }

    /**
     * @param $id
     * @return array
     */
    public function getCalendar($id)
    {
        return $this->get('calendars/' . $id);
    }

    public function getEvents(\DateTime $from = null, \DateTime $to = null, $tzid = null)
    {
        $request = [
            'tzid' => $tzid ?: date_default_timezone_get()
        ];

        if ($from) {
            $request['from'] = $from->format(\DateTime::ISO8601);
        }

        if ($to) {
            $request['to'] = $to->format(\DateTime::ISO8601);
        }

        return $this->get('events', $request);
    }

    public function getHttpAdapter()
    {
        return $this->http;
    }

    /**
     * @param $stream
     * @return \SplFileObject
     */
    protected function handleFileResource($stream)
    {
        if (!is_resource($stream) && get_resource_type($stream) != 'stream') {
            throw new FileException();
        } else {
            return $stream;
        }
    }

    /**
     * @param string $clientSecret
     */
    public function setClientSecret($clientSecret)
    {
        $this->clientSecret = $clientSecret;
    }

    /**
     * @param string $clientId
     */
    public function setClientId($clientId)
    {
        $this->clientId = $clientId;
    }
}
