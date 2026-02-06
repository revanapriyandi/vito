cd $SITE_PATH

if [ -d ".git" ]; then
  git pull origin $BRANCH
else
  git clone -b $BRANCH $REPOSITORY .
fi

pip install -r requirements.txt

sudo supervisorctl restart all

echo "✅ Deployment completed successfully!"
