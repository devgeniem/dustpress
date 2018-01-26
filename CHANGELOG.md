# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
