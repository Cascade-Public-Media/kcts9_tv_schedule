# KCTS 9 TV Schedule

This repository contains the custom module KCTS 9 TV Schedule as developed by
KCTS 9 for the [kcts9.org](https://www.kcts9.org) website.

## AS-IS

This is module is **not complete** as it does not include full configuration
elements necessary for use. The module expects one content type --
"schedule_item" -- and one taxonomy vocabulary -- "channel" -- to exist in order
to function. Example configuration files are provided in the [`example-config`](example-config)
folder, but they may not be complete.

## Functionality

The primary functionality for _syncing_ of content from TVSS can be found in the
[`ScheduleItemManager`](src/ScheduleItemManager.php) and [`ChannelManager`](src/ChannelManager.php)
classes.

Note: some related hooks/functions/classes/etc. for the full functionality of
the kcts9.org TV schedule may not be included here.

## Dependencies

The [KCTS 9 Media Manager](https://github.com/Cascade-Public-Media/kcts9_media_manager)
module is an enforced dependency for this module.

Although it is not explicitly defined by the module configuration, this module
requires the [openpublicmedia/pbs-tv-schedules-service-php ](https://packagist.org/packages/openpublicmedia/pbs-tv-schedules-service-php)
library.
