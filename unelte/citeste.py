"""Descarcă un articol și scoate textul (titlu, dată, paragrafe) pentru extragerea faptelor."""
import re, html, subprocess, sys, json
def citeste(url):
    s = subprocess.run(['curl', '-s', '-L', '--max-time', '40', '-A', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36', url], capture_output=True, text=True, errors='ignore').stdout
    titlu = re.search(r'<title>(.*?)</title>', s, re.S)
    data = re.search(r'"datePublished"\s*:\s*"([^"]+)"', s) or re.search(r'article:published_time"\s+content="([^"]+)"', s)
    s = re.sub(r'<script.*?</script>|<style.*?</style>|<nav.*?</nav>|<footer.*?</footer>|<aside.*?</aside>', '', s, flags=re.S)
    par = [re.sub(r'\s+', ' ', html.unescape(re.sub(r'<[^>]+>', ' ', p))).strip() for p in re.findall(r'<p[^>]*>(.*?)</p>', s, re.S)]
    par = [p for p in par if len(p) > 60 and not re.search(r'cookie|Setări|newsletter|Abonează|©', p, re.I)]
    return {'url': url, 'titlu': html.unescape(titlu.group(1)).strip() if titlu else '', 'data': data.group(1) if data else '', 'text': par[:30]}
if __name__ == '__main__':
  for u in sys.argv[1:]:
    r = citeste(u); print('=====', r['data'], '|', r['titlu'][:120]); print('\n'.join(p[:400] for p in r['text']))
