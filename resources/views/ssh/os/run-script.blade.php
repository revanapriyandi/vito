# Debug: Current directory before cd
# echo "Current directory: $(pwd)"
# echo "Attempting to cd into: {{ $path }}"

if ! cd {{ $path }}; then
    echo "FAILED_TO_CD: Could not change directory to {{ $path }}"
    echo 'VITO_SSH_ERROR' && exit 1
fi

# Debug: Current directory after cd
# echo "Changed directory to: $(pwd)"

{!! $script !!}
