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
  # If directory is not empty but no git, we might need to be careful.
  # For now, assuming empty or safe to clone into.
  git clone -b "$BRANCH" "$REPOSITORY" .
fi

composer install --no-interaction --prefer-dist --optimize-autoloader
php artisan key:generate
php artisan migrate --force

php artisan optimize:clear
php artisan optimize

npm ci
npm run build

echo "✅ Deployment completed successfully!"
