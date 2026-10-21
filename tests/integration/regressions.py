"""Run only against an isolated local WordPress fixture; creates test orders."""
import json, subprocess, time
from concurrent.futures import ThreadPoolExecutor
from uuid import uuid4
from datetime import datetime,timedelta
from zoneinfo import ZoneInfo
import urllib.request,urllib.error
BASE='http://localhost:8080/wp-json/joyrent/v1'
def wp(code):
 return subprocess.check_output(['docker','exec','joyrent-wp','php','/usr/local/bin/wp-cli.phar','eval',code,'--allow-root'],text=True).strip()
def call(payload):
 req=urllib.request.Request(BASE+'/requests',data=json.dumps(payload).encode(),headers={'Content-Type':'application/json'})
 try:
  with urllib.request.urlopen(req,timeout=30) as r:return r.status,json.load(r)
 except urllib.error.HTTPError as e:return e.code,json.load(e)
def candidate():
 return dict(console='ps5',days=3,startDate=(datetime.now(ZoneInfo('Europe/Kyiv'))+timedelta(days=2)).strftime('%Y-%m-%d'),controllers=1,gameIds=[],name='Тест JOYRENT',phone='+380000000001',method='delivery',address='Тестове місто, тестова адреса 1',consent=True,website='',requestId=str(uuid4()))
RATE="'jr_rate_'.hash_hmac('sha256','172.18.0.1',wp_salt('auth'))"
wp('delete_transient('+RATE+');')
payload=candidate();status,receipt=call(payload);assert status==201,(status,receipt)
key=json.dumps(payload['requestId'])
# Remove the optional receipt cache and leave an expired worker lock.
wp('$k=hash_hmac("sha256",'+key+',wp_salt("nonce"));delete_option("jr_result_".$k);update_option("jr_lock_".$k,(string)(time()-400),false);')
status,recovered=call(payload);assert status==200 and recovered==receipt,(status,recovered)
changed=dict(payload,name='Інша заявка');status,_=call(changed);assert status==409,status
print('PASS: durable WooCommerce retry recovery, stale lock and changed payload conflict')
# Parallel same-key submissions must yield at most one new order.
wp('delete_transient('+RATE+');')
payload=candidate()
with ThreadPoolExecutor(max_workers=6) as pool:results=list(pool.map(lambda _:call(payload),range(6)))
assert sum(s==201 for s,_ in results)==1,results
assert all(s in [200,201,409] for s,_ in results),results
refs={r['reference'] for s,r in results if s in [200,201]};assert len(refs)==1,refs
print('PASS: six simultaneous identical submissions create one request')
# Seven admissions consumed; only one further distinct request may enter.
wp('set_transient('+RATE+',7,900);')
with ThreadPoolExecutor(max_workers=6) as pool:results=list(pool.map(lambda _:call(candidate()),range(6)))
assert sum(s==201 for s,_ in results)==1,[(s,r.get('code')) for s,r in results]
assert all(s in [201,429] for s,_ in results),results
print('PASS: concurrent rate admission permits exactly one remaining request')
wp('delete_transient('+RATE+');')
