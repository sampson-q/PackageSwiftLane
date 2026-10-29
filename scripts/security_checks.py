#!/usr/bin/env python3
"""First-party PHP lint and regression checks for prohibited security configurations."""
import pathlib, re, subprocess, sys
root = pathlib.Path(__file__).resolve().parents[1]
php = sys.argv[1] if len(sys.argv) > 1 else 'php'
files = []
for path in root.rglob('*.php'):
    relative = path.relative_to(root)
    if relative.parts[0] in {'.git', 'vendor', 'assets'} or relative.parts[:2] in {('helpers','stripe'), ('helpers','phpmailer'), ('helpers','vendor')}:
        continue
    files.append(path)
failures = []
for path in files:
    result = subprocess.run([php, '-l', str(path)], capture_output=True, text=True)
    if result.returncode: failures.append((str(path.relative_to(root)), result.stdout + result.stderr))
    content = path.read_text(errors='replace')
    # Disabled certificate verification exposes SMTP/API credentials to interception.
    if re.search(r"['\"]verify_peer(?:_name)?['\"]\s*=>\s*false|CURLOPT_SSL_VERIFY(?:HOST|PEER)\s*=>\s*(?:0|false)", content):
        failures.append((str(path.relative_to(root)), 'Disabled TLS verification'))
    if path.relative_to(root).as_posix() != 'helpers/security.php' and re.search(r'(?<![\w])move_uploaded_file\s*\(', content):
        failures.append((str(path.relative_to(root)), 'Upload bypasses the shared validation policy'))
for path, reason in failures: print(path + ': ' + reason)
if failures: raise SystemExit(1)
print(f'PHP lint and security configuration checks passed: {len(files)} first-party/test files.')
