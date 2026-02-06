git pull origin $BRANCH

# Install dependencies if composer.json exists
if [ -f "composer.json" ]; then
  composer install --no-interaction --prefer-dist --optimize-autoloader
fi

echo "✅ Deployment completed successfully!"
