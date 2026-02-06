# Uninstall Python {{ $version }}
sudo apt-get purge -y python{{ $version }} python{{ $version }}-venv
sudo apt-get autoremove -y
