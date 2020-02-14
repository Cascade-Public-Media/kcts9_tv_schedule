<?php

namespace Drupal\kcts9_tv_schedule\Plugin\QueueWorker;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\kcts9_tv_schedule\ScheduleItemManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Removes old Schedule Items from the database.
 *
 * Queue items are added by cron processing.
 *
 * @QueueWorker(
 *   id = "kcts9_tv_schedule.queue.schedule_pruner",
 *   title = @Translation("Schedule items pruner"),
 *   cron = {"time" = 60}
 * )
 *
 * @see kcts9_tv_schedule_cron()
 * @see \Drupal\Core\Annotation\QueueWorker
 * @see \Drupal\Core\Annotation\Translation
 */
class SchedulePrunerQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * KCTS 9 TV Schedule logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  private $logger;

  /**
   * Show manager.
   *
   * @var \Drupal\kcts9_tv_schedule\ScheduleItemManager
   */
  private $scheduleItemManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration,
    $plugin_id,
    $plugin_definition,
    LoggerChannelInterface $logger,
    ScheduleItemManager $schedule_item_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->logger = $logger;
    $this->scheduleItemManager = $schedule_item_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.channel.kcts9_tv_schedule'),
      $container->get('kcts9_tv_schedule.schedule_item_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($nids): void {
    $storage = $this->scheduleItemManager->getStorage();
    $nodes = $storage->loadMultiple($nids);
    $storage->delete($nodes);
  }

}
