# Plan : Reliable base64 recognition for stored media

## Summary

Replace the loose base64 check in `StorableMediaTransformer` with the anchored rule worksite-api already uses (lower-case `data:` and `;base64,` markers, any-case media type, non-empty strict round trip), published as one public static method so worksite-api can call it without building a media object. One modified source file and one new test file, released as a patch.

## Approach

Approach A (Minimalism), chosen by the user. The rule stays in the transformer and is exposed once as `StorableMediaTransformer::isBase64String()`. B was rejected because adding a method to `StorableMediaTransformerContract` breaks outside implementers and rules out a patch release. C was rejected because it reverses the lower-case markers decision and rewrites stored content, which no AC asks for.

## Existing code reused

-   `src/Transformers/Models/StorableMediaTransformer.php`: the strict `base64_decode` / `base64_encode` round trip (current lines 92-96) is kept as is; `fromResource()` already falls through to `setAsString()` when `isBase64()` is false
-   `tests/TestCase.php`: base test case that boots the package service provider (used by the new test to resolve the transformer)

## File structure

-   `src/Transformers/Models/StorableMediaTransformer.php` (modified): adds `public static isBase64String(string $value): bool` holding the rule; the protected `isBase64()` delegates to it. Transformer, existing class.
-   `tests/Unit/Transformers/Models/StorableMediaTransformerTest.php` (new): PHPUnit unit test (`#[Test]` attributes, extends `Tests\TestCase`, as in `tests/Unit/InstallingPackageTest.php`): classification through `fromResource()` and verdicts of `isBase64String()`.

## Sketch

Flow:

1. `StoreMediaRequestTransformer::fromArray` or a consuming app → `StorableMediaTransformer::fromArray` → `fromResource($resource)`
2. → `$this->isBase64($resource)` → `static::isBase64String($resource)` [AC-1, AC-2]
3. true → `$media->setAsBase64()` [AC-2]; false → `isUrl()` → `$media->setAsString()` [AC-1]
4. worksite-api `is_base64_string()` → `StorableMediaTransformer::isBase64String()` [AC-3], wired later in worksite-api (out of scope here)

`src/Transformers/Models/StorableMediaTransformer.php` (modified)

```php
public static function isBase64String(string $value): bool
{
    // Markers stay lower case: Spatie's addMediaFromBase64 only strips a lower-case prefix.
    $content = preg_replace('~^data:[a-zA-Z0-9.+-]+/[a-zA-Z0-9.+-]+;base64,~', '', $value) ?? '';

    if ($content === '') {
        return false;
    }

    // strict mode filters for non-base64 alphabet characters
    $binary = base64_decode($content, true);

    return $binary !== false
        && base64_encode($binary) === $content;
}

protected function isBase64(string $resource): bool
{
    return static::isBase64String($resource);
}
```

`fromResource()` is not modified: AC-1 is met by the existing fall-through to `setAsString()` once `isBase64()` returns false. A `null` from `preg_replace` (regex engine error) becomes `''` and fails closed. No contract, provider or model file changes.

## Slices overview

| Slice | Use case | ACs | Depends on |
| ----- | -------- | --- | ---------- |
| slice-01 | Recognise base64 content strictly, when storing and on request | AC-1, AC-2, AC-3 | None |

## Coverage matrix

| AC  | Slice(s) |
| --- | -------- |
| AC-1 | slice-01 |
| AC-2 | slice-01 |
| AC-3 | slice-01 |

## Architectural decisions

| Decision | Alternative rejected | Reason |
| -------- | -------------------- | ------ |
| Public static method on the transformer | Method on `StorableMediaTransformerContract` (B) | Interface change breaks outside implementers; no patch release |
| Lower-case `data:` / `;base64,` markers, any-case media type | Case-insensitive flag as in worksite-api, or RFC canonicalisation (C) | Spatie `addMediaFromBase64` (v11.23.10, `InteractsWithMedia.php:209`) only strips a lower-case `;base64`; recognising upper-case markers would turn content stored today as text into a storage failure |
| Keep protected `isBase64()` delegating | Remove it | Keeps `fromResource()` untouched and any subclass override working |
