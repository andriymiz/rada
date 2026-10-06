<?php

use App\Services\RollCallPdfParser;

it('parses agenda items, vote totals, and individual votes from the test PDF', function () {
    $pdfContents = file_get_contents(dirname(__DIR__, 2).'/Fixtures/roll-call-votes-99.pdf');

    if ($pdfContents === false) {
        throw new RuntimeException('Could not read the roll-call PDF fixture.');
    }

    $result = (new RollCallPdfParser)->parse($pdfContents);

    expect($result['session'])->toBe('99 сесія 8 скликання');
    expect($result['page_count'])->toBe(6);
    expect(array_column($result['motions'], 'question_number'))->toBe([1, 2, 3, 4, 5, 6]);
    expect(array_column($result['motions'], 'project_number'))->toBe([null, '100', '101', '102', '103', '104']);
    expect(array_column($result['motions'], 'result'))->toBe([
        'Прийнято',
        'Прийнято',
        'Прийнято',
        'Прийнято',
        'Прийнято',
        'Не прийнято',
    ]);
    expect($result['motions'][0]['title'])->toBe('Про затвердження порядку денного');
    expect($result['motions'][5]['counts'])->toBe([
        'for' => 5,
        'against' => 1,
        'abstain' => 0,
        'not_voting' => 5,
        'absent' => 5,
    ]);
    expect($result['motions'][0]['votes'])->toHaveCount(16);
    expect($result['motions'][0]['votes'][0])->toBe([
        'name' => 'Бігус М.Б.',
        'result' => 'Відсутній',
    ]);
    expect($result['motions'][5]['votes'][14]['result'])->toBe('Проти');
});

it('rejects content that is not a PDF', function () {
    expect(fn () => (new RollCallPdfParser)->parse('not a PDF'))
        ->toThrow(InvalidArgumentException::class, 'The uploaded file is not a valid PDF.');
});
