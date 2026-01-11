#!/bin/bash

# Script to remove Brevo API key from git history
# WARNING: This will rewrite git history. Use with caution!

API_KEY="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"

echo "⚠️  WARNING: This will rewrite git history!"
echo "This script will remove the API key from all commits in git history."
echo ""
read -p "Are you sure you want to continue? (yes/no): " confirm

if [ "$confirm" != "yes" ]; then
    echo "Aborted."
    exit 1
fi

echo "Removing API key from git history..."

# Use git filter-branch to remove the key from all commits
git filter-branch --force --index-filter \
  "git rm --cached --ignore-unmatch app/Services/BrevoMailService.php || true" \
  --prune-empty --tag-name-filter cat -- --all

# Alternative: Use BFG Repo-Cleaner (faster, but requires installation)
# bfg --replace-text passwords.txt

echo ""
echo "✅ Git history cleaned!"
echo ""
echo "Next steps:"
echo "1. Review the changes: git log"
echo "2. Force push to remote: git push --force"
echo "⚠️  WARNING: Force push will overwrite remote history!"

