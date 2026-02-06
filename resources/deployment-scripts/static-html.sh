cd $SITE_PATH

if [ -d ".git" ]; then
  git pull origin $BRANCH
else
  git clone -b $BRANCH $REPOSITORY .
fi

echo "✅ Deployment completed successfully!"
