"""Guest localization and retry invariants on the local WordPress fixture only."""
import json,urllib.error
from datetime import datetime,timedelta
from zoneinfo import ZoneInfo
from uuid import uuid4

def call(path,payload=None):
 req=urllib.request.Request('http://localhost:8080/wp-json/joyrent/v1'+path,data=None if payload is None else json.dumps(payload).encode(),headers={'Content-Type':'application/json'})
 try:
  with urllib.request.urlopen(req,timeout=20) as r:return r.status,json.load(r)
 except urllib.error.HTTPError as e:return e.code,json.load(e)
def base():
 return dict(console='ps5',days=3,startDate=(datetime.now(ZoneInfo('Europe/Kyiv'))+timedelta(days=2)).strftime('%Y-%m-%d'),controllers=1,gameIds=['it-takes-two'],name='Тест JOYRENT',phone='+380000000001',method='delivery',address='Тестове місто, тестова адреса 1',consent=True,website='',requestId=str(uuid4()))
import urllib.request
status,catalog=call('/catalog')
assert catalog['settings']['deliveryTextRu'].startswith('Укажи')
assert len({g['image'] for g in catalog['games']})==20
assert all(g['image'].startswith('cover-') and g.get('genreRu') and g.get('descriptionRu') for g in catalog['games'])
assert 'nameRu' in catalog['tariffs']['ps5'][0]
payload=base();payload['language']='ru';payload['phone']='bad'
status,error=call('/requests',payload)
assert status==400 and error['message']=='Укажи украинский номер телефона.',(status,error)
payload=base();payload['language']=['ru']
status,error=call('/requests',payload)
assert status==400,(status,error)
payload=base();payload['language']='ru'
status,receipt=call('/requests',payload)
assert status==201,(status,receipt)
payload['language']='uk'
status,retry=call('/requests',payload)
assert status==200 and retry==receipt,(status,retry)
for slug,label in [('usloviya-arendy','Условия аренды'),('konfidentsialnost','Конфиденциальность')]:
 with urllib.request.urlopen('http://localhost:8080/'+slug+'/') as response:
  content=response.read().decode()
 assert 'lang="ru"' in content and label in content
 assert 'Выбрать даты' in content and '?lang=ru#booking' in content
 assert 'Обрати дати' not in content
print('PASS: 20 official mappings, RU catalog, localized validation, language whitelist, cross-language retry and Russian legal pages')
print('RUSSIAN_REFERENCE='+receipt['reference'])
