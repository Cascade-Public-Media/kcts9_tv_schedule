<?php

namespace Drupal\kcts9_tv_schedule;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermInterface;
use Exception;
use stdClass;

/**
 * Class ChannelManager.
 *
 * @package Drupal\kcts9_tv_schedule
 */
class ChannelManager extends ApiContentManagerBase {

  /**
   * {@inheritdoc}
   */
  public static function getEntityTypeId(): string {
    return 'taxonomy_term';
  }

  /**
   * {@inheritdoc}
   */
  public static function getBundleId(): string {
    return 'channel';
  }

  /**
   * Add and/or update all Channel terms from the TVSS API.
   */
  public function update(): void {
    $feeds = $this->client->getFeeds();
    foreach ($feeds as $feed) {
      try {
        $this->addOrUpdateContent($feed);
      }
      catch (Exception $e) {
        $this->logger->critical($this->t('Unable to add/update Channel 
          taxonomy content due to exception. See site log for details.'));
        watchdog_exception('kcts9_tv_schedule', $e);
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function addOrUpdateContent(stdClass $feed): ContentEntityInterface {
    $channel = $this->getContentByCid($feed->cid);
    if (empty($channel)) {
      $definition = $this->entityTypeManager->getDefinition(self::getEntityTypeId());
      $channel = Term::create([
        $definition->getKey('bundle') => self::getBundleId(),
      ]);
    }

    $channel->setName($feed->full_name);
    $channel->set(self::CID_FIELD_NAME, $feed->cid);
    $channel->set('field_channel_external_id', $feed->external_id);
    $channel->set('field_channel_short_name', $feed->short_name);
    $channel->set('field_channel_timezone', $feed->timezone);
    $channel->save();

    return $channel;
  }

  /**
   * Gets all Channel terms that are configured for Schedule usage.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   Channel terms with `field_schedule_option` set to TRUE.
   */
  public function getScheduleChannels(): array {
    return $this->getContentByProperties(
      ['field_schedule_option' => TRUE],
      'weight'
    );
  }

  /**
   * Gets the default Channel for Schedule usage.
   *
   * @return \Drupal\taxonomy\TermInterface|null
   *   Channel term with `field_schedule_option` and `field_schedule_default`
   *   set to TRUE or NULL if none found.
   */
  public function getScheduleDefaultChannel(): ?TermInterface {
    $channel = NULL;
    $channels = $this->getContentByProperties([
      'field_schedule_option' => TRUE,
      'field_schedule_default' => TRUE,
    ]);
    if (!empty($channels)) {
      $channel = reset($channels);
    }
    return $channel;
  }

}
