<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class LicenseValueRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Get the license type from the request
        $licenseType = request()->input('license.type');
        
        // If no license type is provided, skip validation
        if (!$licenseType) {
            return;
        }
        
        // Validate based on type
        if ($licenseType === 'boolean') {
            // Check if value is boolean or can be converted to boolean
            $validBooleanValues = [true, false, 'true', 'false', '1', '0', 1, 0];
            if (!in_array($value, $validBooleanValues, true)) {
                $fail('License value must be a boolean (true/false) when license type is boolean.');
            }
        } elseif ($licenseType === 'date') {
            if (!is_string($value) || !strtotime($value)) {
                $fail('License value must be a valid date string when license type is date.');
            }
        } else {
            $fail('Invalid license type. Must be either "boolean" or "date".');
        }
    }
}
