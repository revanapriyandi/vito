git pull origin $BRANCH

composer install --no-interaction --prefer-dist --optimize-autoloader

php spark migrate --all
php spark optimize

echo "✅ Deployment completed successfully!"
