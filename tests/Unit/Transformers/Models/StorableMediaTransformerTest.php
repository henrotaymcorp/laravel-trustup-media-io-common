<?php
namespace Henrotaym\LaravelTrustupMediaIoCommon\Tests\Unit\Transformers\Models;

use Henrotaym\LaravelTrustupMediaIoCommon\Contracts\Transformers\Models\StorableMediaTransformerContract;
use Henrotaym\LaravelTrustupMediaIoCommon\Tests\TestCase;
use Henrotaym\LaravelTrustupMediaIoCommon\Transformers\Models\StorableMediaTransformer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class StorableMediaTransformerTest extends TestCase
{
    protected function transformer(): StorableMediaTransformerContract
    {
        return $this->app->make(StorableMediaTransformerContract::class);
    }

    #[Test]
    public function raw_pdf_containing_base64_marker_is_a_string(): void
    {
        $media = $this->transformer()->fromResource("%PDF-1.4\n; x data:a/b;base64,QQ==\x00\xff");

        $this->assertTrue($media->isString());
        $this->assertFalse($media->isBase64());
    }

    #[Test]
    public function address_containing_base64_marker_is_a_url(): void
    {
        $media = $this->transformer()->fromResource("http://internal-host/x;base64,QQ==");

        $this->assertTrue($media->isUrl());
        $this->assertFalse($media->isBase64());
    }

    #[Test]
    public function data_uri_is_base64(): void
    {
        $media = $this->transformer()->fromResource("data:image/png;base64,".base64_encode("hello"));

        $this->assertTrue($media->isBase64());
    }

    #[Test]
    public function bare_base64_is_base64(): void
    {
        $media = $this->transformer()->fromResource(base64_encode("hello"));

        $this->assertTrue($media->isBase64());
    }

    public static function base64Strings(): array
    {
        return [
            'data uri' => ['data:image/png;base64,aGVsbG8='],
            'bare base64' => ['aGVsbG8='],
            'capitals in media type only' => ['data:image/PNG;base64,QQ=='],
        ];
    }

    #[Test]
    #[DataProvider('base64Strings')]
    public function string_is_recognised_as_base64(string $value): void
    {
        $this->assertTrue(StorableMediaTransformer::isBase64String($value));
    }

    public static function emptyContents(): array
    {
        return [
            'empty string' => [''],
            'prefix with no content' => ['data:image/png;base64,'],
        ];
    }

    #[Test]
    #[DataProvider('emptyContents')]
    public function empty_content_is_not_recognised_as_base64(string $value): void
    {
        $this->assertFalse(StorableMediaTransformer::isBase64String($value));
    }
}
