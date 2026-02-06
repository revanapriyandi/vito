cd $SITE_PATH

git pull origin $BRANCH

go build -o app

sudo supervisorctl restart all

echo "✅ Deployment completed successfully!"
