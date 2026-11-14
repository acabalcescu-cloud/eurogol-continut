"""Pune articolele scrise (cronici/<id>.json + .jpg) la începutul listei din index.json. Rulare: python3 unelte/publica.py <id> [<id>...]"""
import json, sys, os
R = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
idx = json.load(open(f'{R}/index.json'))
for id_ in sys.argv[1:]:
    a = json.load(open(f'{R}/cronici/{id_}.json'))
    assert a['id'] == id_ and os.path.exists(f'{R}/cronici/{id_}.jpg'), id_
    cale = f'cronici/{id_}.json'
    if cale not in idx['cronici']:
        idx['cronici'].insert(0, cale)
    print('OK', id_, '|', a['titlu'])
idx['cronici'] = idx['cronici'][:200]
json.dump(idx, open(f'{R}/index.json', 'w'), ensure_ascii=False, indent=2)
