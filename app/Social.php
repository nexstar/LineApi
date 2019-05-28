<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Social extends Model
{
    use SoftDeletes;

    /**
         * The table associated with the model.
         *
         * @var string
         */
    protected $table = 'socials';

    /**
         * The attributes that aren't mass assignable.
         *
         * @var array
         */
    protected $guarded = [];
}
