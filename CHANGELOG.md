# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [1.35.0] - 2021-11-04

### Fixed
- A bug where model termination would not work immediately if `terminate()` was called in constructor.
- Template usage for CPT posts. Also allow page templates to use any name, as intended in WP.

# Released

## [1.34.4] - 2021-10-14

### Fixed
- Current menu item check on archive pages.

## [1.34.3] - 2021-09-30

### Fixed
- Pagination parameter page_count didn't always return the right value.

## [1.34.2] - 2021-08-09

### Added
- Pagination parameters `page` and `page_count`. These can be used inside pagination.dust

## [1.34.1] - 2021-06-22

### Fixed
- Archive pages for custom taxonomies when filtering with another taxonomy.
- A variable typo

## [1.34.0] - 2021-06-10

### Added
- The UserActivateExtend class for allowing the functionality to be extended by the theme.

## [1.33.3] - 2021-05-05

### Fixed
- PHP 7.4 notice when using @menu helper with empty or non-assigned menu.

## [1.33.1] - 2021-04-20

### Changed
- Search template to precede home template to fix hierarchy problem with Polylang searching.

## [1.33.0] - 2021-03-23

### Added
- `no_form` parameter to `@password` helper to be used when there's more than one instance of the helper at a page.

### Changed
- DustPress.js calls returning JSON now return clear error messages from JSON encoding problems.

## [1.32.0] - 2021-03-02

### Added
- DustPress will now measure it's own performance.

## [1.31.0] - 2021-02-25

### Added
- Highlight color for performance alerts.

### Changed
- Decreased DustPress-debugger performance alert from 0.1s to 0.02s.

## [1.30.1] - 2021-02-16

### Fixed
- Possible fatal error caused by measure_hooks_performance.

## [1.30.0] - 2021-01-21

### Added
- Ability to include custom data and filter the data of the pagination helper.
- Ability to disable encoding @s helper strings to HTML entities.

### Changed
- The s helper runs its output through htmlentities function to encode quotes as HTML entities.

## [1.29.5] - 2020-11-17

### Fixed
- Performance problems created by the performance measuring feature.

## [1.29.4] - 2020-07-02

### Fixed
- Fix data handling in DustPress.js GET requests.

## [1.29.3] - 2020-06-16

### Fixed
- A bug within the Helper base class that caused the page to crash in some situations.

## [1.29.2] - 2020-06-16

### Changed
- A lot of cleaning up of the code base.

### Fixed
- Moved saving template paths to the DustPHP instance earlier for compatibility reasons.

## [1.29.1] - 2020-05-12

### Fixed
- A bug where `$GLOBALS['pagenow']` is empty.

## [1.29.0] - 2020-03-09

### Fixed
- Remove the Dust dependency when rendering the data with another render function.

## [1.28.1] - 2020-03-05

### Added
- More details for hook measurements.
- Added $main for dustpress/data/after_render filter.
- Automatic performance measuring for hooks.

## [1.28.0] - 2020-02-20

### Added
- Automatic performance measuring.

## [1.27.1] - 2019-12-13

### Added
- Added network support for the image helper

## [1.27.0] - 2019-12-03

### Added
- A filter for image helper `$image_data`.

## [1.26.3] - 2019-12-03

### Fixed
- A bug in the menu helper that caused fatal errors when the menu did not exist.

## [1.26.2] - 2019-11-13

### Fixed
- Small fix to DustPHP for PHP 7.4 compatibility.

## [1.26.1] - 2019-11-13

### Fixed
- A bug that prevented DustPress Debugger for working properly with AJAX calls.

## [1.26.0] - 2019-11-11

### Added
- Debugging data from DustPress.js calls added to DustPress Debugger view as well.

### Changed
- Default name for the debugging data block to "Debug" instead of "Helper data".

## [1.25.4] - 2019-11-05

### Fixed

## [1.25.4] - 2019-11-5
- Fixed the ability to run multiple methods with a single DustPress.js AJAX requests.

## [1.25.3] - 2019-10-29

### Fixed
- A bug in the menu helper that caused fatal errors when the menu was empty.

## [1.25.2] - 2019-10-18

### Fixed
- The model's terminating (`$this->terminate();`) feature which was broken because of the forced 404 feature.

## [1.25.1] - 2019-10-01

### Added
- Added user email to the data for the partial in UserActivate model.

### Fixed
- Fixed the menu helper partial, which printed out an empty ul element when the requested menu doesn't exist.

## [1.25.0] - 2019-09-20

### Fixed
- Fixed a bug in the pagination helper first page handling.
- Fixed a bug in user-active.php caused by a change in WP core.

## [1.24.1] - 2019-08-20

### Fixed
- A bug where overridden methods in extended models would get run twice.

## [1.24.0] - 2019-08-14

### Changed
- Error handlers now send HTTP status code 500 in error situations.

## [1.23.2] - 2019-06-05

### Fixed
- A bug in how class names with multiple word spaces should be formed.

## [1.23.1] - 2019-06-04

### Fixed
- A bug in the AJAX single method running function.

## [1.23.0] - 2019-06-04

### Changed
- Optimized the loading of Dust partials so that the file matching is done only once per file.

## [1.22.3] - 2019-05-23

## Fixed
- Fixed the inaccurate matching for WordPress feed urls in the core autoloader checking.

## [1.22.2] - 2019-04-08

### Fixed
- Another minor bug regarding the custom route rendering type setting.

## [1.22.1] - 2019-04-02

### Fixed
- An error where not giving a rendering type for custom routes would cause a notice.

## [1.22.0] - 2019-03-29

### Added
- The ability to modify the output format of a custom route by giving it a third parameter to for example use custom routes as JSON endpoints.

### Fixed
- Fixed case in menu helper build_menu method when $item->classes is not an array (e.g. certain cases in Customizer).

## [1.21.3] - 2019-03-04

### Fixed
- A minor bug caused by the last update.

## [1.21.2] - 2019-03-04

### Fixed
- Pagination helper fixed $page_count to return int not float. This caused failure on current page active state in some cases.
- A bug with the menu helper and WordPress Customizer.

## [1.21.1] - 2019-02-26

### Fixed
- A bug that caused a notice when a template was rendered manually with empty data block.

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
