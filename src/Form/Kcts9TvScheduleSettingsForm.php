<?php

namespace Drupal\kcts9_tv_schedule\Form;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\kcts9_tv_schedule\ApiClient;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class Kcts9TvScheduleSettingsForm.
 *
 * @ingroup kcts9_tv_schedule
 */
class Kcts9TvScheduleSettingsForm extends ConfigFormBase {

  /**
   * KCTS 9 TV Schedule API client.
   *
   * @var \Drupal\kcts9_tv_schedule\ApiClient
   */
  protected $apiClient;

  /**
   * Date formatting service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs a new Kcts9TvScheduleSettingsForm.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   Config factory service.
   * @param \Drupal\kcts9_tv_schedule\ApiClient $api_client
   *   KCTS 9 TV Schedule API client.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   Date formatter service.
   */
  public function __construct(
    ConfigFactory $config_factory,
    ApiClient $api_client,
    DateFormatterInterface $date_formatter
  ) {
    parent::__construct($config_factory);
    $this->apiClient = $api_client;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('kcts9_tv_schedule.api_client'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'kcts9_tv_schedule_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['kcts9_tv_schedule.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['api'] = [
      '#type' => 'details',
      '#title' => $this->t('TV Schedules Service API settings'),
      '#open' => TRUE,
    ];

    $form['api']['key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API key'),
      '#description' => $this->t('TVSS API key.'),
      '#default_value' => $this->apiClient->getApiKey(),
      '#disabled' => $this->configIsOverridden(ApiClient::CONFIG_KEY),
    ];

    $form['api']['call_sign'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API call sign'),
      '#description' => $this->t('TVSS API station call sign.'),
      '#default_value' => $this->apiClient->getCallSign(),
      '#disabled' => $this->configIsOverridden(ApiClient::CONFIG_CALL_SIGN),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('kcts9_tv_schedule.settings');

    if (!$this->configIsOverridden(ApiClient::CONFIG_KEY)) {
      $config->set(ApiClient::CONFIG_KEY, $form_state->getValue('key'));
    }

    if (!$this->configIsOverridden(ApiClient::CONFIG_CALL_SIGN)) {
      $config->set(
        ApiClient::CONFIG_CALL_SIGN,
        $form_state->getValue('call_sign')
      );
    }

    $config->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Check if a config variable is overridden by local settings.
   *
   * @param string $name
   *   Settings key to check.
   *
   * @return bool
   *   TRUE if the config is overwritten, FALSE otherwise.
   */
  protected function configIsOverridden($name) {
    $original = $this->configFactory
      ->getEditable('kcts9_tv_schedule.settings')
      ->get($name);
    $current = $this->configFactory
      ->get('kcts9_tv_schedule.settings')
      ->get($name);
    return $original != $current;
  }

}
