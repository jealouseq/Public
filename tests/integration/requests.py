"""Exercise the real guest REST route and WooCommerce request lifecycle."""
import json
import urllib.request
import urllib.error
from datetime import datetime, timedelta
from zoneinfo import ZoneInfo
from uuid import uuid4

BASE = 'http://localhost:8080/wp-json/joyrent/v1'
def call(path, payload=None):
    request=urllib.request.Request(BASE+path, data=None if payload is None else json.dumps(payload).encode(), headers={'Content-Type':'application/json'})
    try:
        with urllib.request.urlopen(request, timeout=20) as response: return response.status,json.load(response)
    except urllib.error.HTTPError as error: return error.code,json.load(error)

status,catalog=call('/catalog')
assert status==200, f'Catalog expected 200, received {status}: {catalog}'
assert catalog['tariffs']['ps5'][1]['price']==1400
assert catalog['tariffs']['ps4'][0]['price']==750
assert catalog['settings']['depositPs5']==25000
assert catalog['settings']['depositPs4']==7500
assert catalog['settings']['baseControllers']==2
assert catalog['settings']['extraControllerFee']==0
assert 'notification_email' not in catalog['settings']
assert catalog['settings']['deliveryFee'] is None
date=(datetime.now(ZoneInfo('Europe/Kyiv'))+timedelta(days=2)).strftime('%Y-%m-%d')
def base():
    return dict(console='ps5', days=3, startDate=date, controllers=1, gameIds=['it-takes-two'], name='Тест JOYRENT', phone='+380000000001', method='delivery', address='Тестове місто, тестова адреса 1', consent=True, website='', requestId=str(uuid4()))

payload=base()
payload['price']=1
status,receipt=call('/requests',payload)
assert status==201,(status,receipt)
assert receipt['rentalAmount']==1400,receipt
assert receipt['status']=='awaiting_confirmation'
assert set(receipt)=={'reference','rentalAmount','status'},receipt
status,duplicate=call('/requests',payload)
assert status==200 and duplicate==receipt, (status,duplicate)
altered=dict(payload,name='Інша заявка')
status,_=call('/requests',altered)
assert status==409,status

cases=[
    ('unsupported PS4 one-day tariff', dict(console='ps4',days=1)),
    ('unknown console', dict(console='ps6')),
    ('impossible date', dict(startDate='2026-02-30')),
    ('past date', dict(startDate='2020-01-01')),
    ('missing consent', dict(consent=False)),
    ('missing address', dict(address='')),
    ('invalid phone', dict(phone='not-a-phone')),
    ('unknown game', dict(gameIds=['not-in-catalog'])),
    ('incompatible game', dict(console='ps4',gameIds=['spider-man-2'])),
    ('honeypot filled', dict(website='spam.example')),
    ('disabled pickup', dict(method='pickup',address='')),
]
for label,changes in cases:
    candidate=base(); candidate.update(changes)
    status,error=call('/requests',candidate)
    assert status==400,(label,status,error)
    print('PASS:',label)
print('PASS: server price, pending confirmation, no PII in response, idempotency, changed-key conflict and catalog configuration.')
print('CREATED_REFERENCE='+receipt['reference'])
