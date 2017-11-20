# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed
- Fixed a Notice from `is_cacheable_function` method when `$ttl` is not set in some model

## [1.7.1] - 2017-11-03

### Changed
- Fixed not properly recognizing tidy & render arguements on older dustpress-js calls

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
