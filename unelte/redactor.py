#!/usr/bin/env python3
"""Cine semnează un articol EuroGol: aceleași reguli ca pe site (tema/eurogol/inc/redactori.php, eg_alege_redactor).

Folosire:  python3 unelte/redactor.py <id> "<titlu>" "<categorie>" ["eticheta1,eticheta2"] [--cronica]
Afișează login-ul, numele și departamentul. Scrie apoi în vocea lui (STIL-REDACTORI.md).
Dacă schimbi regulile aici, schimbă-le și în redactori.php (și invers).
"""
import re
import sys
import unicodedata

REDACTORI = {
    'andrei.toma': ('Andrei Toma', 'SuperLiga'),
    'razvan.petrescu': ('Răzvan Petrescu', 'Naționala și stranierii'),
    'mihai.georgescu': ('Mihai Georgescu', 'Cupele europene'),
    'vlad.ene': ('Vlad Ene', 'Marile campionate'),
    'catalin.dumitru': ('Cătălin Dumitru', 'Transferuri'),
    'raul.neagu': ('Raul Neagu', 'America de Sud'),
    'sorin.avram': ('Sorin Avram', 'Povești'),
    'cristian.manolache': ('Cristian Manolache', 'Declarații și opinii'),
}

SUD = r'libertadores|sudamericana|conmebol|copa america|brasileirao|brazilia|argentin|boca juniors|river plate|flamengo|palmeiras|superclasico'
EUROPA = r'champions league|liga campionilor|europa league|conference league|\buefa\b'
MARI = r'premier league|laliga|la liga|serie a|bundesliga|ligue 1'


def fara_diacritice(s):
    return ''.join(c for c in unicodedata.normalize('NFKD', s or '') if not unicodedata.combining(c)).lower()


def alege(id_, titlu, etichete=(), categorie='', cronica=False):
    t = ' ' + fara_diacritice(titlu + ' ' + ' '.join(etichete)) + ' '
    cat = fara_diacritice(categorie)
    id_ = (id_ or '').lower()
    are = lambda re_: bool(re.search(re_, t))
    if '-poveste-' in id_ or re.search(r'povest|legend|istori', cat):
        return 'sorin.avram'
    if cronica or re.search(r'-etapa-\d+-', id_):
        if re.search(SUD, cat + t):
            return 'raul.neagu'
        if 'nationala' in cat:
            return 'razvan.petrescu'
        if re.search(EUROPA, cat):
            return 'mihai.georgescu'
        if re.search(MARI, cat):
            return 'vlad.ene'
        return 'andrei.toma'
    if re.search(SUD, cat) or are(SUD):
        return 'raul.neagu'
    if are(r'transfer|contract|semneaz|a semnat|imprumut|oferta|clauza|impresar|vandut|cumpar|achizit|mercato'):
        return 'catalin.dumitru'
    if 'nationala' in cat or are(r'nationala|tricolor|stranier|\bromanul\b|\bun roman\b|chivu|lucescu|dragusin|ianis hagi|razvan marin|stanciu|birligea|mihaila|dennis man'):
        return 'razvan.petrescu'
    if are(r'„|declarat|conferint|interviu|editorial|a spus|a reactionat|reactia'):
        return 'cristian.manolache'
    if re.search(EUROPA, cat) or are(EUROPA):
        return 'mihai.georgescu'
    if re.search(MARI, cat) or are(MARI):
        return 'vlad.ene'
    return 'andrei.toma'


if __name__ == '__main__':
    a = [x for x in sys.argv[1:] if x != '--cronica']
    if len(a) < 3:
        sys.exit(__doc__)
    et = a[3].split(',') if len(a) > 3 else []
    login = alege(a[0], a[1], et, a[2], '--cronica' in sys.argv)
    nume, dep = REDACTORI[login]
    print(f'{login} | {nume} | {dep}')
