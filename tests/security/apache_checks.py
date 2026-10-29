#!/usr/bin/env python3
"""Verify repository Apache rules on a temporary document root, with no application data."""
import grp, os, pathlib, pwd, shutil, socket, subprocess, tempfile, time, urllib.error, urllib.request
root = pathlib.Path(__file__).resolve().parents[2]
binary = os.environ.get('APACHE_BINARY', '/usr/sbin/apache2')
modules = pathlib.Path(os.environ.get('APACHE_MODULE_DIR', '/usr/lib/apache2/modules'))
with socket.socket() as sock:
    sock.bind(('127.0.0.1', 0)); port = sock.getsockname()[1]
checks = 0
with tempfile.TemporaryDirectory(prefix='swiftlane-apache-test-') as directory:
    temp = pathlib.Path(directory); public = temp/'public'; public.mkdir()
    shutil.copy(root/'.htaccess', public/'.htaccess')
    fixtures = ['index.html', 'assets/app.css', 'assets/uploads/avatar.png', 'assets/uploads/test.php.png',
                'order_files/invoice.pdf', 'order_files/payload.svg', '.git/HEAD', '.env', 'composer.lock',
                'helpers/private.txt', 'config/config.txt', 'views/private.html', 'backups/dump.sql',
                'api/v1/storage/reset.json', 'cron/job.txt', 'pdf/documentos/private.txt', '.well-known/security.txt']
    for name in fixtures:
        target = public/name; target.parent.mkdir(parents=True, exist_ok=True); target.write_text('fixture')
    module_names = ['mpm_event','authz_core','dir','mime','rewrite','headers','filter','deflate','expires']
    config = f'ServerRoot "{temp}"\nServerName localhost\nListen 127.0.0.1:{port}\nPidFile "{temp}/httpd.pid"\nErrorLog "{temp}/error.log"\n'
    config += f'User {pwd.getpwuid(os.getuid()).pw_name}\nGroup {grp.getgrgid(os.getgid()).gr_name}\n'
    for name in module_names: config += f'LoadModule {name}_module "{modules}/mod_{name}.so"\n'
    config += f'TypesConfig /etc/mime.types\nDocumentRoot "{public}"\n<Directory "{public}">\nAllowOverride All\nRequire all granted\n</Directory>\n'
    conf = temp/'httpd.conf'; conf.write_text(config)
    subprocess.run([binary, '-t', '-f', str(conf)], check=True, capture_output=True)
    server = subprocess.Popen([binary, '-f', str(conf), '-DFOREGROUND'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    opener = urllib.request.build_opener(urllib.request.ProxyHandler({}))
    def request(path):
        try: response = opener.open(f'http://127.0.0.1:{port}/'+path, timeout=5)
        except urllib.error.HTTPError as error: response = error
        return response.status, response.headers
    try:
        for attempt in range(50):
            if server.poll() is not None: raise RuntimeError((temp/'error.log').read_text())
            try: request('index.html'); break
            except urllib.error.URLError: time.sleep(.1)
        else: raise RuntimeError('Apache did not become ready')
        allowed = ['index.html','assets/app.css','assets/uploads/avatar.png','order_files/invoice.pdf','.well-known/security.txt']
        for path in allowed:
            status, headers = request(path); checks += 1
            assert status == 200, (path, status)
        for path in set(fixtures)-set(allowed):
            status, headers = request(path); checks += 1
            assert status == 403, (path, status)
        status, headers = request('index.html'); checks += 2
        assert headers.get('X-Content-Type-Options') == 'nosniff'
        assert headers.get('X-Frame-Options') == 'SAMEORIGIN'
        print(f'Apache security regressions passed: {checks} checks (temporary static document root).')
    finally:
        server.terminate()
        try: server.wait(timeout=5)
        except subprocess.TimeoutExpired: server.kill(); server.wait()
