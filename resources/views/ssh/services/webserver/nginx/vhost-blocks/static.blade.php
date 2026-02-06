#[static]
location / {
    try_files $uri $uri/ /index.html;
}
#[/static]
