<?php

declare(strict_types=1);

namespace Xfa\Pdf\Models;

use Illuminate\Database\Eloquent\Model;

class XfaDocument extends Model
{
    protected $table = 'xfa_documents';

    protected $fillable = [
        'name',
        'original_filename',
        'file_path',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}
