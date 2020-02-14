<?php

namespace Drupal\kcts9_tv_schedule;

use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\StateInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\kcts9_media_manager\ShowManager;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Exception;
use stdClass;

/**
 * Class ScheduleItemManager.
 *
 * @package Drupal\kcts9_tv_schedule
 */
class ScheduleItemManager extends ApiContentManagerBase {

  /**
   * State key for the last time the Schedule Pruner queue was updated.
   */
  const LAST_PRUNE_KEY = 'kcts9_tv_schedule.last_prune';

  /**
   * State key for the last time schedule items were fully synced.
   */
  const LAST_FULL_UPDATE = 'kcts9_tv_schedule.last_full_update';

  /**
   * State key for the last time the current day's schedule items were synced.
   */
  const LAST_DAY_UPDATE = 'kcts9_tv_schedule.last_day_update';

  /**
   * Channel Manager service.
   *
   * @var \Drupal\kcts9_tv_schedule\ChannelManager
   */
  protected $channelManager;

  /**
   * Media Manager - Show Manager service.
   *
   * @var \Drupal\kcts9_media_manager\ShowManager
   */
  protected $showManager;

  /**
   * ApiContentManagerBase constructor.
   *
   * @param \Drupal\kcts9_tv_schedule\ApiClient $client
   *   PBS TVSS API client service.
   * @param \Drupal\kcts9_tv_schedule\ChannelManager $channel_manager
   *   Channel Manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   Logger channel service.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   Queue factory service.
   * @param \Drupal\kcts9_media_manager\ShowManager $show_manager
   *   Media Manager - Show Manager service.
   * @param \Drupal\Core\State\StateInterface $state
   *   State service.
   */
  public function __construct(
    ApiClient $client,
    ChannelManager $channel_manager,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelInterface $logger,
    QueueFactory $queue_factory,
    ShowManager $show_manager,
    StateInterface $state
  ) {
    parent::__construct(
      $client,
      $entity_type_manager,
      $logger,
      $queue_factory,
      $state
    );
    $this->channelManager = $channel_manager;
    $this->showManager = $show_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getEntityTypeId(): string {
    return 'node';
  }

  /**
   * {@inheritdoc}
   */
  public static function getBundleId(): string {
    return 'schedule_item';
  }

  /**
   * Gets the Channel manager service.
   *
   * @return \Drupal\kcts9_tv_schedule\ChannelManager
   *   Channel manager service.
   */
  public function getChannelManager(): ChannelManager {
    return $this->channelManager;
  }

  /**
   * Gets the datetime of the last pruning pass to delete old schedule items.
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime
   *   Datetime of last run or yesterday if nothing found in state.
   *
   * @throws \Exception
   */
  public function getLastPruneTime(): DrupalDateTime {
    $last_update = $this->state->get(self::LAST_PRUNE_KEY);
    if (empty($last_update)) {
      $last_update = new DrupalDateTime('yesterday');
    }
    return $last_update;
  }

  /**
   * Sets the last schedule item pruning pass in state.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $time
   *   Datetime to update with.
   *
   * @see \Drupal\kcts9_tv_schedule\ScheduleItemManager::getLastPruneTime()
   */
  public function setLastPruneTime(DrupalDateTime $time): void {
    $this->state->set(self::LAST_PRUNE_KEY, $time);
  }

  /**
   * Gets the datetime of the last schedule item update for the current day.
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime
   *   Datetime of last run or yesterday if nothing found in state.
   *
   * @throws \Exception
   */
  public function getLastDayUpdate(): DrupalDateTime {
    $last_update = $this->state->get(self::LAST_DAY_UPDATE);
    if (empty($last_update)) {
      $last_update = new DrupalDateTime('yesterday');
    }
    return $last_update;
  }

  /**
   * Sets the datetime of last schedule item update for the current day.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $time
   *   Datetime to update with.
   *
   * @see \Drupal\kcts9_tv_schedule\ScheduleItemManager::getLastDayUpdate()
   */
  public function setLastDayUpdate(DrupalDateTime $time): void {
    $this->state->set(self::LAST_DAY_UPDATE, $time);
  }

  /**
   * Gets the datetime of the last full schedule item update.
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime
   *   Datetime of last run or yesterday if nothing found in state.
   *
   * @throws \Exception
   */
  public function getLastFullUpdate(): DrupalDateTime {
    $last_update = $this->state->get(self::LAST_FULL_UPDATE);
    if (empty($last_update)) {
      $last_update = new DrupalDateTime('yesterday');
    }
    return $last_update;
  }

  /**
   * Sets the datetime of the last full schedule item update.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $time
   *   Datetime to update with.
   *
   * @see \Drupal\kcts9_tv_schedule\ScheduleItemManager::getLastFullUpdate()
   */
  public function setLastFullUpdate(DrupalDateTime $time): void {
    $this->state->set(self::LAST_FULL_UPDATE, $time);
  }

  /**
   * Updates as much as possible from the TVSS from a date forward.
   *
   * The TVSS API generally provides about two weeks of future listings, but
   * this method will churn until it reaches a date with no listing data.
   *
   * This process can take some time, so it is recommended that this method be
   * run in an environment with no PHP execution timeout.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime|null $date
   *   A DrupalDateTime is used here as a means to (sort of) ensure that the
   *   method is dealing with a proper timezone. Defaults to today.
   *
   * @throws \Exception
   */
  public function updateFromDate(DrupalDateTime $date = NULL): void {
    if (empty($date)) {
      $date = new DrupalDateTime();
    }

    while ($this->updateByDate($date->format('Y-m-d'))) {
      $date->add(new DateInterval('P1D'));
    }
  }

  /**
   * Update all Schedule Item nodes from the TVSS API for a date.
   *
   * This method will:
   *  - Add any newly discovered API items as Schedule Items.
   *  - Update any matching existing Schedule Items found in the API. The API
   *    does not provided any method for recognizing changes, so all nodes will
   *    be updated.
   *  - Remove any Schedule Items _not_ found in the API.
   *
   * @param string $date
   *   Date string in the format Y-m-d. This is provided as a string in order to
   *   support Channel-based timezones for listing data.
   *
   * @return bool
   *   TRUE if at least one listing is found and updated, FALSE otherwise.
   */
  public function updateByDate(string $date): bool {
    $date_utc = DateTime::createFromFormat('Y-m-d', $date);
    $feeds = $this->client->getListings($date_utc, FALSE, TRUE);

    // This variable will be used as the return value. It will be flipped to
    // TRUE as long as least one listing is found and processed.
    $updated = FALSE;

    foreach ($feeds as $feed) {

      try {
        /** @var \Drupal\taxonomy\TermInterface $channel */
        $channel = $this->channelManager->addOrUpdateContent($feed);
      }
      catch (Exception $e) {
        $this->logger->critical($this->t('Unable to add/update Channel
          taxonomy content for TVSS feed @cid due to exception. See site log for
          details.', ['@cid' => $feed->cid]));
        watchdog_exception('kcts9_tv_schedule', $e);
        continue;
      }

      // Match $date's timezone to the Channel. This will help with time
      // calculations during listing update handling.
      if (!$channel->get('field_channel_timezone')->isEmpty()) {
        $timezone = new DateTimeZone($channel->get('field_channel_timezone')->value);
      }
      else {
        $this->logger->critical($this->t('Could not determine timezone for
          Channel {@label} (@id). Listing import abandoned!', [
            '@label' => $channel->label(),
            '@id' => $channel->id(),
          ]));
        continue;
      }

      // Create a date object in the Channel's timezone to use during listing
      // processing.
      $listing_date = DateTimeImmutable::createFromFormat(
        'Y-m-d H:i',
        "{$date} 00:00",
        $timezone
      );

      $cids = [];
      foreach ($feed->listings as $listing) {
        $listing->channel = $channel;
        $listing->date = $listing_date;

        try {
          $this->addOrUpdateContent($listing);
        }
        catch (Exception $e) {
          $this->logger->critical($this->t('Unable to add/update Schedule Item
          node content for TVSS listing @cid due to exception. See site log for
          details.', ['@cid' => $listing->cid]));
          watchdog_exception('kcts9_tv_schedule', $e);
          // This is allowed to fall through because the CID should still be
          // recorded to prevent accidentally deleting nodes during unmatched
          // node processing below.
        }

        // Capture all CIDs for unmatched processing (below).
        $cids[] = $listing->cid;

        // Indicates that at least one listing was found and processed.
        $updated = TRUE;
      }

      $this->removeUnmatchedContent($listing_date, $channel, $cids);
    }

    return $updated;
  }

  /**
   * Deletes any nodes with TVSS API CIDs not found in the provided $cids array.
   *
   * This is necessary because it is possible for an entry to be deleted and
   * replaced with something in the TVSS API. When this happens, the old nodes
   * need to be removed in order to prevent time overlaps in a schedule.
   *
   * @param \DateTimeImmutable $date
   *   Start date to use for query conditions.
   * @param \Drupal\taxonomy\TermInterface $channel
   *   Channel to use for query conditions.
   * @param array $expected_cids
   *   TVSS CIDs that should be found from the query.
   */
  public function removeUnmatchedContent(
    DateTimeImmutable $date,
    TermInterface $channel,
    array $expected_cids
  ): void {
    $nodes = $this->getNodesForDateAndChannel($date, $channel);

    // Get a list of "unmatched" nodes to be deleted.
    if (!empty($nodes)) {
      $unmatched_nodes = array_udiff($nodes, $expected_cids, function ($a, $b) {
        // Inputs are _not_ guaranteed to have a specific type.
        if ($a instanceof NodeInterface) {
          $a = $a->get(self::CID_FIELD_NAME)->value;
        }
        if ($b instanceof NodeInterface) {
          $b = $b->get(self::CID_FIELD_NAME)->value;
        }

        return $a <=> $b;
      });
    }

    if (!empty($unmatched_nodes)) {
      try {
        $storage = $this->getStorage();
        $storage->delete($unmatched_nodes);
      }
      catch (Exception $e) {
        watchdog_exception('kcts9_tv_schedule', $e);
        return;
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function addOrUpdateContent(stdClass $item): ContentEntityInterface {
    $node = $this->getContentByCid($item->cid);
    if (empty($node)) {
      $definition = $this->getDefinition();
      $node = Node::create([
        $definition->getKey('bundle') => self::getBundleId(),
      ]);
      $node->enforceIsNew();
    }

    /*
     * Required fields.
     */

    $node->set(self::CID_FIELD_NAME, $item->cid);
    if (isset($item->episode_title) && !empty($item->episode_title)) {
      $node->setTitle($item->episode_title);
      $node->set('field_show_title', $item->title);
    }
    else {
      $node->setTitle($item->title);
      $node->set('field_show_title', $item->title);
    }
    if (isset($item->episode_description)
      && !empty($item->episode_description)) {
      $node->set('field_description', $item->episode_description);
    }
    else {
      $node->set('field_description', $item->description);
    }
    $node->set('field_channel_ref', $item->channel);

    // Handle start date/time and calculate end date/time.
    $start_date = self::createStorageDate($item->date, $item->start_time);
    $node->set(
      'field_start_time',
      $start_date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT)
    );
    $node->set('field_minutes', $item->minutes);
    $end_date = $start_date->add(new DateInterval("PT{$item->minutes}M"));
    $node->set(
      'field_end_time',
      $end_date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT)
    );

    /*
     * Optional fields.
     */

    if (isset($item->program_external_id)
      && !empty($item->program_external_id)) {
      $node->set('field_program_external_id', $item->program_external_id);

      // Attempt to match the external ID to a locally known Show.
      $show = $this->showManager
        ->getShowNodeByTmsId($item->program_external_id);
      if ($show) {
        $node->set('field_show_ref', $show);
      }
    }

    // TVSS refers to episodes as "shows".
    if (isset($item->show_external_id)
      && !empty($item->show_external_id)) {
      $node->set('field_episode_external_id', $item->show_external_id);
    }

    if (isset($item->airing_type) && !empty($item->airing_type)) {
      $node->set('field_airing_type', $item->airing_type);
    }

    if (isset($item->program_id) && !empty($item->program_id)) {
      $node->set('field_program_id', $item->program_id);
    }

    if (isset($item->images) && !empty($item->images)) {
      $node->set('field_si_show_image', self::getShowImageUri($item->images));
    }

    if (isset($item->episode_images) && !empty($item->episode_images)) {
      $node->set(
        'field_si_episode_image',
        self::getEpisodeImageUri($item->episode_images)
      );
    }

    $node->save();

    return $node;
  }

  /**
   * Gets a Show image from an array of images for a listing.
   *
   * An image with properties "ratio" = "16:9" and "external_profile" =
   * "Banner-L2" is preferred and will be returned immediately if found. This
   * method also seeks a fallback of "ratio" = "16:9" and "external_profile" =
   * "Banner-L1". These properties come from Gracenote API image metadata.
   *
   * @param array $images
   *   Images from a listing item.
   *
   * @return string|null
   *   URI for a Show image or NULL if none found.
   *
   * @see http://developer.tmsapi.com/page/Image_Metadata
   */
  private static function getShowImageUri(array $images): ?string {
    $uri = NULL;
    foreach ($images as $image) {
      if ($image->ratio === '16:9') {
        if ($image->external_profile === 'Banner-L2') {
          $uri = $image->image;
          break;
        }
        elseif ($image->external_profile === 'Banner-L1') {
          $uri = $image->image;
        }
      }
    }
    return $uri;
  }

  /**
   * Gets an Episode image from an array of episode images for a listing.
   *
   * Returns the first image with a "16:9" ratio.
   *
   * @param array $images
   *   Episode images from a listing item.
   *
   * @return string|null
   *   URI for an Episode image or NULL if none found.
   *
   * @see http://developer.tmsapi.com/page/Image_Metadata
   */
  private static function getEpisodeImageUri(array $images): ?string {
    $uri = NULL;
    if ($key = array_search('16:9', array_column($images, 'ratio'))) {
      $uri = $images[$key]->image;
    }
    return $uri;
  }

  /**
   * Gets a storage-ready DateTime object for a date and time.
   *
   * @param \DateTimeImmutable $date
   *   Listing's run date in a Channel's timezone.
   * @param string $time
   *   Time in the format HHMM.
   *
   * @return \DateTimeImmutable
   *   The Listing's start date and time in the storage TZ and format.
   */
  private static function createStorageDate(
    DateTimeImmutable $date,
    string $time
  ): DateTimeImmutable {
    $h = (int) substr($time, 0, 2);
    $m = (int) substr($time, 2);
    $storage_tz = new DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
    $storage_date = $date->setTime($h, $m)->setTimeZone($storage_tz);
    return $storage_date;
  }

  /**
   * Gets a specific listing for a date by CID.
   *
   * @param \DateTime $date
   *   Date to check.
   * @param string $cid
   *   CID to search for.
   *
   * @return object|null
   *   The listing item or NULL if the CID is not found.
   */
  public function getListing(DateTime $date, string $cid): ?stdClass {
    $feeds = $this->client->getListings($date, FALSE, TRUE);
    foreach ($feeds as $feed) {
      $key = array_search($cid, array_column($feed->listings, 'cid'));
      if ($key) {
        return $feed->listings[$key];
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getContentByProperties(
    array $properties,
    string $sort_by = NULL,
    string $sort_dir = 'ASC',
    int $range_start = NULL,
    int $range_length = NULL
  ): array {
    try {
      $definition = $this->getDefinition();
    }
    catch (Exception $e) {
      watchdog_exception('kcts9_tv_schedule', $e);
      return [];
    }
    $properties = [
      $definition->getKey('bundle') => self::getBundleId(),
    ] + $properties;
    return parent::getContentByProperties(
      $properties,
      $sort_by,
      $sort_dir,
      $range_start,
      $range_length
    );
  }

  /**
   * Gets all nodes for a given date and channel.
   *
   * @param \DateTimeImmutable $date
   *   Date to use for query.
   * @param \Drupal\taxonomy\TermInterface $channel
   *   Channel to use for query.
   *
   * @return array|\Drupal\Core\Entity\EntityInterface[]
   *   Matching nodes or an empty array if an exception occurs.
   */
  public function getNodesForDateAndChannel(
    DateTimeImmutable $date,
    TermInterface $channel
  ): array {
    $nodes = [];

    try {
      $definition = $this->getDefinition();
      $storage = $this->getStorage();
    }
    catch (Exception $e) {
      watchdog_exception('kcts9_tv_schedule', $e);
      return $nodes;
    }

    try {
      // Get all nodes with date $date and channel $channel.
      /** @var \DateTimeImmutable $start_time */
      $start_time = $date->setTime(0, 0)
        ->setTimezone(new DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
      /** @var \DateTimeImmutable $end_time */
      $end_time = $start_time->add(new DateInterval('P1D'));
    }
    catch (Exception $e) {
      watchdog_exception('kcts9_tv_schedule', $e);
      return $nodes;
    }

    $query = $storage->getQuery();
    $query->condition($definition->getKey('bundle'), self::getBundleId())
      ->condition('field_channel_ref.target_id', $channel->id())
      ->condition(
        'field_start_time',
        $start_time->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        '>='
      )
      ->condition(
        'field_start_time',
        $end_time->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        '<='
      );

    return $storage->loadMultiple($query->execute());
  }

  /**
   * Queues items older than a certain age to be deleted.
   *
   * @param string $interval_spec
   *   An interval spec for DateInterval. Any nodes older than than this will be
   *   queued for delete. Defaults to one month (P1M).
   *
   * @see \DateInterval::__construct
   */
  public function queueOldScheduleItemsForDelete(string $interval_spec = 'P1M'): void {
    try {
      $date_limit = new DateTime();
      $date_limit->sub(new DateInterval($interval_spec));
    }
    catch (Exception $e) {
      watchdog_exception('kcts9_tv_schedule', $e);
      return;
    }

    try {
      $definition = $this->getDefinition();
      $storage = $this->getStorage();
    }
    catch (Exception $e) {
      watchdog_exception('kcts9_tv_schedule', $e);
      return;
    }

    // Get all Schedule Item nodes older than $date_limit.
    $query = $storage->getQuery();
    $query->condition($definition->getKey('bundle'), self::getBundleId());
    $query->condition(
      'field_start_time',
      $date_limit->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      '<='
    );
    $query->sort('field_start_time', 'ASC');
    $nids = $query->execute();

    // Add all results to the queue, in groups of 50.
    if (!empty($nids)) {
      $queue = $this->queueFactory
        ->get('kcts9_tv_schedule.queue.schedule_pruner');
      foreach (array_chunk($nids, 50) as $nid_group) {
        $queue->createItem($nid_group);
      }
    }
  }

}
