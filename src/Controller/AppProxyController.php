<?php

namespace Drupal\jsonapi_explorer\Controller;

use Drupal\Core\Controller\ControllerBase;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AppProxyController extends ControllerBase {

  /**
   * The URL of the remo
   *
   * @var string
   */
  protected $explorerUrl = 'https://zrpnr.github.io/jsonapi_explorer';

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * @var array
   */
  protected $corsConfig;

  public function __construct(Client $http_client) {
    $this->httpClient = $http_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('http_client'));
  }

  public function app() {
    return $this->proxy('index.html');
  }

  public function proxy($file) {
    $proxy_url = "{$this->explorerUrl}/$file";
    $response = $this->httpClient->get($proxy_url);
    return StreamedResponse::create(function () use ($response) {
      $body = $response->getBody();
      while (!$body->eof()) {
        echo $body->read(1024);
      }
    }, $response->getStatusCode(), $response->getHeaders());
  }

}
