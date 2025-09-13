<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    // User type constants
    const TYPE_USER = 'user';
    const TYPE_GARDEN = 'garden';
    const TYPE_DISTER = 'dister';
    const TYPE_ADMIN = 'admin';
    const TYPE_ACCOUNTANT = 'accountant';
    const TYPE_TECHNICAL = 'technical';

    // User status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'type',
        'balance',
        'status',
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

    protected $appends = ['type_display', 'status_display'];

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
            'balance' => 'decimal:2',
        ];
    }

    /**
     * Get all available user types
     */
    public static function getUserTypes()
    {
        return [
            self::TYPE_USER => 'მომხმარებელი',
            self::TYPE_GARDEN => 'ბაღი',
            self::TYPE_DISTER => 'დისტერი',
            self::TYPE_ADMIN => 'ადმინისტრატორი',
            self::TYPE_ACCOUNTANT => 'ბუღალტერი',
            self::TYPE_TECHNICAL => 'ტექნიკური პირი',
        ];
    }

    /**
     * Get user type display name
     */
    public function getTypeDisplayAttribute()
    {
        $types = self::getUserTypes();
        return $types[$this->type] ?? $this->type;
    }

    /**
     * Get all available user statuses
     */
    public static function getUserStatuses()
    {
        return [
            self::STATUS_ACTIVE => 'აქტიური',
            self::STATUS_INACTIVE => 'არააქტიური',
        ];
    }

    /**
     * Get user status display name
     */
    public function getStatusDisplayAttribute()
    {
        $statuses = self::getUserStatuses();
        return $statuses[$this->status] ?? $this->status;
    }

    /**
     * Check if user is active
     */
    public function isActive()
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if user is inactive
     */
    public function isInactive()
    {
        return $this->status === self::STATUS_INACTIVE;
    }

    /**
     * Activate user
     */
    public function activate()
    {
        $this->update(['status' => self::STATUS_ACTIVE]);
    }

    /**
     * Deactivate user
     */
    public function deactivate()
    {
        $this->update(['status' => self::STATUS_INACTIVE]);
    }
}
