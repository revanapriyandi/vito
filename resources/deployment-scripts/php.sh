# Ensure we are in the site directory
cd $SITE_PATH || exit 1
echo "Current directory: $(pwd)"

if [ -d ".git" ]; then
  git pull origin $BRANCH
else
  git clone -b $BRANCH $REPOSITORY .
fi

# Install dependencies if composer.json exists
if [ -f "composer.json" ]; then
  composer install --no-interaction --prefer-dist --optimize-autoloader
fi

echo "✅ Deployment completed successfully!"
