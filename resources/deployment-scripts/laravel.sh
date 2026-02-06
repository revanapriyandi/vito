cd $SITE_PATH

if [ -d ".git" ]; then
  git pull origin $BRANCH
else
  git clone -b $BRANCH $REPOSITORY .
fi

composer install --no-interaction --prefer-dist --optimize-autoloader
php artisan key:generate
php artisan migrate --force

php artisan optimize:clear
php artisan optimize

npm ci
npm run build

echo "✅ Deployment completed successfully!"
