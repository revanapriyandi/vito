if [ -d ".git" ]; then
  git pull origin $BRANCH
else
  git clone -b $BRANCH $REPOSITORY .
fi

composer install --no-interaction --prefer-dist --optimize-autoloader

php spark migrate --all
php spark optimize

echo "✅ Deployment completed successfully!"
