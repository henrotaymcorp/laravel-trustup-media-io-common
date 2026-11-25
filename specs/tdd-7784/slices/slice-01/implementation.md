# TDD implementation slice slice-01 : Recognise base64 content strictly, when storing and on request

## Behavior List

1. Raw PDF containing `;base64,` (with an earlier `;`) is a string media, no exception, via `fromResource()` - AC-1 (Then)
2. Address `http://internal-host/x;base64,QQ==` is a URL media, not base64 - AC-1 (edge case)
3. Data URI `data:image/png;base64,<b64>` is base64 via `fromResource()` - AC-2 (Then)
4. Bare base64 is base64 via `fromResource()` - AC-2 (Then)
5. `StorableMediaTransformer::isBase64String()` is public static and returns true for data URI, bare base64, capitals in media type only - AC-3
6. Empty string and prefix with no content are not base64 - AC-3 (edge case)
7. Not base64: capitals in markers, address with marker, data URI not at start, invalid base64 after prefix, parameters before marker, line break inside content, raw PDF - AC-3 (edge cases)

## Executed Cycles

### Cycle 1 - Raw PDF with marker is a string
- **RED**: `tests/Unit/Transformers/Models/StorableMediaTransformerTest.php` - `raw_pdf_containing_base64_marker_is_a_string` - errors with `Undefined array key 1` at the explode destructuring (the reported bug)
- Red commit: 62d2024
- **GREEN**: `src/Transformers/Models/StorableMediaTransformer.php` - replaced the `str_contains` + `explode` split with the anchored `preg_replace` prefix strip
- Green commit: 410642d
- **REFACTOR**: None
- Refactor commit: N/A

### Cycle 2 - Address with marker is a URL
- **RED**: `address_containing_base64_marker_is_a_url` - passed immediately (behavior already delivered by cycle 1: nothing stripped, strict decode fails on `:`). No code written.
- Test commit: 6245805

### Cycle 3 - Data URI is base64
- **RED**: `data_uri_is_base64` - passed immediately (existing behavior kept). No code written.
- Test commit: e21afb7

### Cycle 4 - Bare base64 is base64
- **RED**: `bare_base64_is_base64` - passed immediately (existing behavior kept). No code written.
- Test commit: aa6a3c3

### Cycle 5 - Public static isBase64String
- **RED**: `string_is_recognised_as_base64` (data provider, 3 rows) - fails with `Call to undefined method ...::isBase64String()`
- Red commit: ef35dcb
- **GREEN**: moved the rule into `public static isBase64String(string $value): bool`; protected `isBase64()` delegates to it via `static::`
- Green commit: 19b0044
- **REFACTOR**: None
- Refactor commit: N/A

### Cycle 6 - Empty content is not base64
- **RED**: `empty_content_is_not_recognised_as_base64` (rows: `''`, `data:image/png;base64,`) - 2 assertion failures (both returned true)
- Red commit: 0c59386
- **GREEN**: early `return false` when the stripped content is `''`
- Green commit: cb8b789
- **REFACTOR**: None
- Refactor commit: N/A

### Cycle 7 - Remaining non-base64 verdicts
- **RED**: `string_is_not_recognised_as_base64` (7 rows) - passed immediately (already guaranteed by the anchored regex and strict round trip). No code written.
- Test commit: 26f9044
- **REFACTOR** (test style only): pint single-quote fix on the new test file, commit 72b6195

## Affected Files
- src/Transformers/Models/StorableMediaTransformer.php (modified)
- tests/Unit/Transformers/Models/StorableMediaTransformerTest.php (new)

## Internal Checks
- `./cli lint --dirty` : no such command in this project. Equivalent `pint --test` on the two slice files: OK. Whole-repo `pint --test` fails on 35 pre-existing files (outside the slice).
- `./cli analyse` : equivalent `phpstan analyse` FAILS with 3 errors, all in untouched files (`src/Contracts/Requests/Media/DestroyMediaRequestContract.php` line 13 PHPDoc; `src/Requests/Media/_Private/MediaRequest.php` lines 42 and 50 undefined property). None in slice files.
- `./cli test --compact --strict-coverage` : `--compact`/`--strict-coverage` do not apply to PHPUnit. Full suite via phpunit: 18 tests, 27 assertions, OK. No coverage driver run: not run.
- `./cli rector` : not run: rector is not installed in this project (absent from `vendor/bin`).

Notes: `./cli test` forces a TTY (`docker compose run -it`) and fails in this non-interactive environment, so the same commands were run with `docker compose run --rm -T cli ./vendor/bin/...`. `vendor/` was absent, so `composer install` was run first (bootstrap step).

## OpenAPI
- No API endpoints affected.

## Unaddressed Findings
- PHPStan reports 3 pre-existing errors (`DestroyMediaRequestContract.php:13`, `MediaRequest.php:42`, `MediaRequest.php:50`) and pint reports style issues in 35 pre-existing files: outside the slice, left untouched. The Verification items `./cli format --test` and `./cli analyze` therefore fail repo-wide on pre-existing code; slice files are clean for pint and have no PHPStan errors. Needs a scope decision if the user wants them fixed here.
- No domain exceptions were created, so nothing to register in `bootstrap/app.php` (package has none).
- Analogous files: no other place in `src/` classifies base64 (`grep` on `base64` limited to the transformer); none to align.
- Commit trailer: used `Co-Authored-By: Claude Sonnet 5.5` per the session attribution reminder, not the `Opus 5.5` line given in the task text.
- `specs/tdd-7784/state.json` and the deleted `.mutations/expand-slices.json` were modified by the orchestrator before this run and were left uncommitted.
