#!/usr/bin/env python3
"""Local HTTP regression checks using a test-only router and disposable SQLite data."""
import base64, http.cookiejar, json, os, pathlib, shutil, socket, subprocess, tempfile, time, urllib.error, urllib.request
root = pathlib.Path(__file__).resolve().parents[2]
php = os.environ.get('PHP_BINARY', 'php')
with socket.socket() as sock:
    sock.bind(('127.0.0.1', 0)); port = sock.getsockname()[1]
url = f'http://127.0.0.1:{port}'
checks = 0

def verify(condition, label):
    global checks
    checks += 1
    if not condition: raise AssertionError(label)

upload_root = root/'assets/uploads'
upload_root.mkdir(parents=True, exist_ok=True)
with tempfile.TemporaryFile() as log, tempfile.TemporaryDirectory(prefix='security_fixture_', dir=upload_root) as fixture_dir:
    extension_args = [] if subprocess.run([php, '-r', 'exit(extension_loaded("pdo_sqlite") ? 0 : 1);'], capture_output=True).returncode == 0 else ['-d', 'extension=pdo_sqlite']
    server = subprocess.Popen([php] + extension_args + ['-S', f'127.0.0.1:{port}', str(root/'tests/security/http_fixture.php')], cwd=root,
                              env=dict(os.environ, SWIFTLANE_SECURITY_FIXTURE='1', SWIFTLANE_SECURITY_FIXTURE_DIR=fixture_dir), stdout=log, stderr=log)
    class NoRedirect(urllib.request.HTTPRedirectHandler):
        def redirect_request(self, request, fp, code, msg, headers, newurl): return None
    opener = urllib.request.build_opener(NoRedirect(), urllib.request.ProxyHandler({}), urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    def request(path, data=None, headers=None):
        req = urllib.request.Request(url + path, data=data, headers=headers or {})
        try: result = opener.open(req, timeout=5)
        except urllib.error.HTTPError as error: result = error
        return result.status, result.headers, result.read()
    try:
        for attempt in range(50):
            if server.poll() is not None: raise RuntimeError('Fixture server exited')
            try:
                status, headers, body = request('/seed'); break
            except urllib.error.URLError: time.sleep(.1)
        else: raise RuntimeError('Fixture server did not become ready')
        token = json.loads(body)['csrf']
        verify(status == 200, 'fixture session created')
        verify('HttpOnly' in headers.get('Set-Cookie', '') and 'SameSite=Lax' in headers.get('Set-Cookie', ''), 'cookie flags reach HTTP client')
        verify(request('/auth')[0] == 401, 'API rejects missing bearer')
        verify(request('/auth', headers={'Authorization': 'Bearer wrong'})[0] == 401, 'API rejects malformed bearer')
        status, headers, body = request('/auth', headers={'Authorization': 'Bearer ' + 'a'*64})
        verify(status == 200 and json.loads(body)['userid'] == 10, 'valid token authenticates')
        status, headers, body = request('/session')
        verify(json.loads(body) == {'userid': None, 'marker': 'guest'}, 'Bearer identity never persists to browser cookie session')
        for mode in ['off', 'audit', 'enforce']:
            verify(request('/guard?mode='+mode, data=b'', headers={'X-CSRF-Token': token})[0] == 403, 'denied permission stays denied in '+mode)
        verify(request('/delete')[0] == 405, 'GET cannot perform deletion')
        verify(request('/delete', data=b'')[0] == 419, 'POST deletion requires CSRF')
        verify(request('/delete', data=b'', headers={'X-CSRF-Token': token})[0] == 200, 'valid session action can proceed')
        def upload(name, content, outside=False):
            boundary = 'securityfixtureboundary'
            fields = {'name': name, 'outside': '1' if outside else '0'}
            body = b''
            for key, value in fields.items():
                body += f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode()
            body += f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="fixture.png"\r\nContent-Type: image/png\r\n\r\n'.encode()+content+b'\r\n'+f'--{boundary}--\r\n'.encode()
            return request('/upload', body, {'Content-Type': 'multipart/form-data; boundary='+boundary, 'X-CSRF-Token': token})
        png = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j4mkAAAAASUVORK5CYII=')
        verify(json.loads(upload('image.png', png)[2])['stored'], 'real multipart PNG stored')
        verify(not json.loads(upload('image.png', png)[2])['stored'], 'upload cannot overwrite existing file')
        verify(not json.loads(upload('image.php.png', png)[2])['stored'], 'double-extension multipart upload blocked')
        verify(not json.loads(upload('spoof.png', b'<?php echo 1;')[2])['stored'], 'spoofed multipart MIME blocked')
        verify(not json.loads(upload('escape.png', png, outside=True)[2])['stored'], 'upload cannot escape approved storage')
        status, headers, body = request('/cors', headers={'Origin': 'https://untrusted.example.invalid'})
        verify(headers.get('Access-Control-Allow-Origin') == '*' and not headers.get('Access-Control-Allow-Credentials'), 'wildcard CORS never grants cookies: '+str(status)+' '+str(dict(headers))+' '+body.decode(errors='replace'))
        verify('no-store' in headers.get('Cache-Control', ''), 'API responses prevent shared-cache disclosure')
        status, headers, body = request('/cors?explicit=1', headers={'Origin': 'https://untrusted.example.invalid'})
        verify(not headers.get('Access-Control-Allow-Origin'), 'explicit CORS allowlist rejects unknown origins')
        for target in ['https%3A%2F%2Fevil.example.invalid', '%2F%2Fevil.example.invalid', 'javascript%3Aalert%281%29', 'index.php%0D%0AX-Injected%3Ayes']:
            status, headers, body = request('/redirect?target='+target)
            verify(status == 302 and headers.get('Location') == 'index.php', 'unsafe redirect rejected')
        request('/seed?privileged=1')
        verify(request('/auth', headers={'Authorization': 'Bearer ' + 'a'*64})[0] == 200, 'Bearer works alongside another browser identity')
        verify(json.loads(request('/session')[2])['userid'] == 999, 'original browser identity is preserved after Bearer request')
        print(f'HTTP security regressions passed: {checks} checks (test router, not the complete application).')
    finally:
        server.terminate()
        try: server.wait(timeout=5)
        except subprocess.TimeoutExpired: server.kill(); server.wait()
