<?php

namespace Tests\Unit;

use App\Services\PdfTextExtractor;
use Tests\TestCase;

class PdfTextExtractorTest extends TestCase
{
    public function test_pdf_text_layer_is_extractable(): void
    {
        $pages = (new PdfTextExtractor())->pages(base_path('сесія 99.pdf'));

        $this->assertCount(44, $pages);
        $this->assertStringContainsString('сесія восьмого скликання', $pages[0]);
        $this->assertStringContainsString('№7760', $pages[1]);
        $this->assertStringContainsString('РІШЕННЯ ПРИЙНЯТО', $pages[1]);
    }
}
