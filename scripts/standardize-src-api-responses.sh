#!/bin/bash

# Script to standardize API responses from JSendResponse to ApiResponse in src/ directory

echo "🔧 Standardizing API responses in src/ directory..."

# Find all controllers using JSendResponse in src/
controllers=$(grep -r "JSendResponse" src/ | cut -d: -f1 | sort | uniq)

for controller in $controllers; do
    echo "Processing: $controller"
    
    # Create backup
    cp "$controller" "$controller.backup"
    
    # Replace import statement
    sed -i '' 's/use Src\\Foundation\\Utils\\JSendResponse;/use App\\Support\\ApiResponse;/g' "$controller"
    
    # Replace usage
    sed -i '' 's/JSendResponse::/ApiResponse::/g' "$controller"
    
    echo "✅ Updated: $controller"
done

echo "🎉 API response standardization in src/ completed!"
echo "📝 Please review the changes and test API compatibility."
