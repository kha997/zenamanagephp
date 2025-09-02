#!/bin/bash

# zenamanage Installation Script
# Cài đặt dependencies và thiết lập dự án

echo "🚀 Installing zenamanage..."

# Check if PHP is available
if ! command -v /Applications/XAMPP/bin/php &> /dev/null; then
    echo "❌ XAMPP PHP not found at /Applications/XAMPP/bin/php"
    echo "Please install XAMPP or update the PHP path"
    exit 1
fi

echo "✅ PHP found: $(/Applications/XAMPP/bin/php -v | head -n 1)"

# Check if Composer is available
if ! command -v composer &> /dev/null; then
    echo "❌ Composer not found"
    echo "Please install Composer: https://getcomposer.org/"
    exit 1
fi

echo "✅ Composer found: $(composer --version)"

# Install PHP dependencies
echo "📦 Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader

if [ $? -ne 0 ]; then
    echo "❌ Failed to install dependencies"
    exit 1
fi

# Copy environment file
if [ ! -f .env ]; then
    echo "📝 Creating environment file..."
    cp .env.example .env
    echo "✅ Environment file created. Please update database credentials in .env"
else
    echo "✅ Environment file already exists"
fi

# Create directories
echo "📁 Creating directories..."
mkdir -p storage/logs
mkdir -p storage/uploads
mkdir -p storage/cache
mkdir -p public/assets

# Set permissions
echo "🔐 Setting permissions..."
chmod -R 755 storage/
chmod -R 755 public/

echo "🎉 Installation completed!"
echo ""
echo "Next steps:"
echo "1. Update database credentials in .env file"
echo "2. Create database 'zenamanage' in MySQL"
echo "3. Run migrations: /Applications/XAMPP/bin/php migrate.php"
echo "4. Access the application at: http://localhost/zenamanage"