<?php

namespace App\Enums;

enum SmsGateway: int
{
    case GEO_SMS_UBILL = 1;
    case GLOBAL_INTERGO = 2;

    public function name(): string
    {
        return match($this) {
            self::GEO_SMS_UBILL => 'Geo Sms - ubill',
            self::GLOBAL_INTERGO => 'Global Intergo',
        };
    }

    public function getBaseUrl(): string
    {
        return match($this) {
            self::GEO_SMS_UBILL => 'https://api.ubill.dev/v1/sms/send',
            self::GLOBAL_INTERGO => 'https://api.intergo.com/v1/sms/send', // Adjust this URL as needed
        };
    }

    public static function fromName(string $name): ?self
    {
        return match($name) {
            'Geo Sms - ubill' => self::GEO_SMS_UBILL,
            'Global Intergo' => self::GLOBAL_INTERGO,
            default => null,
        };
    }

    public static function options(): array
    {
        return [
            self::GEO_SMS_UBILL->value => self::GEO_SMS_UBILL->name(),
            self::GLOBAL_INTERGO->value => self::GLOBAL_INTERGO->name(),
        ];
    }
}
