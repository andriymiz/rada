<?php

namespace App\Services;

use App\Enums\MotionResult;
use App\Enums\VoteOption;
use InvalidArgumentException;
use Smalot\PdfParser\Parser;
use UnexpectedValueException;

class RollCallPdfParser
{
    /**
     * @return array{
     *     session: string|null,
     *     page_count: int,
     *     motions: list<array{
     *         question_number: int,
     *         project_number: string|null,
     *         title: string,
     *         result: string,
     *         counts: array{for: int, against: int, abstain: int, not_voting: int, absent: int},
     *         votes: list<array{name: string, result: string}>
     *     }>
     * }
     */
    public function parse(string $contents): array
    {
        if (! str_starts_with($contents, '%PDF-')) {
            throw new InvalidArgumentException('The uploaded file is not a valid PDF.');
        }

        $pages = array_values((new Parser)->parseContent($contents)->getPages());

        if ($pages === []) {
            throw new InvalidArgumentException('The PDF does not contain any pages.');
        }

        $session = null;
        if (preg_match('/\d+\s+сесія\s+\d+\s+скликання/u', $pages[0]->getText(), $sessionMatch) === 1) {
            $session = trim($sessionMatch[0]);
        }

        $motions = [];

        foreach ($pages as $pageIndex => $page) {
            $motions[] = $this->parseMotion($page->getText(), $pageIndex + 1);
        }

        return [
            'session' => $session,
            'page_count' => count($pages),
            'motions' => $motions,
        ];
    }

