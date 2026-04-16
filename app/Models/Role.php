<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = ['name', 'slug', 'is_admin', 'level'];

    protected function casts(): array
    {
        return [
            'is_admin' => 'boolean',
            'level'    => 'integer',
        ];
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
