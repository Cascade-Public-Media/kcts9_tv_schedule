<?php

namespace Drupal\kcts9_tv_schedule;

use Drupal\Core\Config\ConfigFactoryInterface;
use OpenPublicMedia\PbsTvSchedulesService\Client;

/**
 * API client class for the PBS TV Schedules Service (TVSS) API.
 *
 * @package Drupal\kcts9_tv_schedule
 */
class ApiClient extends Client {

  /**
   * Config key for the API key.
   */
  const CONFIG_KEY = 'api.key';

  /**
   * Config key for the API call sign.
   */
  const CONFIG_CALL_SIGN = 'api.call_sign';

  /**
   * API key.
   *
   * @var string
   */
  private $key;

  /**
   * API call sign.
   *
   * @var string
   */
  public $callSign;

  /**
   * API base endpoint.
   *
   * @var string
   */
  public $baseUri;

  /**
   * KCTS 9 TV Schedule immutable config object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * ApiClient constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory service.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->config = $config_factory->get('kcts9_tv_schedule.settings');
    $this->key = $this->config->get(self::CONFIG_KEY);
    $this->callSign = $this->config->get(self::CONFIG_CALL_SIGN);
    parent::__construct($this->key, $this->callSign);
  }

  /**
   * Gets the API client key.
   *
   * @return string|null
   *   TVSS API key setting or NULL if none is set.
   */
  public function getApiKey(): ?string {
    return $this->key;
  }

  /**
   * Gets the API client call sign.
   *
   * @return string|null
   *   TVSS API call sign setting or NULL if none is set.
   */
  public function getCallSign(): ?string {
    return $this->callSign;
  }

}
