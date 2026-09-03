<?php

namespace Webkul\Sandbox\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\Sandbox\Contracts\Note as NoteContract;

class Note extends Model implements NoteContract
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'sandbox_notes';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'body',
    ];
}
