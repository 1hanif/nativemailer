<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Email extends Model
{
    protected $fillable = [
        'from',
        'to',
        'subject',
        'body_text',
        'body_html',
        'attachments',
        'raw',
        'received_at'
    ];

    protected $casts = [
        'attachments' => 'array',
        'received_at' => 'datetime',
    ];
}
