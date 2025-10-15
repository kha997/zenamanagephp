#!/bin/bash

# Script to standardize API responses from JSendResponse to ApiResponse

echo "🔧 Standardizing API responses from JSendResponse to ApiResponse..."

# Find all controllers using JSendResponse
controllers=$(grep -r "JSendResponse" app/Http/Controllers/ | cut -d: -f1 | sort | uniq)

for controller in $controllers; do
    echo "Processing: $controller"
    
    # Replace import statement
    sed -i '' 's/use Src\\Foundation\\Utils\\JSendResponse;/use App\\Support\\ApiResponse;/g' "$controller"
    
    # Replace usage
    sed -i '' 's/JSendResponse::/ApiResponse::/g' "$controller"
    
    echo "✅ Updated: $controller"
done

echo "🎉 API response standardization completed!"
