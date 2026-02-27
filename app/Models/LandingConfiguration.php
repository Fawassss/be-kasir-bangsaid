<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingConfiguration extends Model
{
    protected $fillable = [
        'hero_image',
        'hero_description',
        'about_image_1',
        'about_image_2',
        'about_image_3',
        'about_image_4',
        'about_description',
    ];
}
