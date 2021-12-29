# CiviCRM Green Register

Provides customisations for integrating CiviCRM and Green Register with WordPress

* **Contributors:** [MJW](https://mjw.pt/support/)
* **Tags:** civicrm, sync
* **Requires at least:** 5.8
* **Tested up to:** 5.8
* **Stable tag:** 0.1
* **License:** GPLv2 or later
* **License URI:** https://www.gnu.org/licenses/gpl-2.0.html

## Requirements

* [CiviCRM Profile Sync](https://wordpress.org/plugins/civicrm-wp-profile-sync/).

## Functionality

### Custom Post Type "The Register"

This plugin provides a custom post type "The Register" which is used to synchronise contacts of type "Organization" that can be shown on a searchable register on the frontend website.

It provides one-way sync (CiviCRM -> WordPress) of "Organization Details" custom fields as taxonomies:
* Regions
* Professions

Any changes on the WordPress side will be overwritten. You must make all changes in CiviCRM.

