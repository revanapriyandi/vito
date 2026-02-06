cd $SITE_PATH

if [ -d ".git" ]; then
  git pull origin $BRANCH
else
  git clone -b $BRANCH $REPOSITORY .
fi

go build -o app

sudo supervisorctl restart all

echo "✅ Deployment completed successfully!"
