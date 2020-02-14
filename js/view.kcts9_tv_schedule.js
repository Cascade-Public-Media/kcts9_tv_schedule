/**
 * @file
 * Javascript support for the KCTS 9 TV Schedule View.
 */

(function ($, Drupal) {

  /**
   * Update arguments and refresh a View based on link data attributes.
   *
   * TODO: Update URL state to include filters.
   *
   * @type {{attach: Drupal.behaviors.kcts9TvScheduleViewArgUpdate.attach}}
   */
  Drupal.behaviors.kcts9TvScheduleViewArgUpdate = {
    attach: function (context, settings) {
      $('.kcts9-tv-schedule-view-arg-update', context)
        .once('kcts9TvScheduleViewArgUpdate')
        .each(function () {
        $(this).bind('click', function (e) {

          // Update the specified filter type with a new value.
          let $type = $(this).data('type');
          let $value = $(this).data('value');
          let $filters = $('.kcts9-tv-schedule-filters');
          $filters.data($type, $value);

          // Refresh the View with the updated filters.
          let $view_dom_id = $filters.data('view-dom-id');

          // Handle arguments.
          let $view_args = $filters.data('date') + '/' + $filters.data('channel');
          if ($filters.data('favorites') === 1) {
            $view_args += '/favorites';
          }
          else {
            $view_args += '/all';
          }
          if ($filters.data('current-only') === 1) {
            $view_args += '/current-only';
          }
          else {
            $view_args += '/all';
          }

          if ($view_dom_id && $view_args) {
            let $instance = Drupal.views.instances['views_dom_id:' + $view_dom_id];
            if ($instance) {
              $instance.settings.view_args = $view_args;
              $instance.$view.trigger('RefreshView');
            }
          }

          if ($(this).prop('type') === 'submit') {
            e.preventDefault();
          }
        });
      });
    }
  };

})(jQuery, Drupal);
