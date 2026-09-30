<?php

use App\Services\PdfTextExtractor;

test('pdf text layer is extractable', function () {
    $pages = (new PdfTextExtractor)->pages(base_path('сесія 99.pdf'));

    $this->assertCount(44, $pages);
    $this->assertStringContainsString('сесія восьмого скликання', $pages[0]);
    $this->assertStringContainsString('№7760', $pages[1]);
    $this->assertStringContainsString('РІШЕННЯ ПРИЙНЯТО', $pages[1]);
});
