<?php

namespace App\Enums;

enum SourceDocumentStatus: string
{
    case Uploaded = 'uploaded';
    case Processing = 'processing';
    case Processed = 'processed';
    case Rejected = 'rejected';
}
