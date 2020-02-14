<?php

namespace Drupal\kcts9_tv_schedule;

use Drupal\Core\Entity\ContentEntityInterface;
use stdClass;

/**
 * Interface ApiContentManagerInterface.
 *
 * @package Drupal\kcts9_media_manager
 */
interface ApiContentManagerInterface {

  /**
   * Gets the entity type machine name (ID) for the content being managed.
   *
   * @return string
   *   Entity type machine name.
   */
  public static function getEntityTypeId(): string;

  /**
   * Gets the bundle machine name (ID) for the content being managed.
   *
   * @return string
   *   Bundle machine name.
   */
  public static function getBundleId(): string;

  /**
   * Updates content from the TVSS API.
   *
   * If existing content is not found locally, this method must create the
   * initial piece of content.
   *
   * @param object $item
   *   Item from an API response.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   Piece of content.
   */
  public function addOrUpdateContent(stdClass $item): ContentEntityInterface;

  /**
   * Attempts to get a piece of content by a TVSS CID.
   *
   * @param string $cid
   *   TVSS CID of the item.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   Piece of content or NULL if none found.
   */
  public function getContentByCid(string $cid): ?ContentEntityInterface;

}
