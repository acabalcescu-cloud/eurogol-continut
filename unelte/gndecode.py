"""Rezolva linkurile Google News RSS catre URL-ul real al articolului."""
import re, json, subprocess, urllib.parse
UA='Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36'
def curl(args): return subprocess.run(['curl','-s','-L','--max-time','30','-A',UA]+args,capture_output=True,text=True,errors='ignore').stdout
def decode(link):
    gid=re.search(r'articles/([^?]+)',link).group(1)
    h=curl(['https://news.google.com/rss/articles/'+gid])
    ts=re.search(r'data-n-a-ts="([^"]+)"',h); sg=re.search(r'data-n-a-sg="([^"]+)"',h)
    if not ts: return None
    inner='["garturlreq",[["X","X",["X","X"],null,null,1,1,"US:en",null,1,null,null,null,null,null,0,1],"X","X",1,[1,1,1],1,1,null,0,0,null,0],"%s",%s,"%s"]'%(gid,ts.group(1),sg.group(1))
    freq=json.dumps([[["Fbv4je",inner,None,"generic"]]])
    r=curl(['-X','POST','https://news.google.com/_/DotsSplashUi/data/batchexecute','-H','Content-Type: application/x-www-form-urlencoded;charset=UTF-8','--data','f.req='+urllib.parse.quote(freq)])
    m=re.search(r'\[\\"garturlres\\",\\"(.*?)\\"',r)
    return m.group(1).encode().decode('unicode_escape') if m else None
if __name__=='__main__':
    import sys
    for l in sys.argv[1:]: print(decode(l))
