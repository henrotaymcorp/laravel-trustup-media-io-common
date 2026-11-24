<?php
namespace Henrotaym\LaravelTrustupMediaIoCommon\Tests\Unit\Transformers\Models;

use Henrotaym\LaravelTrustupMediaIoCommon\Contracts\Transformers\Models\StorableMediaTransformerContract;
use Henrotaym\LaravelTrustupMediaIoCommon\Tests\TestCase;
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
}
