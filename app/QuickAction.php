<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuickAction extends Model
{
    use HasFactory;

    protected $table = 'quick_action';

    protected $fillable = [
        'user_id',
        'quick_action_text',
    ];

    public $timestamps = true;
}
