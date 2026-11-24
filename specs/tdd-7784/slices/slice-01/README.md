# Slice 1: Recognise base64 content strictly, when storing and on request

## Covered ACs
- **AC-1**: raw content (for example a PDF) containing `;base64,` anywhere other than as a well-formed prefix at the very start is classified as raw string content, without any error
- **AC-2**: a data URI starting with `data:<type>/<subtype>;base64,` followed by valid non-empty base64, or valid non-empty base64 on its own, is still classified as base64
- **AC-3**: the same verdict is available through a public static method, without building a media object

## Dependencies
None.

## Objective
`StorableMediaTransformer::isBase64()` (protected) currently splits any value containing `;base64,` on its first `;` and first `,`. A raw PDF with an earlier `;` makes the destructuring hit `Undefined array key 1`, which Laravel turns into an exception. It also accepts `http://internal-host/x;base64,QQ==` and `''` as base64.

Add `public static function isBase64String(string $value): bool` to the transformer with this rule, and make the protected `isBase64()` return `static::isBase64String($resource)`:

1. Strip an anchored prefix with `preg_replace('~^data:[a-zA-Z0-9.+-]+/[a-zA-Z0-9.+-]+;base64,~', '', $value) ?? ''`. No `i` flag: `data:` and `;base64,` must be lower case (the storage side, Spatie `addMediaFromBase64`, only strips a lower-case `;base64`), the media type may use any case.
2. Return false when what remains is `''`.
3. Otherwise keep the existing strict round trip: `base64_decode($content, true)` not false and re-encoding equals `$content`.

Do not modify `fromResource()`: it already falls through to `setAsString()` when `isBase64()` is false. Do not touch the contract, the provider or the model.

## Files
- `src/Transformers/Models/StorableMediaTransformer.php` (modified): new public static `isBase64String()` holding the rule; protected `isBase64()` delegates to it
- `tests/Unit/Transformers/Models/StorableMediaTransformerTest.php` (new): PHPUnit test extending `Henrotaym\LaravelTrustupMediaIoCommon\Tests\TestCase`, methods marked `#[Test]` like `tests/Unit/InstallingPackageTest.php`; resolve the transformer with `$this->app->make(StorableMediaTransformerContract::class)`

## Expected tests
Through `fromResource()` (assert `isBase64()` / `isString()` on the returned media):
- Nominal raw PDF: given `"%PDF-1.4\n; x data:a/b;base64,QQ==\x00\xff"`, when transformed → media is string, no exception [AC-1]
- Nominal data URI: given `'data:image/png;base64,'.base64_encode('hello')` → media is base64 [AC-2]
- Nominal bare base64: given `base64_encode('hello')` → media is base64 [AC-2]
- Address with marker: given `http://internal-host/x;base64,QQ==` → media is URL, not base64

Through `StorableMediaTransformer::isBase64String()` (data provider, AC-3):
- `data:image/png;base64,aGVsbG8=` → true
- `aGVsbG8=` → true
- `data:image/PNG;base64,QQ==` (capitals in media type only) → true
- `DATA:image/png;BASE64,QQ==` (capitals in markers) → false
- `http://internal-host/x;base64,QQ==` → false
- `http://internal-host/data:image/png;base64,QQ==` (data URI not at the start) → false
- `''` → false
- `data:image/png;base64,` (prefix with no content) → false
- `data:image/png;base64,not base64!` → false
- `data:application/pdf;name=x.pdf;base64,QQ==` (parameters before the marker) → false, no exception
- `aGVs\nbG8=` (valid only once line breaks are ignored) → false
- the raw PDF string above → false, no exception

## Verification
- [ ] `./cli test --filter=StorableMediaTransformerTest`: all tests pass
- [ ] `./cli test`: whole suite passes
- [ ] `./cli format --test`: no style errors
- [ ] `./cli analyze`: no errors
