<?php

namespace Drupal\kcts9_tv_schedule\Form;

use DateTimeImmutable;
use DateTimeZone;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\kcts9_tv_schedule\ScheduleItemManager;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class NodeUpdateForm.
 *
 * @package Drupal\kcts9_tv_schedule\Form
 */
class NodeUpdateForm extends FormBase {

  /**
   * Schedule Item manager service.
   *
   * @var \Drupal\kcts9_tv_schedule\ScheduleItemManager
   */
  protected $scheduleItemManager;

  /**
   * Constructs a NodeUpdateForm object.
   *
   * @param \Drupal\kcts9_tv_schedule\ScheduleItemManager $schedule_item_manager
   *   Schedule Item manager service.
   */
  public function __construct(ScheduleItemManager $schedule_item_manager) {
    $this->scheduleItemManager = $schedule_item_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('kcts9_tv_schedule.schedule_item_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'kcts9_tv_schedule_node_update';
  }

  /**
   * Restricts access to the correct bundle.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node context.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   Allowed if the bundle matches.
   */
  public function checkAccess(NodeInterface $node): AccessResult {
    return AccessResult::allowedif(
      $node->bundle() === $this->scheduleItemManager::getBundleId()
    );
  }

  /**
   * Gets page title.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node context.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Updated page title with the Node title.
   */
  public function getTitle(NodeInterface $node): TranslatableMarkup {
    return $this->t('TV Schedules Service: @title', [
      '@title' => $node->getTitle(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    NodeInterface $node = NULL
  ): array {
    $cid = $node->get($this->scheduleItemManager::CID_FIELD_NAME)->value;
    /** @var \Drupal\Core\Datetime\DrupalDateTime $date */
    $date = $node->get('field_start_time')->date;
    /** @var \Drupal\taxonomy\TermInterface $channel */
    $channel = $node->get('field_channel_ref')->entity;

    // Adjust date field to match channel timezone.
    $timezone = $channel->get('field_channel_timezone')->value;
    $date->setTimezone(new DateTimeZone($timezone));

    $listing = NULL;
    if (!empty($cid) && !empty($date)) {
      $listing = $this->scheduleItemManager->getListing(
        $date->getPhpDateTime(),
        $cid
      );

      // ScheduleItemManager::addOrUpdateContent expects added "channel" and
      // "date" metadata with the listing.
      if (!empty($listing)) {
        $listing->channel = $channel;
        $listing->date = DateTimeImmutable::createFromMutable(
          $date->getPhpDateTime()
        );
      }
    }

    $form['description'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Updating will replace all existing data with
        data from the TV Schedules Service'),
    ];

    $form['cid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('TV Schedules Service ID'),
      '#value' => $cid,
      '#disabled' => TRUE,
    ];

    if (!empty($listing)) {
      $form['listing'] = [
        '#type' => 'textfield',
        '#title' => $this->t('TV Schedules Service Listing Title'),
        '#value' => $listing->title,
        '#disabled' => TRUE,
      ];
    }
    else {
      $this->messenger()->addWarning(
        $this->t('Item ID not found in TV Schedules Service API. Update
          not possible.')
      );
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => 'Update from TV Schedules Service',
      '#disabled' => empty($listing),
    ];

    $form_state->setStorage([
      'node' => $node,
      'listing' => $listing,
    ]);

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $storage = $form_state->getStorage();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $storage['node'];
    /** @var \stdClass $listing */
    $listing = $storage['listing'];

    if (!empty($listing)) {
      $this->scheduleItemManager->addOrUpdateContent($listing);

      $this->messenger()->addStatus($this->t('Node updated from TV Schedules
        Service!'));

      $form_state->setRedirect('entity.node.edit_form', [
        'node' => $node->id(),
      ]);
    }
    else {
      $this->messenger()->addWarning($this->t('Nothing found to update.'));
    }
  }

}
