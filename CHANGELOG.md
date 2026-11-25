# laravel-trustup-media-io-common

## 2.0.2

### Patch Changes

- 69de829: Fix base64 detection crashing on raw content containing ';base64,' (e.g. binary PDFs). Only a data URI prefix at the start of the value is stripped; empty content and markers elsewhere are no longer treated as base64. Expose the check as `StorableMediaTransformer::isBase64String()`.

## 2.0.1

### Patch Changes

- 05060d4: Add changeset release infrastructure + bun

## 1.3.0

### Minor Changes

- 6cc4929: Handling media collection enum in attributes.
