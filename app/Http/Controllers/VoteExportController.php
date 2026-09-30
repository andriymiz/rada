<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VoteExportController extends Controller
{
    public function motions(): StreamedResponse
    {
        $totals = DB::table('roll_call_votes')
            ->whereNotNull('confirmed_at')
            ->select('question_id')
            ->selectRaw('SUM(CASE WHEN result = ? THEN 1 ELSE 0 END) AS voting_for', ['for'])
            ->selectRaw('SUM(CASE WHEN result = ? THEN 1 ELSE 0 END) AS voting_against', ['against'])
            ->selectRaw('SUM(CASE WHEN result = ? THEN 1 ELSE 0 END) AS voting_abstain', ['abstained'])
            ->selectRaw('SUM(CASE WHEN result = ? THEN 1 ELSE 0 END) AS not_voting', ['not_voted'])
            ->selectRaw('SUM(CASE WHEN result = ? THEN 1 ELSE 0 END) AS absent', ['absent'])
            ->groupBy('question_id');

        $rows = DB::table('questions')
            ->join('council_sessions as sessions', 'sessions.id', '=', 'questions.session_id')
            ->leftJoinSub($totals, 'totals', 'totals.question_id', '=', 'questions.id')
            ->orderBy('sessions.held_at')
            ->orderBy('sessions.id')
            ->orderBy('questions.id')
            ->select([
                'questions.id as motion_id',
                'sessions.id as session_id',
                'sessions.session_number',
                'sessions.held_at as session_date',
                'questions.question_number',
                'questions.title',
                'questions.project_number',
                'questions.voting_result',
                'questions.decision_document_url',
                'questions.decision_document_name',
                'totals.voting_for',
                'totals.voting_against',
                'totals.voting_abstain',
                'totals.not_voting',
                'totals.absent',
            ])
            ->cursor();

        $headers = [
            'uid',
            'date',
            'authorityName',
            'authorityId',
            'authorityCattutc',
            'convocation',
            'legislativeSession',
            'number',
            'title',
            'projectNumber',
            'votingFor',
            'votingAgainst',
            'votingAbstain',
            'notVoting',
            'absent',
            'votingResult',
            'textUrl',
            'text',
        ];

        return $this->download('motions.csv', $headers, $rows, function (object $motion): array {
            $votingResult = $motion->voting_result;
            if ($votingResult === null && $motion->voting_for === null) {
                $votingResult = 'Не розглядали';
            } elseif (
                $votingResult === null
                && (int) $motion->voting_for === 0
                && (int) $motion->voting_against === 0
                && (int) $motion->voting_abstain === 0
                && ((int) $motion->not_voting > 0 || (int) $motion->absent > 0)
            ) {
                $votingResult = 'Не голосували';
            }

            return [
                $this->motionUid($motion->session_date, $motion->session_id, $motion->question_number),
                $motion->session_date,
                config('rada.open_data.authority_name'),
                config('rada.open_data.authority_id'),
                config('rada.open_data.authority_cattutc'),
                config('rada.open_data.convocation'),
                $motion->session_number,
                preg_match('/^\d+$/D', $motion->question_number) === 1 ? (int) $motion->question_number : '',
                $motion->title,
                $motion->project_number,
                $motion->voting_for,
                $motion->voting_against,
                $motion->voting_abstain,
                $motion->not_voting,
                $motion->absent,
                $votingResult,
                is_string($motion->decision_document_url)
                    && preg_match('/^https?:\/\//i', $motion->decision_document_url) === 1
                    ? $motion->decision_document_url
                    : '',
                $motion->decision_document_name,
            ];
        });
    }

    public function votings(): StreamedResponse
    {
        $rows = DB::table('roll_call_votes as votes')
            ->join('questions', 'questions.id', '=', 'votes.question_id')
            ->join('council_sessions as sessions', 'sessions.id', '=', 'questions.session_id')
            ->join('deputies', 'deputies.id', '=', 'votes.deputy_id')
            ->whereNotNull('votes.confirmed_at')
            ->orderBy('sessions.held_at')
            ->orderBy('sessions.id')
            ->orderBy('questions.id')
            ->orderBy('votes.id')
            ->select([
                'sessions.id as session_id',
                'sessions.held_at as session_date',
                'questions.question_number',
                'questions.title as motion_title',
                'deputies.external_id as voter_id',
                'deputies.name as voter_name',
                'votes.result',
            ])
            ->cursor();

        return $this->download(
            'votings.csv',
            ['motionUid', 'voterId', 'voterName', 'motionTitle', 'result'],
            $rows,
            fn (object $voting): array => [
                $this->motionUid($voting->session_date, $voting->session_id, $voting->question_number),
                $voting->voter_id,
                $voting->voter_name,
                $voting->motion_title,
                match ($voting->result) {
                    'for' => 'За',
                    'against' => 'Проти',
                    'abstained' => 'Утримався',
                    'not_voted' => 'Не голосував',
                    'absent' => 'Відсутній',
                    default => '',
                },
            ],
        );
    }

    private function motionUid(?string $date, int $sessionId, string $number): string
    {
        return ($date ?? 'session-'.$sessionId).'-'.$sessionId.'-'.$number;
    }

    private function download(string $filename, array $headers, iterable $rows, callable $columns): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows, $columns): void {
            $output = fopen('php://output', 'w');

            try {
                fputcsv($output, $headers, ',', '"', '');

                foreach ($rows as $row) {
                    fputcsv($output, $columns($row), ',', '"', '');
                }
            } finally {
                fclose($output);
            }
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
