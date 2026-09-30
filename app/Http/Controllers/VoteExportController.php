<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VoteExportController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, [
                'session_number',
                'session_date',
                'session_title',
                'question_number',
                'question_title',
                'deputy_external_id',
                'deputy_name',
                'result',
                'notes',
                'confirmed_at',
            ], ',', '"', '');

            DB::table('roll_call_votes as votes')
                ->join('questions', 'questions.id', '=', 'votes.question_id')
                ->join('council_sessions as sessions', 'sessions.id', '=', 'questions.session_id')
                ->join('deputies', 'deputies.id', '=', 'votes.deputy_id')
                ->whereNotNull('votes.confirmed_at')
                ->orderBy('sessions.held_at')
                ->orderBy('sessions.id')
                ->orderBy('questions.id')
                ->orderBy('votes.id')
                ->select([
                    'sessions.session_number',
                    'sessions.held_at',
                    'sessions.title as session_title',
                    'questions.question_number',
                    'questions.title as question_title',
                    'deputies.external_id as deputy_external_id',
                    'deputies.name as deputy_name',
                    'votes.result',
                    'votes.notes',
                    'votes.confirmed_at',
                ])
                ->cursor()
                ->each(static function (object $vote) use ($output): void {
                    fputcsv($output, [
                        $vote->session_number,
                        $vote->held_at,
                        $vote->session_title,
                        $vote->question_number,
                        $vote->question_title,
                        $vote->deputy_external_id,
                        $vote->deputy_name,
                        $vote->result,
                        $vote->notes,
                        $vote->confirmed_at,
                    ], ',', '"', '');
                });

            fclose($output);
        }, 'roll-call-votes.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
