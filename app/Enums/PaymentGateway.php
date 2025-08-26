<?php

namespace App\Enums;

enum PaymentGateway: int
{
    case BOG = 1;

    public function name(): string
    {
        return match($this) {
            self::BOG => 'BOG',
        };
    }

    public function getBaseUrl(): string
    {
        return match($this) {
            self::BOG => 'https://api.bog.ge/v1/payment', // Adjust this URL as needed
        };
    }

    public static function fromName(string $name): ?self
    {
        return match($name) {
            'BOG' => self::BOG,
            default => null,
        };
    }

    public static function options(): array
    {
        return [
            self::BOG->value => self::BOG->name(),
        ];
    }
}
