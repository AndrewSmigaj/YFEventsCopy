# Shop Import Guide

This guide explains how to import local shops into the YFEvents system using CSV or JSON files.

## Accessing the Import Feature

1. Go to the Advanced Admin panel: `/admin/calendar/shops.php`
2. Click the "Import Shops" button
3. Select your file format (CSV or JSON)
4. Upload your file and review the preview
5. Click "Import Shops" to complete the process

## Supported File Formats

### CSV Format

CSV files should include a header row with column names. The system will automatically map common column variations to the appropriate database fields.

#### Required Fields
- `name` - Shop name (required)

#### Optional Fields
- `description` - Shop description
- `address` - Full address for geocoding
- `phone` - Contact phone number
- `email` - Contact email address
- `website` - Shop website URL
- `category` - Category name or ID
- `operating_hours` - Business hours description
- `payment_methods` - Accepted payment methods (comma-separated)
- `amenities` - Shop amenities/features (comma-separated)
- `featured` - Boolean (true/false) for featured shops
- `status` - Shop status (active, pending, inactive)

#### Column Name Variations
The system recognizes common variations of column names:
- **Name**: name, shop_name, business_name, title
- **Description**: description, desc, about, summary
- **Address**: address, location, street_address, full_address
- **Phone**: phone, telephone, phone_number, contact_phone
- **Email**: email, email_address, contact_email
- **Website**: website, url, web_address, homepage

#### Example CSV
```csv
name,description,address,phone,email,website,category,operating_hours,payment_methods,amenities,featured,status
"Yakima Coffee Roasters","Premium coffee roasting shop","123 Main St, Yakima, WA","509-555-0123","info@yakimacoffee.com","https://yakimacoffee.com","Food & Beverage","Mon-Fri 6am-6pm","Credit Card,Cash","WiFi,Parking",true,active
```

### JSON Format

JSON files can contain either a single shop object or an array of shop objects.

#### Example JSON (Array)
```json
[
  {
    "name": "Yakima Bike Works",
    "description": "Full-service bicycle shop",
    "address": "555 Bicycle Ln, Yakima, WA 98905",
    "phone": "509-555-0555",
    "email": "info@yakimabikeworks.com",
    "website": "https://yakimabikeworks.com",
    "operating_hours": {
      "monday": "10am-7pm",
      "tuesday": "10am-7pm",
      "wednesday": "10am-7pm",
      "thursday": "10am-7pm",
      "friday": "10am-8pm",
      "saturday": "9am-6pm",
      "sunday": "11am-5pm"
    },
    "payment_methods": ["Credit Card", "Cash", "Financing"],
    "amenities": ["Repair Service", "Bike Rental", "Test Rides"],
    "featured": false,
    "status": "active"
  }
]
```

#### Example JSON (Single Object)
```json
{
  "name": "Mountain View Books",
  "description": "Independent bookstore",
  "address": "456 Oak Ave, Yakima, WA",
  "operating_hours": "Mon-Sat 9am-8pm",
  "payment_methods": "Credit Card,Cash",
  "amenities": "WiFi,Reading Area",
  "featured": false,
  "status": "active"
}
```

## Field Processing

### Operating Hours
- **CSV**: Plain text description
- **JSON**: Can be a string description or structured object with days

### Payment Methods
- **CSV**: Comma-separated list in quotes
- **JSON**: Array of strings or comma-separated string

### Amenities
- **CSV**: Comma-separated list in quotes  
- **JSON**: Array of strings or comma-separated string

### Categories
- Can be category ID (number) or category name (string)
- If category name is provided, system will try to find matching category
- If not found or not provided, shop will be uncategorized

### Address Geocoding
- Addresses are automatically geocoded to latitude/longitude
- Uses Google Maps Geocoding API or OpenStreetMap Nominatim
- If geocoding fails, shop is still imported without coordinates

### Status Values
- `active` - Shop is live and visible
- `pending` - Shop needs review (default)
- `inactive` - Shop is hidden

## Import Validation

### File Validation
- Maximum file size: 10MB
- Supported extensions: .csv, .json
- File format must match selected format

### Data Validation
- Shop name is required
- Duplicate detection by name (and address if provided)
- Invalid JSON will be rejected
- Malformed CSV rows will be skipped with error messages

### Error Handling
- Import continues even if some rows fail
- Detailed error messages for each failed row
- Success count and error summary provided

## Import Results

After import completion, you'll see:
- Number of shops successfully imported
- List of any errors or warnings
- Page automatically refreshes to show new shops

## Sample Files

Sample files are provided for testing:
- `sample_shops.csv` - Example CSV format
- `sample_shops.json` - Example JSON format

## Troubleshooting

### Common Issues

1. **"File format mismatch"** - Ensure file extension matches selected format
2. **"Missing shop name"** - Every shop must have a name field
3. **"Shop already exists"** - Duplicate shops (by name) are skipped
4. **"Invalid JSON"** - Check JSON syntax using a validator
5. **"Failed to geocode"** - Address geocoding failed, shop imported without coordinates

### Best Practices

1. **Test with small files first** - Start with 5-10 shops to verify format
2. **Use consistent formatting** - Especially for phone numbers and websites
3. **Include full addresses** - For accurate geocoding
4. **Validate your data** - Check for duplicates and required fields before import
5. **Review categories** - Ensure category names match existing categories

## Technical Details

### Database Schema
Shops are stored in the `local_shops` table with these key fields:
- `name` (VARCHAR 255, required)
- `description` (TEXT)
- `address` (TEXT)
- `latitude`, `longitude` (DECIMAL)
- `phone` (VARCHAR 50)
- `email` (VARCHAR 255)
- `website` (VARCHAR 500)
- `category_id` (INT, foreign key)
- `operating_hours` (JSON)
- `payment_methods` (JSON)
- `amenities` (JSON)
- `featured` (BOOLEAN)
- `status` (ENUM: active, pending, inactive)

### API Endpoint
- **URL**: `/admin/ajax/import_shops.php`
- **Method**: POST
- **Authentication**: Admin session required
- **Parameters**: `file` (uploaded file), `format` (csv|json)
- **Response**: JSON with success status and import details