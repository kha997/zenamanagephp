#!/bin/bash

# Clear previous content in md-inventory.md, keeping the header
echo "| Path | Last Git Commit (hash + date) | Size (lines) | References Count | Recommendation | Evidence Notes |" > docs/md-inventory.md
echo "|---|---|---|---|---|---|" >> docs/md-inventory.md

# Read md_files.txt line by line
while IFS= read -r filepath; do
    if [ -f "$filepath" ]; then
        # Get last git commit
        GIT_LOG=$(git log -n 1 --format="%h %ad" --date=short -- "$filepath" 2>/dev/null)
        if [ -z "$GIT_LOG" ]; then
            GIT_LOG="N/A"
        fi

        # Get line count
        LINE_COUNT=$(wc -l < "$filepath" 2>/dev/null | xargs)
        if [ -z "$LINE_COUNT" ]; then
            LINE_COUNT="N/A"
        fi

        # Get file name for reference count (remove path)
        filename=$(basename "$filepath")
        # Escape special characters in filename for ripgrep
        escaped_filename=$(printf %s "$filename" | sed 's/[^[:alnum:]]/\&/g')

        # Get reference count (excluding the file itself from the count)
        # We search for the filename as a link or direct reference.
        # This is a basic approach and might need refinement for more complex link types.
        REF_COUNT=$(rg -c -l --type md "$escaped_filename" . | grep -v "$filepath" | wc -l | xargs)
        if [ -z "$REF_COUNT" ]; then
             REF_COUNT=0
        fi

        # Append to inventory
        echo "| $filepath | $GIT_LOG | $LINE_COUNT | $REF_COUNT | | |" >> docs/md-inventory.md
    else
        echo "Warning: File not found or is a directory: $filepath"
    fi
done < md_files.txt

echo "Markdown inventory updated in docs/md-inventory.md"
