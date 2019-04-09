<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Arquivo extends Model
{
    protected $table = 'files';
    protected $fillable = ['nome', 'hash'];
}
