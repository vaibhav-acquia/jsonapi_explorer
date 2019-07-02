<?php

namespace Drupal\jsonapi_explorer\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DrupalKernelInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConfigController extends ConfigFormBase {

  const EXPLORER_ORIGIN = 'https://jsonapi.dev/';

  const CONFIG_ID = 'jsonapi_explorer.settings';

  /**
   * @var \Drupal\Core\DrupalKernelInterface
   */
  protected $drupalKernel;

  protected $httpClient;

  /**
   * @var array
   */
  protected $corsConfig;

  public function __construct(ConfigFactoryInterface $config_factory, DrupalKernelInterface $kernel, Client $http_client, array $cors_config) {
    parent::__construct($config_factory);
    $this->drupalKernel = $kernel;
    $this->httpClient = $http_client;
    $this->corsConfig = $cors_config;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('kernel'),
      $container->get('http_client'),
      $container->getParameter('cors.config')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [static::CONFIG_ID];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'jsonapi_explorer_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::CONFIG_ID);

    if ($this->corsConfig['enabled']) {
      $this->messenger()->addStatus($this->t('It looks like CORS is already configured, <a href=":href" target="_blank">go explore!</a>', [
        ':href' => Url::fromRoute('jsonapi_explorer.app.index', [], [
          'query' => [
            'location' => 'http://drupal.test/',
          ],
        ])->toString(),
      ]));
    }

    // Only make this configurable if it is not already enabled.
    if (!$this->corsConfig['supportsCredentials']) {
      $form['supports_credentials'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Allow authenticated requests from other origins.'),
        '#description' => $this->t('Enabling this will allow JavaScript from <em>any</em> of your allowed origins to send authenticated requests.'),
        '#default_value' => $config->get('supports_credentials'),
      ];
    }

    $form['custom_instance'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use a custom JSON:API Explorer origin.'),
      '#description' => $this->t('Enable this if you host your own JSON:API Explorer instance or are running one locally.'),
      '#default_value' => $config->get('custom_instance'),
    ];

    $form['origin'] = [
      '#type' => 'textfield',
      '#description' => $this->t('Location of your JSON:API Explorer instance.'),
      '#default_value' => $config->get('origin'),
      '#states' => [
        'enabled' => [
          ':input[name="custom_instance"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config(static::CONFIG_ID);
    foreach ($form_state->getValues() as $key => $new_value) {
      if (in_array($key, ['supports_credentials', 'custom_instance', 'origin'])) {
        $old_value = $config->get($key);
        if ($old_value !== $new_value) {
          $this->drupalKernel->invalidateContainer();
        }
        $config->set($key, $new_value)->save();
      }
    }
    $config->save();
    parent::submitForm($form, $form_state);
  }

  public function app() {
    return $this->proxy('index.html');
  }

  public function proxy($file) {
    $config = $this->config(static::CONFIG_ID);
    $has_custom_origin = $config->get('custom_instance') && $config->get('origin');
    $explorer_origin = $has_custom_origin ? $config->get('origin') : static::EXPLORER_ORIGIN;
    if (!empty($file)) {
      $explorer_origin .= $file;
    }
    $response = $this->httpClient->get($explorer_origin);
    return StreamedResponse::create(function () use ($response) {
      $body = $response->getBody();
      while (!$body->eof()) {
        echo $body->read(1024);
      }
    }, $response->getStatusCode(), $response->getHeaders());
  }

}
