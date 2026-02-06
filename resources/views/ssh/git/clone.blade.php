# Setup SSH Config
mkdir -p ~/.ssh
chmod 700 ~/.ssh
touch ~/.ssh/config
chmod 600 ~/.ssh/config

if ! grep -q "Host {{ $host }}-{{ $key }}" ~/.ssh/config; then
    echo "Host {{ $host }}-{{ $key }}
        Hostname {{ $host }}
        IdentityFile=~/.ssh/{{ $key }}
        StrictHostKeyChecking no" >> ~/.ssh/config
fi

# Setup Known Hosts
mkdir -p ~/.ssh
chmod 700 ~/.ssh
touch ~/.ssh/known_hosts
chmod 644 ~/.ssh/known_hosts
ssh-keyscan -H {{ $host }} >> ~/.ssh/known_hosts 2>/dev/null

# Clean up and clone
rm -rf {{ $path }}
mkdir -p $(dirname {{ $path }})

if ! git config --global core.fileMode false; then
    echo 'VITO_SSH_ERROR' && exit 1
fi

if ! git clone -b {{ $branch }} {{ $repo }} {{ $path }}; then
    echo "FAILED_TO_CLONE: Git clone failed for {{ $repo }}"
    echo 'VITO_SSH_ERROR' && exit 1
fi

if [ -d "{{ $path }}" ]; then
    find {{ $path }} -type d -exec chmod 755 {} \;
    find {{ $path }} -type f -exec chmod 644 {} \;
    cd {{ $path }} && git config core.fileMode false
else
    echo "FAILED_TO_FIND_PATH: Directory {{ $path }} not found after clone"
    echo 'VITO_SSH_ERROR' && exit 1
fi
