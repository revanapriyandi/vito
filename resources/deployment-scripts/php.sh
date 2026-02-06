# Ensure we are in the site directory
if [ -z "$SITE_PATH" ]; then
    echo "ERROR: SITE_PATH variable is not set."
    exit 1
fi

cd "$SITE_PATH" || { echo "ERROR: Could not change directory to $SITE_PATH"; exit 1; }
echo "Current directory: $(pwd)"

if [ -d ".git" ]; then
  git pull origin "$BRANCH"
else
  git clone -b "$BRANCH" "$REPOSITORY" .
fi

# Install dependencies if composer.json exists
if [ -f "composer.json" ]; then
  composer install --no-interaction --prefer-dist --optimize-autoloader
fi

echo "✅ Deployment completed successfully!"
