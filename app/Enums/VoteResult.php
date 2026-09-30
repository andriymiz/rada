<?php

namespace App\Enums;

enum VoteResult: string
{
    case For = 'for';
    case Against = 'against';
    case Abstained = 'abstained';
    case NotVoted = 'not_voted';
    case Absent = 'absent';
    case Unknown = 'unknown';
}
