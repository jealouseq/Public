"""Concurrent local PHP workers; all mail is intercepted inside the container."""
import concurrent.futures
import json
import subprocess

def run(mode):
    result = subprocess.run(['docker', 'exec', 'joyrent-wp', 'php', '/tmp/backend-concurrency.php', mode], check=True, capture_output=True, text=True)
    return result.stdout.strip()
subprocess.run(['docker', 'cp', 'tests/php/backend-concurrency.php', 'joyrent-wp:/tmp/backend-concurrency.php'], check=True)
run('setup')
try:
    with concurrent.futures.ThreadPoolExecutor(max_workers=6) as pool:
        migrations = [json.loads(v) for v in pool.map(run, ['migrate'] * 6)]
    assert all(v['count'] == v['unique'] for v in migrations), migrations
    with concurrent.futures.ThreadPoolExecutor(max_workers=6) as pool:
        requests = [json.loads(v) for v in pool.map(run, ['request'] * 6)]
    assert all(v['status'] in (200, 201, 409) for v in requests), requests
    retry = json.loads(run('request'))
    assert retry['status'] == 200, retry
    receipts = {v['reference'] for v in requests + [retry] if 'reference' in v}
    assert len(receipts) == 1, requests
    final = json.loads(run('inspect'))
    assert final['canonicalGames'] == 1 and final['catalogCount'] == final['unique'], final
    assert final['orders'] == 1 and final['mailAttempts'] == 1 and final['notificationStatuses'] == ['sent'], final
    print(json.dumps({'workersPerBatch': 6, 'migrations': migrations, 'requestStatuses': [v['status'] for v in requests], 'retryStatus': retry['status'], 'verified': final}, indent=2))
finally:
    run('cleanup')
