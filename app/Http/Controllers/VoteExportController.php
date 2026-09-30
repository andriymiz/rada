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
            ->selectRaw('COUNT(*) AS votes_total')
            ->selectRaw('SUM(CASE WHEN result = ? THEN 1 ELSE 0 END) AS votes_for', ['for'])
            ->selectRaw('SUM(CASE WHEN result = ? THEN 1 ELSE 0 END) AS votes_against', ['against'])
            ->selectRaw('SUM(CASE WHEN result = ? THEN 1 ELSE 0 END) AS votes_abstained', ['abstained'])
            ->selectRaw('SUM(CASE WHEN result = ? THEN 1 ELSE 0 END) AS votes_not_voted', ['not_voted'])
            ->selectRaw('SUM(CASE WHEN result = ? THEN 1 ELSE 0 END) AS votes_absent', ['absent'])
            ->selectRaw('SUM(CASE WHEN result = ? THEN 1 ELSE 0 END) AS votes_unknown', ['unknown'])
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
                'sessions.title as session_title',
                'questions.question_number as motion_number',
                'questions.title as motion_text',
                'totals.votes_total',
                'totals.votes_for',
                'totals.votes_against',
                'totals.votes_abstained',
                'totals.votes_not_voted',
                'totals.votes_absent',
                'totals.votes_unknown',
            ])
            ->cursor();

        return $this->download(
            'motions.csv',
            [
                'motion_id',
                'session_id',
                'session_number',
                'session_date',
                'session_title',
                'motion_number',
                'motion_text',
                'votes_total',
                'votes_for',
                'votes_against',
                'votes_abstained',
                'votes_not_voted',
                'votes_absent',
                'votes_unknown',
            ],
            $rows,
            static fn (object $motion): array => [
                $motion->motion_id,
                $motion->session_id,
                $motion->session_number,
                $motion->session_date,
                $motion->session_title,
                $motion->motion_number,
                $motion->motion_text,
                $motion->votes_total ?? 0,
                $motion->votes_for ?? 0,
                $motion->votes_against ?? 0,
                $motion->votes_abstained ?? 0,
                $motion->votes_not_voted ?? 0,
                $motion->votes_absent ?? 0,
                $motion->votes_unknown ?? 0,
            ],
        );
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
                'votes.id as voting_id',
                'questions.id as motion_id',
                'deputies.external_id as deputy_external_id',
                'deputies.name as deputy_name',
                'votes.result',
            ])
            ->cursor();

        return $this->download(
            'votings.csv',
            ['voting_id', 'motion_id', 'deputy_external_id', 'deputy_name', 'vote'],
            $rows,
            static fn (object $voting): array => [
                $voting->voting_id,
                $voting->motion_id,
                $voting->deputy_external_id,
                $voting->deputy_name,
                $voting->result,
            ],
        );
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
