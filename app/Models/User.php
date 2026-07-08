<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, HasUuids, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'contact_number',
        'profile_image',
        'is_active',
        'token_version',
        'department_id',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'token_version' => 'integer',
        ];
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function addresses()
    {
        return $this->belongsToMany(Address::class, 'user_addresses');
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class, 'authorized_by_id');
    }
}
