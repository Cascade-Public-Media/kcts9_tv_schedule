<?php

namespace Drupal\kcts9_tv_schedule;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Exception;

/**
 * Class ApiContentManagerBase.
 *
 * @package Drupal\kcts9_tv_schedule
 */
abstract class ApiContentManagerBase implements ApiContentManagerInterface {
  use StringTranslationTrait;

  /**
   * The Drupal field name for TVSS API item CIDs.
   */
  const CID_FIELD_NAME = 'field_remote_content_id';

  /**
   * API Client service.
   *
   * @var \Drupal\kcts9_tv_schedule\ApiClient
   *
   * @see \OpenPublicMedia\PbsTvSchedulesService\Client
   */
  protected $client;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * KCTS 9 TV Schedule logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Queue service.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * State interface.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * ApiContentManagerBase constructor.
   *
   * @param \Drupal\kcts9_tv_schedule\ApiClient $client
   *   PBS TVSS API client service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   Logger channel service.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   Queue factory service.
   * @param \Drupal\Core\State\StateInterface $state
   *   State service.
   */
  public function __construct(
    ApiClient $client,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelInterface $logger,
    QueueFactory $queue_factory,
    StateInterface $state
  ) {
    $this->client = $client;
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
    $this->queueFactory = $queue_factory;
    $this->state = $state;
  }

  /**
   * Gets the storage for the local content type.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   Storage interface.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getStorage(): EntityStorageInterface {
    return $this->entityTypeManager->getStorage($this->getEntityTypeId());
  }

  /**
   * Gets the definition for the local content type.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface|null
   *   Entity type interface or NULL if not found.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getDefinition(): ?EntityTypeInterface {
    return $this->entityTypeManager->getDefinition($this->getEntityTypeId());
  }

  /**
   * {@inheritdoc}
   */
  public function getContentByCid(string $cid): ?ContentEntityInterface {
    try {
      $items = $this->getContentByProperties([self::CID_FIELD_NAME => $cid]);
    }
    catch (Exception $e) {
      // Let NULL fall through.
      $items = [];
    }

    $item = NULL;
    if (!empty($items)) {
      $item = reset($items);
      if (count($items) > 1) {
        $this->logger->error($this->t('Multiple items found for TVSS CID {cid}.
          {label} IDs found: {id_list}. Using {id}.', [
            'cid' => $cid,
            'label' => $item->getEntityType()->getLabel(),
            'id_list' => implode(', ', array_keys($items)),
            'tid' => $item->id(),
          ]));
      }
    }

    return $item;
  }

  /**
   * Gets content using provided properties.
   *
   * @param array $properties
   *   Conditions used to query for content.
   * @param string|null $sort_by
   *   Field/property to sort by.
   * @param string $sort_dir
   *   Sort direction (should be "ASC" or "DESC").
   * @param int|null $range_start
   *   Range start.
   * @param int|null $range_length
   *   Range length.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   Content matching the properties.
   */
  public function getContentByProperties(
    array $properties,
    string $sort_by = NULL,
    string $sort_dir = 'ASC',
    int $range_start = NULL,
    int $range_length = NULL
  ): array {
    try {
      $definition = $this->entityTypeManager->getDefinition($this->getEntityTypeId());
      $storage = $this->entityTypeManager->getStorage($this->getEntityTypeId());
    }
    catch (Exception $e) {
      $this->logger->critical($this->t('Invalid entity configuration for API
        content detected. Unable to query content.'));
      watchdog_exception('kcts9_tv_schedule', $e);
      return [];
    }

    $query = $storage->getQuery();
    $query->condition($definition->getKey('bundle'), $this->getBundleId());
    foreach ($properties as $property => $value) {
      $query->condition($property, $value);
    }

    if (!empty($sort_by)) {
      $query->sort($sort_by, $sort_dir);
    }
    $query->range($range_start, $range_length);

    $ids = $query->execute();
    $storage->loadMultiple($ids);

    return $storage->loadMultiple($ids);
  }

}
