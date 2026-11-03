"""Pune poveștile din plan scrise (fișierele există în cronici/) în index.json și marchează planul. Rulare: python3 unelte/publica-povesti.py <id> [<id>...]"""
import json, sys, os
R = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
idx = json.load(open(f'{R}/index.json'))
plan = json.load(open(f'{R}/plan-povesti.json'))
for id_ in sys.argv[1:]:
    a = json.load(open(f'{R}/cronici/{id_}.json'))
    assert a['id'] == id_ and os.path.exists(f'{R}/cronici/{id_}.jpg'), id_
    cale = f'cronici/{id_}.json'
    if cale not in idx['cronici']:
        idx['cronici'].insert(0, cale)
    cheie = id_.split('-poveste-', 1)[1]
    for x in plan['povesti']:
        if x['cheie'] == cheie:
            x['stare'], x['id'] = 'scris', id_
    print('OK', id_, '|', a['titlu'])
idx['cronici'] = idx['cronici'][:200]
json.dump(idx, open(f'{R}/index.json', 'w'), ensure_ascii=False, indent=2)
json.dump(plan, open(f'{R}/plan-povesti.json', 'w'), ensure_ascii=False, indent=1)
