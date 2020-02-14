<?php

/**
 * @file
 * Functions related to the KCTS 9 TV Schedule View.
 */

use Drupal\Core\Cache\Cache;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\views\ViewExecutable;

/**
 * Add JS-based contextual filter handlers to the Schedule View.
 *
 * @param \Drupal\views\ViewExecutable $view
 *   View being built.
 *
 * @throws \Exception
 *
 * @see kcts9_tv_schedule_views_pre_render
 */
function kcts9_tv_schedule_view_alter(ViewExecutable &$view): void {
  $view->element['#attached']['library'][] = 'kcts9_tv_schedule/view.kcts9_tv_schedule';

  $element = [
    '#type' => 'container',
    '#attributes' => [
      'class' => ['kcts9-tv-schedule-filters'],
      'data-view-dom-id' => $view->dom_id,
      'data-date' => NULL,
      'data-channel' => NULL,
      'data-current-only' => NULL,
      'data-favorites' => NULL,
    ],
  ];

  _kcts9_tv_schedule_view_attach_date_picker($element, $view);
  _kcts9_tv_schedule_view_attach_channel_picker($element, $view);
  _kcts9_tv_schedule_view_attach_options($element, $view);

  $view->attachment_before[] = $element;
}

/**
 * Creates basic render array for "options" element.
 *
 * This elements controls the "favorites" (args[2]) and "current only" (args[3])
 * filters.
 *
 * @param array $parent
 *   Container element for all View filters.
 * @param \Drupal\views\ViewExecutable $view
 *   View being built.
 */
function _kcts9_tv_schedule_view_attach_options(array &$parent, ViewExecutable $view): void {
  $favorites_only = $view->args[2] === 'favorites';
  if ($view->args[0] == date('Ymd')) {
    $current_only = $view->args[3] === 'current-only';
    $parent['#attributes']['data-current-only'] = (int) $current_only;
  }
  else {
    $current_only = NULL;
  }

  $element = [
    '#theme' => 'kcts9_tv_schedule_view_options',
    '#attributes' => [
      'class' => ['kcts9-tv-schedule-view-options'],
    ],
    '#current_only' => $current_only,
    '#favorites_only' => $favorites_only,
  ];

  $parent['#attributes']['data-favorites'] = (int) $favorites_only;
  $parent[] = $element;
}

/**
 * Creates a render array for the Date Picker.
 *
 * @param array $parent
 *   Container element for all View filters.
 * @param \Drupal\views\ViewExecutable $view
 *   View being built.
 *
 * @throws \Exception
 */
function _kcts9_tv_schedule_view_attach_date_picker(array &$parent, ViewExecutable $view): void {
  $element = [
    '#theme' => 'kcts9_tv_schedule_view_date_picker',
    '#attributes' => [
      'class' => ['kcts9-tv-schedule-view-date-picker'],
    ],
    '#dates' => [],
    '#default_date' => $view->args[0],
    '#next_week_date' => NULL,
    '#last_week_date' => NULL,
  ];

  // Create a group of date options for Sun - Sat of the current week, excluding
  // dates outside the range of available schedule data for the default channel.
  /** @var \Drupal\Core\Datetime\DrupalDateTime[] $range */
  $range = _kcts9_tv_schedule_get_schedule_date_range();
  $date_min = $range['min'];
  $date_max = $range['max'];
  $date_current = DrupalDateTime::createFromFormat(
    'Ymd',
    $element['#default_date']
  );
  $dow = $date_current->format('w');
  $date = clone $date_current;
  if ($dow > 0) {
    $date->sub(new DateInterval("P{$dow}D"));
  }
  for ($i = 0; $i <= 6; $i++) {
    if ($date->format('Ymd') >= $date_min->format('Ymd')
      && $date->format('Ymd') <= $date_max->format('Ymd')) {
      $element['#dates'][$i] = clone $date;
    }
    $date->add(new DateInterval('P1D'));
  }

  // Add next week date using last $date object from previous loop.
  if ($date->format('Ymd') <= $date_max->format('Ymd')) {
    $element['#next_week_date'] = clone $date;
  }

  // Add last week date.
  $last_week_date = clone reset($element['#dates']);
  $last_week_date->sub(new DateInterval('P1D'));
  if ($last_week_date->format('Ymd') >= $date_min->format('Ymd')) {
    $element['#last_week_date'] = $last_week_date;
  }

  $parent['#attributes']['data-date'] = $element['#default_date'];
  $parent[] = $element;
}

