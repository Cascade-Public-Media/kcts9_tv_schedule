<?php

namespace Drupal\kcts9_tv_schedule\Form;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\kcts9_tv_schedule\ScheduleItemManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ScheduleItemsUpdateForm.
 *
 * @package Drupal\kcts9_tv_schedule\Form
 */
class ScheduleItemsUpdateForm extends FormBase {
  /**
   * Date formatting service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Schedule Item manager.
   *
   * @var \Drupal\kcts9_tv_schedule\ScheduleItemManager
   */
  protected $scheduleItemManager;

  /**
   * Constructs a ShowsQueueForm object.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   Date formatter service.
   * @param \Drupal\kcts9_tv_schedule\ScheduleItemManager $schedule_item_manager
   *   Schedule Item manager.
   */
  public function __construct(
    DateFormatterInterface $date_formatter,
    ScheduleItemManager $schedule_item_manager
  ) {
    $this->dateFormatter = $date_formatter;
    $this->scheduleItemManager = $schedule_item_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('date.formatter'),
      $container->get('kcts9_tv_schedule.schedule_item_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'kcts9_tv_schedule_schedule_items_update_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['date_update'] = [
      '#type' => 'details',
      '#title' => $this->t('Update single date'),
      '#description' => $this->t('This process will update all Schedule Item
        nodes for the specified date from the TV Schedules Service.'),
      '#open' => TRUE,
    ];

    $date_min = new DrupalDateTime();
    $date_max = (clone $date_min)->add(new \DateInterval('P16D'));
    $form['date_update']['date'] = [
      '#type' => 'date',
      '#title' => $this->t('Date'),
      '#attributes' => [
        'type' => 'date',
        'min' => $date_min->format('Y-m-d'),
        'max' => $date_max->format('Y-m-d'),
      ],
    ];

    $form['date_update']['actions']['#type'] = 'actions';
    $form['date_update']['actions']['update_date'] = [
      '#type' => 'submit',
      '#name' => 'update_date',
      '#value' => $this->t('Update selected date'),
      '#button_type' => 'primary',
    ];

    $form['all_update'] = [
      '#type' => 'details',
      '#title' => $this->t('Update all from today'),
      '#description' => $this->t('This process will find and update as
        many Schedule Item nodes as possible from the TV Schedules Service.
        Generally this is the equivalent of about two weeks. Running this 
        process is <strong>not recommended</strong> as it may cause an execution
        time out. Proceed with caution.'),
      '#open' => FALSE,
    ];

    $form['all_update']['actions']['#type'] = 'actions';
    $form['all_update']['actions']['update_all'] = [
      '#type' => 'submit',
      '#name' => 'update_all',
      '#value' => $this->t('Update all from today'),
      '#button_type' => 'secondary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $trigger = $form_state->getTriggeringElement();

    switch ($trigger['#name']) {
      case 'update_date':
        if ($form_state->isValueEmpty('date')) {
          $form_state->setErrorByName('date', $this->t('The date 
            field is required.'));
        }
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();

    switch ($trigger['#name']) {
      case 'update_date':
        $this->scheduleItemManager->updateByDate($form_state->getValue('date'));
        $this->messenger()->addStatus($this->t('@date schedule updated!', [
          '@date' => $form_state->getValue('date'),
        ]));
        break;

      case 'update_all':
        $this->scheduleItemManager->updateFromDate(new DrupalDateTime());
        $this->messenger()->addStatus($this->t('All schedule data updated!'));
        break;
    }
  }

}
