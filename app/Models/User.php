<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens,HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'contact_no',
        'otp',
        'user_info_flag'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function user_profile()
    {
        return $this->hasOne(UserProfile::class,'user_id','id');
    }

    public function properties()
    {
        return $this->hasMany(UserProperty::class,'user_id','id');
    }

    public function customer_properties()
    {
        return $this->hasMany(CustomerProperty::class,'user_id','id');
    }

    public function company_detail()
    {
        return $this->hasOne(CompanyDetail::class,'user_id','id');
    }
}
