# Card New Fields Documentation

## Overview
Two new fields have been added to the Card model:
1. `parent_verification` - Boolean field for parent verification status
2. `license` - JSON field that can store either boolean or date values

## Database Schema

### Migration
```php
// database/migrations/2025_07_31_175459_add_parent_verification_and_license_to_cards_table.php
Schema::table('cards', function (Blueprint $table) {
    $table->boolean('parent_verification')->default(false)->after('image_path');
    $table->json('license')->nullable()->after('parent_verification');
});
```

### Model Fields
```php
// app/Models/Card.php
protected $fillable = [
    // ... existing fields ...
    'parent_verification',
    'license',
];

protected $casts = [
    'parent_verification' => 'boolean',
    'license' => 'array',
];
```

## API Usage Examples

### 1. Creating a Card with Parent Verification and Boolean License

```bash
POST /api/cards
Content-Type: application/json

{
    "child_first_name": "Giorgi",
    "child_last_name": "Davitashvili",
    "parent_name": "Nino Davitashvili",
    "phone": "+995599123456",
    "status": "active",
    "group_id": 1,
    "parent_verification": true,
    "license": {
        "type": "boolean",
        "value": true
    }
}
```

### 2. Creating a Card with Parent Verification and Date License

```bash
POST /api/cards
Content-Type: application/json

{
    "child_first_name": "Mariam",
    "child_last_name": "Gogoladze",
    "parent_name": "Ana Gogoladze",
    "phone": "+995599654321",
    "status": "active",
    "group_id": 1,
    "parent_verification": false,
    "license": {
        "type": "date",
        "value": "2025-12-31"
    }
}
```

### 3. Updating Parent Verification Status

```bash
PUT /api/cards/1
Content-Type: application/json

{
    "parent_verification": true
}
```

### 4. Updating License to Date Type

```bash
PUT /api/cards/1
Content-Type: application/json

{
    "license": {
        "type": "date",
        "value": "2026-06-30"
    }
}
```

### 5. Filtering Cards by Parent Verification

```bash
GET /api/cards?parent_verification=true
```

### 6. Filtering Cards by License Type

```bash
GET /api/cards?license_type=boolean
GET /api/cards?license_type=date
```

## Model Helper Methods

The Card model includes helper methods for working with the license field:

```php
// Set license as boolean
$card->setLicenseBoolean(true);
$card->setLicenseBoolean(false);

// Set license as date
$card->setLicenseDate('2025-12-31');
$card->setLicenseDate(Carbon::parse('2025-12-31'));

// Get license information
$card->getLicenseValue(); // Returns the value (boolean or date string)
$card->getLicenseType();  // Returns 'boolean' or 'date'
$card->isLicenseBoolean(); // Returns true if license type is boolean
$card->isLicenseDate();   // Returns true if license type is date
```

## Response Examples

### Card with Boolean License
```json
{
    "id": 1,
    "child_first_name": "Giorgi",
    "child_last_name": "Davitashvili",
    "parent_name": "Nino Davitashvili",
    "phone": "+995599123456",
    "status": "active",
    "group_id": 1,
    "parent_verification": true,
    "license": {
        "type": "boolean",
        "value": true
    },
    "created_at": "2025-07-31T17:54:59.000000Z",
    "updated_at": "2025-07-31T17:54:59.000000Z"
}
```

### Card with Date License
```json
{
    "id": 2,
    "child_first_name": "Mariam",
    "child_last_name": "Gogoladze",
    "parent_name": "Ana Gogoladze",
    "phone": "+995599654321",
    "status": "active",
    "group_id": 1,
    "parent_verification": false,
    "license": {
        "type": "date",
        "value": "2025-12-31"
    },
    "created_at": "2025-07-31T17:54:59.000000Z",
    "updated_at": "2025-07-31T17:54:59.000000Z"
}
```

## Validation Rules

The API validates the new fields with the following rules:

```php
'parent_verification' => 'nullable|boolean',
'license' => 'nullable|array',
'license.type' => 'nullable|string|in:boolean,date',
'license.value' => 'nullable',
```

## Notes

- `parent_verification` defaults to `false` if not provided
- `license` is optional and can be `null`
- When `license` is provided, it must be an object with `type` and `value` properties
- `license.type` must be either "boolean" or "date"
- `license.value` must be boolean (true/false) when type is "boolean", or a valid date string when type is "date" 