    /**
     * @return array{
     *     question_number: int,
     *     project_number: string|null,
     *     title: string,
     *     result: string,
     *     counts: array{for: int, against: int, abstain: int, not_voting: int, absent: int},
     *     votes: list<array{name: string, result: string}>
     * }
     */
    private function parseMotion(string $text, int $pageNumber): array
    {
        $lines = preg_split('/\R/u', $text) ?: [];
        $resultLineIndex = null;

        foreach ($lines as $lineIndex => $line) {
            if (preg_match('/РІШЕННЯ\s+(?:НЕ\s+)?ПРИЙНЯТО/u', $line) === 1) {
                $resultLineIndex = $lineIndex;
                break;
            }
        }

        if ($resultLineIndex === null) {
            throw new UnexpectedValueException("Could not find the voting result on PDF page {$pageNumber}.");
        }

        $questionNumber = $pageNumber;
        $questionLineIndex = null;

        foreach (array_slice($lines, 0, $resultLineIndex, preserve_keys: true) as $lineIndex => $line) {
            if (preg_match('/^\s*№\s*(\d+)\s*$/u', trim($line), $numberMatch) === 1) {
                $questionNumber = (int) $numberMatch[1];
                $questionLineIndex = $lineIndex;
                break;
            }
        }

        $titleStartIndex = $questionLineIndex !== null
            ? $questionLineIndex + 1
            : $this->findTitleStartIndex($lines, $resultLineIndex);

        if ($titleStartIndex === null) {
            throw new UnexpectedValueException("Could not find the agenda item title on PDF page {$pageNumber}.");
        }

        $projectNumber = null;
        $titleLines = [];

        for ($lineIndex = $titleStartIndex; $lineIndex < $resultLineIndex; $lineIndex++) {
            $line = trim(preg_replace('/[ \t]+/u', ' ', $lines[$lineIndex]) ?? $lines[$lineIndex]);

            if ($line === '' || preg_match('/^\s*№\s*\d+\s*$/u', $line) === 1) {
                continue;
            }

            if ($titleLines === [] && preg_match('/^\s*(?:\([^)]*\)\s*)?№\s*([\d\/.-]+)\s*(.*)$/u', $line, $projectMatch) === 1) {
                $projectNumber = $projectMatch[1];
                $line = trim($projectMatch[2]);
            } elseif ($titleLines === [] && preg_match('/^\s*\([^)]*\)\s*(.*)$/u', $line, $selectionMatch) === 1) {
                $line = trim($selectionMatch[1]);
            }

            if ($line !== '') {
                $titleLines[] = $line;
            }
        }

        $title = trim(preg_replace('/\s+/u', ' ', implode(' ', $titleLines)) ?? '');

        if ($title === '') {
            throw new UnexpectedValueException("Could not parse the agenda item title on PDF page {$pageNumber}.");
        }

        $counts = $this->parseCounts($text, $pageNumber);
        $votes = $this->parseVotes($text);
        $calculatedCounts = $this->countVotes($votes);

        if ($counts !== $calculatedCounts) {
            throw new UnexpectedValueException("The voting totals do not match the individual votes on PDF page {$pageNumber}.");
        }

        $result = str_contains($text, 'РІШЕННЯ НЕ ПРИЙНЯТО')
            ? MotionResult::NotPassed->getLabel()
            : MotionResult::Passed->getLabel();

        return [
            'question_number' => $questionNumber,
            'project_number' => $projectNumber,
            'title' => $title,
            'result' => $result,
            'counts' => $counts,
            'votes' => $votes,
        ];
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function findTitleStartIndex(array $lines, int $resultLineIndex): ?int
    {
        foreach (array_slice($lines, 0, $resultLineIndex, preserve_keys: true) as $lineIndex => $line) {
            if (preg_match('/^\s*\(За основу/u', $line) === 1 || preg_match('/^\s*Про\s/u', $line) === 1) {
                return $lineIndex;
            }
        }

        return null;
    }

    /**
     * @return array{for: int, against: int, abstain: int, not_voting: int, absent: int}
     */
    private function parseCounts(string $text, int $pageNumber): array
    {
        if (preg_match(
            '/ЗА\s*=\s*(\d+)\s*,\s*ПРОТИ\s*=\s*(\d+)\s*,\s*УТРИМАЛИСЬ\s*=\s*(\d+)\s*,\s*НЕ\s+ГОЛОСУВАЛИ\s*=\s*(\d+)\s*,\s*ВІДСУТНІХ\s*=\s*(\d+)/u',
            $text,
            $matches,
        ) !== 1) {
            throw new UnexpectedValueException("Could not parse the voting totals on PDF page {$pageNumber}.");
        }

        return [
            'for' => (int) $matches[1],
            'against' => (int) $matches[2],
            'abstain' => (int) $matches[3],
            'not_voting' => (int) $matches[4],
            'absent' => (int) $matches[5],
        ];
    }

    /** @return list<array{name: string, result: string}> */
    private function parseVotes(string $text): array
    {
        preg_match_all(
            '/(?<name>\p{Lu}[\p{L}’\'ʼ-]*[ \t]+[\p{Lu}\p{Ll}]\.[ \t]*[\p{Lu}\p{Ll}]\.)[ \t]*(?:\R[ \t]*)?-[ \t]*(?<result>Не[ \t]+голосував|Утримався|Відсутній|Проти|За)/u',
            $text,
            $voteMatches,
        );

        $votes = [];

        foreach ($voteMatches['name'] as $voteIndex => $name) {
            $votes[] = [
                'name' => trim(preg_replace('/\s+/u', ' ', $name) ?? $name),
                'result' => trim(preg_replace('/\s+/u', ' ', $voteMatches['result'][$voteIndex]) ?? $voteMatches['result'][$voteIndex]),
            ];
        }

        return $votes;
    }

    /**
     * @param  list<array{name: string, result: string}>  $votes
     * @return array{for: int, against: int, abstain: int, not_voting: int, absent: int}
     */
    private function countVotes(array $votes): array
    {
        $counts = [
            'for' => 0,
            'against' => 0,
            'abstain' => 0,
            'not_voting' => 0,
            'absent' => 0,
        ];

        foreach ($votes as $vote) {
            $key = match ($vote['result']) {
                VoteOption::Yes->getLabel() => 'for',
                VoteOption::No->getLabel() => 'against',
                VoteOption::Abstain->getLabel() => 'abstain',
                VoteOption::NotVoting->getLabel() => 'not_voting',
                VoteOption::Absent->getLabel() => 'absent',
                default => throw new UnexpectedValueException("Unknown individual vote result [{$vote['result']}]."),
            };

            $counts[$key]++;
        }

        return $counts;
    }
}
