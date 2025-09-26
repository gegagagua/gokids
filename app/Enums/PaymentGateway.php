<?php

namespace App\Enums;

enum PaymentGateway: int
{
    case BOG = 1;
    case BOG_USD = 2;
    case BOG_EUR = 3;

    public function name(): string
    {
        return match($this) {
            self::BOG => 'BOG',
            self::BOG_USD => 'BOG - USD',
            self::BOG_EUR => 'BOG - EUR',
        };
    }

    public function getBaseUrl(): string
    {
        return match($this) {
            self::BOG, self::BOG_USD, self::BOG_EUR => 'https://api.bog.ge/v1/payment', // Adjust this URL as needed
        };
    }

    public static function fromName(string $name): ?self
    {
        return match($name) {
            'BOG' => self::BOG,
            'BOG - USD' => self::BOG_USD,
            'BOG - EUR' => self::BOG_EUR,
            default => null,
        };
    }

    public static function options(): array
    {
        return [
            self::BOG->value => self::BOG->name(),
            self::BOG_USD->value => self::BOG_USD->name(),
            self::BOG_EUR->value => self::BOG_EUR->name(),
        ];
    }

    public function currency(): string
    {
        return match($this) {
            self::BOG => 'GEL',
            self::BOG_USD => 'USD',
            self::BOG_EUR => 'EUR',
        };
    }
}
