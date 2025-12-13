<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Laravel\Sanctum\HasApiTokens;

class Card extends Model
{
    use HasFactory, HasApiTokens;

    protected $fillable = [
        'child_first_name',
        'child_last_name',
        'parent_name',
        'phone',
        'expo_token',
        'status',
        'group_id',
        'person_type_id',
        'parent_code',
        'image_path',
        'active_garden_image',
        'parent_verification',
        'license',
        'deleted',
        'spam',
        'comment',
        'spam_comment',
        'is_deleted',
        'deleted_at',
        'free_calls_remaining',
    ];

    protected $casts = [
        'parent_verification' => 'boolean',
        'license' => 'array',
        'spam' => 'boolean',
        'is_deleted' => 'boolean',
        'deleted_at' => 'datetime',
        'free_calls_remaining' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($card) {
            if (empty($card->parent_code)) {
                $card->parent_code = self::generateParentCode();
            }
            // Set default free_calls_remaining to 5 if not set
            if (!isset($card->free_calls_remaining)) {
                $card->free_calls_remaining = 5;
            }
        });
    }

    /**
     * Generate a unique 6-character parent code
     */
    public static function generateParentCode()
    {
        do {
            // Generate 6-character code with letters and numbers only (more readable)
            $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            $code = '';
            
            for ($i = 0; $i < 6; $i++) {
                $code .= $characters[random_int(0, strlen($characters) - 1)];
            }
        } while (self::where('parent_code', $code)->exists());

        return $code;
    }

    /**
     * Set license as boolean
     */
    public function setLicenseBoolean($value)
    {
        $this->license = ['type' => 'boolean', 'value' => (bool) $value];
        return $this;
    }

    /**
     * Set license as date
     */
    public function setLicenseDate($date)
    {
        $this->license = ['type' => 'date', 'value' => $date instanceof Carbon ? $date->toDateString() : $date];
        return $this;
    }

    /**
     * Get license value
     */
    public function getLicenseValue()
    {
        if (!$this->license) {
            return null;
        }
        
        return $this->license['value'] ?? null;
    }

    /**
     * Get license type
     */
    public function getLicenseType()
    {
        if (!$this->license) {
            return null;
        }
        
        return $this->license['type'] ?? null;
    }

    /**
     * Check if license is boolean
     */
    public function isLicenseBoolean()
    {
        return $this->getLicenseType() === 'boolean';
    }

    /**
     * Check if license is date
     */
    public function isLicenseDate()
    {
        return $this->getLicenseType() === 'date';
    }

    public function group()
    {
        return $this->belongsTo(GardenGroup::class);
    }

    public function parents()
    {
        return $this->hasMany(ParentModel::class);
    }

    public function people()
    {
        return $this->hasMany(People::class);
    }

    public function personType()
    {
        return $this->belongsTo(\App\Models\PersonType::class);
    }

    public function calledCards()
    {
        return $this->hasMany(CalledCard::class);
    }

    /**
     * Get garden images through group relationship with full URLs
     */
    public function getGardenImagesAttribute()
    {
        if ($this->group && $this->group->garden) {
            return $this->group->garden->images->sortBy('index')->map(function ($image) {
                return [
                    'id' => $image->id,
                    'title' => $image->title,
                    'image' => $image->image,
                    'image_url' => $image->image_url,
                    'index' => $image->index,
                    'created_at' => $image->created_at,
                    'updated_at' => $image->updated_at,
                ];
            });
        }
        return collect();
    }

    /**
     * Get garden information through group relationship
     */
    public function getGardenAttribute()
    {
        if ($this->group && $this->group->garden) {
            return $this->group->garden;
        }
        return null;
    }

    /**
     * Get full URL for image_path
     */
    public function getImageUrlAttribute()
    {
        if ($this->image_path) {
            return url('storage/' . $this->image_path);
        }
        return null;
    }

    /**
     * Check if the card's garden is paid (not free)
     */
    public function isGardenPaid()
    {
        if (!$this->group || !$this->group->garden) {
            return false;
        }

        $garden = $this->group->garden;
        if (!$garden->countryData) {
            return false;
        }

        return !$garden->countryData->isFree();
    }

    /**
     * Check if the card has a valid license
     */
    public function hasValidLicense()
    {
        if (!$this->license) {
            return false;
        }

        $licenseType = $this->getLicenseType();
        $licenseValue = $this->getLicenseValue();

        if ($licenseType === 'boolean') {
            return (bool) $licenseValue;
        }

        if ($licenseType === 'date') {
            try {
                $licenseDate = Carbon::parse($licenseValue);
                return $licenseDate->isFuture();
            } catch (\Exception $e) {
                return false;
            }
        }

        return false;
    }

    /**
     * Check if the card can make unlimited notification calls
     * (either garden is free or card has valid license)
     */
    public function canMakeUnlimitedCalls()
    {
        return !$this->isGardenPaid() || $this->hasValidLicense();
    }

    /**
     * Soft delete the card (mark as deleted)
     */
    public function softDelete()
    {
        $this->update([
            'is_deleted' => true,
            'deleted_at' => now(),
        ]);
    }

    /**
     * Restore the card (mark as not deleted)
     */
    public function restore()
    {
        $this->update([
            'is_deleted' => false,
            'deleted_at' => null,
        ]);
    }

    /**
     * Check if card is deleted
     */
    public function isDeleted()
    {
        return $this->is_deleted;
    }

    /**
     * Check if card can be restored (deleted within 20 days)
     */
    public function canBeRestored()
    {
        if (!$this->is_deleted || !$this->deleted_at) {
            return false;
        }

        return $this->deleted_at->diffInDays(now()) <= 20;
    }

    /**
     * Get days since deletion
     */
    public function getDaysSinceDeletion()
    {
        if (!$this->is_deleted || !$this->deleted_at) {
            return null;
        }

        return $this->deleted_at->diffInDays(now());
    }

    /**
     * Scope to get only non-deleted cards
     */
    public function scopeNotDeleted($query)
    {
        return $query->where('is_deleted', false);
    }

    /**
     * Scope to get only deleted cards
     */
    public function scopeDeleted($query)
    {
        return $query->where('is_deleted', true);
    }

    /**
     * Scope to get cards that can be restored (deleted within 20 days)
     */
    public function scopeRestorable($query)
    {
        return $query->where('is_deleted', true)
                    ->where('deleted_at', '>=', now()->subDays(20));
    }

    /**
     * Get the country tariff for this card's garden
     */
    public function getCountryTariff()
    {
        if (!$this->group || !$this->group->garden || !$this->group->garden->countryData) {
            return 0;
        }
        
        return $this->group->garden->countryData->tariff ?? 0;
    }

    /**
     * Check if free calls should be managed (country has tariff > 0)
     */
    public function shouldManageFreeCalls()
    {
        return $this->getCountryTariff() > 0;
    }

    /**
     * Decrement free calls remaining (only if country has tariff and no valid license)
     * Returns true if call is allowed, false otherwise
     */
    public function decrementFreeCalls()
    {
        // If country is free or has valid license, unlimited calls allowed
        if (!$this->shouldManageFreeCalls() || $this->hasValidLicense()) {
            return true;
        }

        // Check if there are free calls remaining
        if ($this->free_calls_remaining <= 0) {
            return false;
        }

        // Decrement and save
        $this->decrement('free_calls_remaining');
        return true;
    }

    /**
     * Reset free calls to default (5)
     */
    public function resetFreeCalls()
    {
        $this->update(['free_calls_remaining' => 5]);
    }

    /**
     * Get payment amount in country's currency
     * Converts payment gateway currency tariff to the country's currency using NBG exchange rate
     * 
     * @return array|null
     */
    public function getPaymentAmountInCurrency()
    {
        if (!$this->group || !$this->group->garden || !$this->group->garden->countryData) {
            return null;
        }

        $country = $this->group->garden->countryData;
        $tariff = $country->tariff ?? 0;
        $countryCurrency = $country->currency ?? 'GEL';

        // If tariff is 0, return as is
        if ($tariff == 0) {
            return [
                'amount' => $tariff,
                'currency' => $countryCurrency,
                'original_tariff' => $tariff
            ];
        }

        // Get payment gateway currency
        $paymentGateway = $country->paymentGateway;
        $paymentGatewayCurrency = $paymentGateway ? ($paymentGateway->currency ?? 'GEL') : 'GEL';

        // If payment gateway currency is the same as country currency, return as is
        if ($paymentGatewayCurrency === $countryCurrency) {
            return [
                'amount' => $tariff,
                'currency' => $countryCurrency,
                'original_tariff' => $tariff,
                'payment_gateway_currency' => $paymentGatewayCurrency
            ];
        }

        // Convert from payment gateway currency to country currency using NBG rates
        try {
            $nbgService = app(\App\Services\NbgCurrencyService::class);
            
            // Get exchange rates for both currencies
            $paymentGatewayRateResult = $nbgService->getExchangeRate($paymentGatewayCurrency);
            $countryCurrencyRateResult = $nbgService->getExchangeRate($countryCurrency);

            if ($paymentGatewayRateResult['success'] && isset($paymentGatewayRateResult['rate']) &&
                $countryCurrencyRateResult['success'] && isset($countryCurrencyRateResult['rate'])) {
                
                $paymentGatewayRate = $paymentGatewayRateResult['rate']; // e.g., 2.7 GEL per 1 USD
                $countryCurrencyRate = $countryCurrencyRateResult['rate']; // e.g., 0.002 GEL per 1 KZT
                
                // Convert: tariff_in_payment_currency * (payment_currency_rate / country_currency_rate)
                // Example: 4 USD * (2.7 / 0.002) = 4 * 1350 = 5400 KZT
                $amountInCountryCurrency = round($tariff * ($paymentGatewayRate / $countryCurrencyRate), 2);

                return [
                    'amount' => $amountInCountryCurrency,
                    'currency' => $countryCurrency,
                    'original_tariff' => $tariff,
                    'payment_gateway_currency' => $paymentGatewayCurrency,
                ];
            }
        } catch (\Exception $e) {
            \Log::error('Error getting exchange rate for card payment', [
                'card_id' => $this->id,
                'country_currency' => $countryCurrency,
                'payment_gateway_currency' => $paymentGatewayCurrency,
                'error' => $e->getMessage()
            ]);
        }

        // Fallback: return tariff in payment gateway currency if exchange rate fails
        return [
            'amount' => $tariff,
            'currency' => $paymentGatewayCurrency,
            'original_tariff' => $tariff,
            'error' => 'Exchange rate not available'
        ];
    }
}
