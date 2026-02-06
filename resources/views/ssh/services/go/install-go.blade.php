# Install Go {{ $version }}
# Remove existing installation
sudo rm -rf /usr/local/go

# Download and install Go
wget https://go.dev/dl/go{{ $version }}.linux-amd64.tar.gz
sudo tar -C /usr/local -xzf go{{ $version }}.linux-amd64.tar.gz
rm go{{ $version }}.linux-amd64.tar.gz

# Add Go to PATH (if not already there)
if ! grep -q "/usr/local/go/bin" /etc/profile; then
    echo "export PATH=\$PATH:/usr/local/go/bin" | sudo tee -a /etc/profile
fi
