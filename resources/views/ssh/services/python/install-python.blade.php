# Install Python {{ $version }} and venv
sudo apt-get update
sudo apt-get install -y python{{ $version }} python{{ $version }}-venv python3-pip
