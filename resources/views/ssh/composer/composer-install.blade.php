if [ ! -d "{{ $path }}" ]; then
    echo "FAILED_TO_FIND_PATH: Directory {{ $path }} does not exist."
    echo 'VITO_SSH_ERROR' && exit 1
fi

if ! cd {{ $path }}; then
    echo "FAILED_TO_CD: Could not change directory to {{ $path }}"
    echo 'VITO_SSH_ERROR' && exit 1
fi

if [ ! -f "composer.json" ]; then
    echo "MISSING_COMPOSER_JSON: composer.json not found in {{ $path }}"
    echo "Directory content:"
    ls -la
    echo 'VITO_SSH_ERROR' && exit 1
fi

if ! php{{ $phpVersion }} /usr/local/bin/composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev; then
    echo 'VITO_SSH_ERROR' && exit 1
fi
