<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Model;

class Dister extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Boot method to set default values
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($dister) {
            if (empty($dister->referral)) {
                $dister->referral = self::generateUniqueReferralCode();
            }
        });
    }

    protected $fillable = [
        'email',
        'phone',
        'password',
        'first_name',
        'last_name',
        'country_id',
        'gardens',
        'percent',
        'main_dister',
        'balance',
        'balance_comment',
        'iban',
        'referral',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'main_dister',
    ];

    protected $casts = [
        'gardens' => 'array',
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'percent' => 'decimal:2',
        'main_dister' => 'array',
        'balance' => 'decimal:2',
    ];

    /**
     * Get the country that owns the dister.
     */
    public function country()
    {
        return $this->belongsTo(Country::class);
    }



    /**
     * Get the gardens associated with the dister.
     */
    public function gardenList()
    {
        return $this->belongsToMany(Garden::class, 'dister_gardens');
    }

    /**
     * Get the countries associated with the dister.
     */
    public function countries()
    {
        return $this->hasMany(Country::class);
    }

    /**
     * Get the full name of the dister.
     */
    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName()
    {
        return 'id';
    }

    /**
     * Find dister by garden email
     */
    public static function findByGardenEmail($email)
    {
        return static::whereJsonContains('gardens', ['email' => $email])->first();
    }

    /**
     * Check if dister has access to specific garden
     */
    public function hasGardenAccess($gardenId)
    {
        if (is_array($this->gardens)) {
            return in_array($gardenId, $this->gardens);
        }
        return false;
    }

    /**
     * Get formatted balance display
     */
    public function getFormattedBalanceAttribute()
    {
        if ($this->balance === null) {
            return null;
        }
        return number_format($this->balance, 2) . ' ₾';
    }

    /**
     * Get formatted IBAN display
     */
    public function getFormattedIbanAttribute()
    {
        if ($this->iban === null) {
            return null;
        }
        // Format IBAN with spaces every 4 characters for better readability
        return trim(chunk_split($this->iban, 4, ' '));
    }

    /**
     * Validate IBAN format
     */
    public static function validateIban($iban)
    {
        if (empty($iban)) {
            return false;
        }
        
        // Remove spaces and convert to uppercase
        $iban = strtoupper(str_replace(' ', '', $iban));
        
        // Basic IBAN format validation (2 letters + 2 digits + up to 30 alphanumeric)
        if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{1,30}$/', $iban)) {
            return false;
        }
        
        return true;
    }

    /**
     * Generate unique referral code
     */
    public static function generateUniqueReferralCode()
    {
        do {
            $code = strtoupper(substr(uniqid(), -6));
        } while (self::where('referral', $code)->exists());
        return $code;
    }
}
