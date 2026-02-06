cd $SITE_PATH

if [ -d ".git" ]; then
  git pull origin $BRANCH
else
  git clone -b $BRANCH $REPOSITORY .
fi

# Add your build commands here

sudo supervisorctl restart all

echo "✅ Deployment completed successfully!"
