<?php

/*
 * Value Model
 * The model name must be the same name of the table but in the singular
 * Else : protected $table = 'name_table'
 */
namespace APP\MODELS;

use \Illuminate\Database\Eloquent\Model as Eloquent;

class Value extends Eloquent
{

    protected $table = 'values';

    public function trackers(){
        return $this->hasMany('\APP\MODELS\TrackerValue', 'value_id');
    }

    public function stores()
    {
        return $this->belongsTo('\APP\MODELS\Store', 'value_id');
    }

    public function users()
    {
        return $this->belongsTo('\APP\MODELS\User', 'user_id');
    }
}