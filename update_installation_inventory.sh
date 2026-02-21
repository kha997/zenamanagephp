#!/bin/bash

# Read the inventory content
inventory_content=$(<docs/md-inventory.md)

# Update the lines for the INSTALLATION.md files
inventory_content=$(echo "$inventory_content" | awk '
BEGIN {
    FS = "|"; OFS = "|"
}
{
    if ($2 ~ /.\/docs\/INSTALLATION.md/) {
        print $1, $2, $3, $4, $5, " DEPRECATE ", " Superseded by INSTALLATION_GUIDE.md "
    } else {
        print
    }
}' | column -t -s '|')

# Overwrite the md-inventory.md with the updated content
echo "$inventory_content" > docs/md-inventory.md

echo "Updated docs/md-inventory.md"
