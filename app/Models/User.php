<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
// use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;                        
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'profile_image',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'profile_image_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }


    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            if (empty($user->uuid)) {
                $user->uuid = static::generateUuid();
            }
        });
    }

    public static function generateUuid(): string
    {
        do {
            $uuid = str_pad((string) mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);
            try {
                $exists = static::where('uuid', $uuid)->exists();
            } catch (\Throwable $e) {
                $exists = false;
            }
        } while ($exists);

        return $uuid;
    }

    public function ensureUuid(): string
    {
        if (! empty($this->uuid)) {
            return $this->uuid;
        }

        $this->forceFill([
            'uuid' => static::generateUuid(),
        ])->saveQuietly();

        return $this->uuid;
    }

    public function getProfileImageUrlAttribute(): string
    {
        if (! empty($this->profile_image)) {
            return asset('storage/' . ltrim($this->profile_image, '/'));
        }

        return asset('assets/img/users/user-08.jpg');
    }

    /**
     * Send the email verification notification with custom branding.
     */
    public function sendEmailVerificationNotification()
    {
        $this->notify(new \App\Notifications\CustomVerifyEmail());
    }


//     public function isProfileComplete(): bool
// {
//     if ($this->role === 'doctor') {
//         return $this->doctor &&
//             $this->doctor->specialization &&
//             $this->doctor->phone;
//     }

//     if ($this->role === 'patient') {
//         return $this->patient &&
//             $this->patient->age &&
//             $this->patient->phone &&
//             $this->patient->gender &&
//             $this->patient->blood_group;
//     }

//     return true;
// }



    public function admin()
    {
        return $this->hasOne(Admin::class);
    }

    public function doctor()
    {
        return $this->hasOne(Doctor::class);
    }

    public function patient()
    {
        return $this->hasOne(Patient::class);
    }
}
