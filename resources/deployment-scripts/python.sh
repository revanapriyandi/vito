cd $SITE_PATH

git pull origin $BRANCH

pip install -r requirements.txt

sudo supervisorctl restart all

echo "✅ Deployment completed successfully!"
