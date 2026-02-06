cd $SITE_PATH

if [ -d ".git" ]; then
  git pull origin $BRANCH
else
  git clone -b $BRANCH $REPOSITORY .
fi

npm ci

npm run build

sudo supervisorctl restart all

echo "✅ Deployment completed successfully!"
