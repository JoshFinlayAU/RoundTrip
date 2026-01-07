<div style="margin: 15px;">
    <h4>RoundTrip Settings</h4>
    <p class="text-muted">Configure the connection to your RoundTrip API server.</p>

    <form method="post" style="margin: 15px 0;">
        @csrf
        <div class="form-group">
            <label for="api_url">API URL</label>
            <input type="text" class="form-control" id="api_url" name="settings[api_url]" 
                   value="{{ $api_url }}" placeholder="http://localhost:8000">
            <small class="form-text text-muted">The base URL of your RoundTrip API (without trailing slash)</small>
        </div>

        <div class="form-group" style="margin-top: 15px;">
            <label for="api_token">API Token</label>
            <input type="password" class="form-control" id="api_token" name="settings[api_token]" 
                   value="{{ $api_token }}" placeholder="Enter your RoundTrip API token">
            <small class="form-text text-muted">
                Generate a token with: <code>cd /opt/roundtrip && php artisan token:manage</code>
            </small>
        </div>

        <div style="margin-top: 20px;">
            <button type="submit" class="btn btn-primary">Save Settings</button>
        </div>
    </form>
</div>