/**
 * Creates a render array for the Channel Picker.
 *
 * @param array $parent
 *   Container element for all View filters.
 * @param \Drupal\views\ViewExecutable $view
 *   View being built.
 */
function _kcts9_tv_schedule_view_attach_channel_picker(array &$parent, ViewExecutable $view): void {
  /** @var \Drupal\kcts9_tv_schedule\ChannelManager $channelManager */
  $channelManager = Drupal::service('kcts9_tv_schedule.channel_manager');

  $element = [
    '#theme' => 'kcts9_tv_schedule_view_channel_picker',
    '#attributes' => [
      'class' => ['kcts9-tv-schedule-view-channel-picker'],
    ],
    '#channels' => $channelManager->getScheduleChannels(),
    '#default_channel' => $view->args[1],
  ];

  $parent['#attributes']['data-channel'] = $element['#default_channel'];
  $parent[] = $element;
}

/**
 * Gets a minimum and maximum date for the TV Schedule view.
 *
 * The returned dates are DrupalDateTime objects in the timezone of the default
 * schedule channel.
 *
 * This data is based on the default channel so it is not 100% accurate for the
 * view (which may be filtered by other channels). While this is not ideal, it
 * gets around the complexity of querying more granularly on the view, which is
 * currently not cachable.
 *
 * @return \Drupal\Core\Datetime\DrupalDateTime[]
 *   Array of two DrupalDateTime objects. The minimum date is in the "min" key
 *   and the maximum is in the "max" key.
 */
function _kcts9_tv_schedule_get_schedule_date_range(): array {
  $cid = 'kcts9_tv_schedule.schedule_date_range';
  $cache = Drupal::cache();

  $range = $cache->get($cid);
  if (!$range) {
    $cache_tags = [];
    /** @var \Drupal\Core\Datetime\DrupalDateTime|null $date_min */
    $date_min = NULL;
    /** @var \Drupal\Core\Datetime\DrupalDateTime|null $date_max */
    $date_max = NULL;
    /** @var \Drupal\kcts9_tv_schedule\ScheduleItemManager $scheduleItemManager */
    $scheduleItemManager = Drupal::service('kcts9_tv_schedule.schedule_item_manager');

    // Cache earliest and latest schedule availability dates.
    $channel = $scheduleItemManager
      ->getChannelManager()
      ->getScheduleDefaultChannel();
    try {
      $timezone = new DateTimeZone($channel->get('field_channel_timezone')->value);
    }
    catch (Exception $e) {
      $timezone = NULL;
    }

    if ($channel && $timezone) {
      $properties = [
        'status' => TRUE,
        'field_channel_ref' => $channel->id(),
      ];

      $cache_tags = $channel->getCacheTags();

      // Get earliest date.
      $nodes = $scheduleItemManager
        ->getContentByProperties($properties, 'field_start_time', 'ASC', 0, 1);
      if (!empty($nodes)) {
        /** @var \Drupal\node\NodeInterface $earliest */
        $earliest = reset($nodes);
        $date_min = $earliest->get('field_start_time')->date;
        $date_min->setTimezone($timezone);
        $cache_tags = Cache::mergeTags($cache_tags, $earliest->getCacheTags());
      }

      // Get latest date.
      $nodes = $scheduleItemManager
        ->getContentByProperties($properties, 'field_start_time', 'DESC', 0, 1);
      if (!empty($nodes)) {
        /** @var \Drupal\node\NodeInterface $latest */
        $latest = reset($nodes);
        $date_max = $latest->get('field_start_time')->date;
        $date_max->setTimezone($timezone);
        $cache_tags = Cache::mergeTags($cache_tags, $latest->getCacheTags());
      }
    }

    // Provide sane fallback dates.
    if (empty($date_min)) {
      $date_min = new DrupalDateTime('-1 month', $timezone);
    }
    if (empty($date_max)) {
      $date_max = new DrupalDateTime('+1 month', $timezone);
    }

    $range = ['min' => $date_min, 'max' => $date_max];
    $cache->set($cid, $range, time() + 60 * 60 * 6, $cache_tags);
  }
  else {
    $range = $range->data;
  }

  return $range;
}
