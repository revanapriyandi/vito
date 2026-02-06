cd $SITE_PATH

git pull origin $BRANCH

# Add your build commands here

sudo supervisorctl restart all

echo "✅ Deployment completed successfully!"
