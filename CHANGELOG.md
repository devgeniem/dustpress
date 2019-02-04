# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [hotfix-pagination-helper-active-state] - 2019-02-04

### Added

- Pagination helper fixed $page_count to return int not float. This caused failure on current page active state in some cases.

## [1.21.0] - 2019-02-01

### Added

- Object caching for the menu helper.

## [1.20.0] - 2019-01-08

### Added
- A function `get_custom_route()` that returns matched custom route and the template it points to.

## [1.19.1] - 2019-01-07

### Changed
- Added a version check for the user activation feature because WordPress 5.0 breaks the backwards compatibility of the customization.

## [1.19.0] - 2018-12-19

### Changed
- Rendered HTML is not cached by default anymore as it may cause problems with object cache drop-ins.
- Only show image helper error messages if WP_DEBUG is set to true

## [1.18.0] - 2018-10-23

### Added
- Cache DustPHP template file paths during runtime to prevent multiple recursive directory searches for same template requests.

## [1.17.0] - 2018-10-19

### Fixed
- Make the menuhelper show the correct current item even on category or tag archives.
- Query::get_post() and Query::get_acf_post() methods to function properly without global post object existence.

## [1.16.8] - 2018-10-15

### Fixed
- A bug in the Query class that caused a notice in certain situations.

## [1.16.7] - 2018-10-10

### Added
- A filter to the output HTML of the Image helper.

## [1.16.6] - 2018-10-05

### Fixed

- `DustPress\Query::get_acf_post()` method returns null if no post is found with the given id. Fixes [#87](https://github.com/devgeniem/dustpress/issues/87).
- `DustPress\Query::get_acf_post()` will not throw an error if ACF is not active.

## [1.16.5] - 2018-09-30

### Fixed
- A bug that broke some of the recursive functionality in the Query class.

## [1.16.4] - 2018-09-14

### Fixed
- A minor bug in the previous release.

## [1.16.3] - 2018-09-14

### Fixed
- A bug where `get_post()` and `get_acf_post()` would not work with proper IDs if used multiple times in a pageload.

## [1.16.2] - 2018-09-03

### Changed
- Fixed 'dustpress/router' filter for WordPress preview functionality.
- Removed unnecessary `$id` parameter from `get_post_meta()`.
- Changed functions `get_post()` and `get_acf_post()` to use global post if desired post id same as global post.

## [1.16.1] - 2018-06-20

### Added
- Added a parameter to enable last update's changes as otherwise they would break backwards-compability.

## [1.16.0] - 2018-06-14

### Added
- Some optimizations for DustPress ajax calls to make them faster and less error-prone.

## [1.15.3] - 2018-06-03

### Fixed
- Core files do not override theme files anymore.

## [1.15.2] - 2018-05-30

### Fixed
- A bug where core partials where unusable in some cases.

## [1.15.1] - 2018-05-25

### Fixed
- A bug which prevented partials in theme from overriding core partials.

## [1.15.0] - 2018-05-09

### Added
- A possibility to give Comparison helpers `type` parameter to use it with booleans as is the case with the original Dust.js.

## [1.14.1] - 2018-04-13

### Fixed
- A bug where PHP warnings would occur when trying to call custom AJAX route that does not exist. Enhanced error messages as well.

## [1.14.0] - 2018-03-21

### Changed
- Template file locations are now searched only once and stored in cache to improve performance.

## [1.13.1] - 2018-03-19

### Changed
- Moved add_rewrite_tag calls to init hook.

## [1.13.0] - 2018-03-15

### Added
- Ability to register custom routes.
- The Pagination helper now adds its data into the debugger.

### Fixed

- Code style fixes for the Pagination helper.

## [1.12.0] - 2018-01-29

### Added
- Menu location to the menu helper data.
- Filters for menu object and menu items by the menu location.

## [1.11.0] - 2018-01-26

### Added
- Documentation for Model class.
- A `set_args()` method for Model to make it possible to set arguments for an already instantiated model dynamically.

### Fixed
- A bug where renaming a model to a name it already had would throw an error.
- A bug where sending an array as a JSON POST payload to a DustPress site would cause an error.

## [1.10.0] - 2017-12-21

### Added
- By giving the AJAX request a `data` parameter, it now returns the resulting data with the rendered partial. DustPress.js 2.1.0 is required for the front-end side.

### Changed
- The `get_post` and `get_posts` methods in the `Query` class now get the key `image_id` containing the the featured image id if it is found.

## [1.9.0] - 2017-12-18

### Changed
- The `Pagination` helper allows changing the amount of pages displayed with the new `neighbours` parameter.
- Corrected documentation for `Query` classes recursive post querying functionality.

## [1.8.1] - 2017-11-20

### Changed
- Fixed a Notice from `is_cacheable_function` method when `$ttl` is not set in some model

## [1.8.0] - 2017-11-17

### Changed
- Changed the default functionality for the render cacheing to be true

## [1.7.1] - 2017-11-03

### Changed
- Fixed not properly recognizing tidy & render arguments on older DustPress.js calls

## [1.7.0] - 2017-11-03

### Added
- Model and partial containing the basic functionality of the default wp-activate.php file.

### Changed
- Modifiied core so that when wp-activate.php is loaded the execution is stopped and DustPress creates its own instance.

## [1.6.11] - 2017-10-30

### Changed
- Optimized file loading routines to reduce loading times

## [1.6.10] - 2017-10-20

### Added
- Support for new DustPress.js version
