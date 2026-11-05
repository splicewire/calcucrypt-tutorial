"""Prepare the explicit optional Commerce-only wallet inputs; never install or boot."""
from pathlib import Path
import argparse,hashlib,json,subprocess
p=argparse.ArgumentParser(description=__doc__);p.add_argument('--commerce-source',type=Path,required=True);a=p.parse_args();root=Path(__file__).resolve().parents[2];raw=(root/'composer.lock').read_bytes()
if hashlib.sha256(raw).hexdigest()!='3a770fe49f04c5751eb91fa98b2af8591e6518f2128a6bcf8072306e5d1e9304':p.error('Expected the shipping225 lock; do not carry optional marketplace inputs.')
source=a.commerce_source.resolve(strict=True)
def git(*args):return subprocess.check_output(['git','-C',str(source),*args],text=True).strip()
ref='aef4bfbdce492bbcc91c3027a46bdeb1042a4a77'
if git('rev-parse','HEAD')!=ref or git('status','--porcelain=v1','--untracked-files=no'):p.error('Commerce source must be clean at exact selected aef ref.')
if json.loads((source/'composer.json').read_text())['name']!='rushing/laravel-commerce':p.error('Commerce package identity mismatch.')
branch=git('branch','--show-current')
if not branch:p.error('Use a named local VCS branch at exact selected Commerce ref.')
out=root/'composer.wallet.json';lockout=root/'composer.wallet.lock'
if out.exists() or lockout.exists():p.error('Refusing to overwrite an alternate context.')
lock=json.loads(raw);m=json.loads((root/'composer.json').read_text())
for sec,key in [('require','packages'),('require-dev','packages-dev')]:
 for item in lock[key]:
  version=item['version'];m.setdefault(sec,{})[item['name']]=version+('#'+item['source']['reference'] if version.startswith('dev-') else '')
m['require']['rushing/laravel-commerce']='dev-'+branch+'#'+ref
m['require']['league/commonmark']='2.10.4';m['require']['symfony/http-foundation']='8.1.9'
m['repositories']=[{'type':'git','url':str(source),'only':['rushing/laravel-commerce']}]+m.get('repositories',[])
m.setdefault('extra',{}).setdefault('merge-plugin',{})['include']=[]
out.write_text(json.dumps(m,indent=4)+'\n');lockout.write_bytes(raw)
print('Prepared isolated inputs only: next constrained dry-run, review delta, no-scripts install and installed audit before boot.')
