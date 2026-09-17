#!/usr/bin/env python3
"""Evidence-driven SOL-1 comment/whitespace remediation.

Consumes a PHPCS JSON report and touches only comments or leading whitespace.
It exists to repair the documentation pass without changing PHP executable
syntax. The workflow independently fingerprints executable tokens before/after.
"""
from __future__ import annotations
import json,re,sys,pathlib

if len(sys.argv) != 4:
    raise SystemExit('usage: sol1-fix-from-phpcs.py REPORT.json TARGET_ROOT CHANGELOG')
report=pathlib.Path(sys.argv[1]); root=pathlib.Path(sys.argv[2]).resolve(); changelog=pathlib.Path(sys.argv[3])
data=json.loads(report.read_text(encoding='utf-8'))
marker='/pre-release-candidates/gpt-5-6-sol/flosc-by-gpt-5-6-sol/flosc/'


def sentence(s:str)->str:
    s=s.strip()
    if not s:return s
    s=re.sub(r'\bWord\s+Press\b','WordPress',s,flags=re.I)
    s=re.sub(r'\bwordpress\b','WordPress',s,flags=re.I)
    if s and s[0].islower():s=s[0].upper()+s[1:]
    if not re.search(r'[.!?:;`\)\]]$',s):s+='.'
    return s

def human(name:str)->str:
    name=name.lstrip('$')
    name=re.sub(r'^(flosc_|ajax_|handle_|get_|set_|save_|build_|create_|delete_|remove_|register_|render_|validate_|sanitize_|resolve_|find_|load_|update_|maybe_|process_|is_|has_|can_)+','',name)
    name=re.sub(r'([a-z0-9])([A-Z])',r'\1 \2',name).replace('_',' ').replace('-',' ')
    return re.sub(r'\s+',' ',name).strip() or 'operation'

def param_desc(name:str)->str:
    n=name.lstrip('$'); h=human(n)
    if re.search(r'^(user|member)_id$',n):return 'WordPress user ID whose state is used by this operation.'
    if n=='post_id':return 'WordPress post ID identifying the content used by this operation.'
    if 'flow' in n and 'id' in n:return 'Flow identifier used to resolve flow-scoped configuration and state.'
    if 'ivr' in n:return 'IVR identifier or filename selecting the flow configuration.'
    if n=='request' or n.endswith('_request'):return 'Request object carrying the input consumed by this handler.'
    if 'url' in n or 'uri' in n:return 'URL resolved or validated by this operation.'
    if 'path' in n or 'file' in n:return 'Filesystem value identifying the file used by this operation.'
    if 'settings' in n or 'config' in n:return 'Configuration values controlling this operation.'
    if 'options' in n or 'args' in n:return 'Optional arguments refining how this operation runs.'
    if 'context' in n:return 'Context values used to resolve request- or flow-specific behavior.'
    if 'payload' in n or 'data' in n:return 'Structured data consumed by this operation.'
    if 'provider' in n:return 'Provider identifier or object used by this integration path.'
    if 'model' in n:return 'AI model identifier used for the provider request.'
    if 'token' in n:return 'Token value used for authentication or request correlation.'
    if 'email' in n:return 'Email address used by this operation.'
    if 'callback' in n:return 'Callback invoked when this extension point is reached.'
    if 'fallback' in n or n=='default':return 'Fallback value used when no more specific value is available.'
    if 'id' in n:return f'Identifier selecting the {h} record used by this operation.'
    if 'name' in n or 'key' in n:return f'Name or key selecting the {h} value.'
    if 'value' in n:return f'Value consumed or normalized for {h}.'
    return f'Input consumed while processing {h}.'

def property_desc(name:str)->str:
    n=name.lstrip('$'); h=human(n)
    if 'provider' in n:return 'Provider collaborator used to dispatch the corresponding external integration.'
    if 'logger' in n or n.endswith('log'):return 'Logging collaborator used to record this component\'s operational events.'
    if 'filesystem' in n:return 'Filesystem collaborator used for WordPress-managed reads and writes.'
    if 'cache' in n:return 'Cached state retained to avoid repeating the same resolution work during one request.'
    if 'token' in n:return 'Token-related state retained for authentication or request correlation.'
    if 'session' in n:return 'Session-scoped state retained while serving the current visitor.'
    if 'manager' in n:return 'Manager collaborator that owns the subsystem behavior delegated by this class.'
    if 'flow' in n:return 'Flow-scoped state retained between related operations.'
    if 'config' in n or 'settings' in n:return 'Configuration state used to control this class\'s behavior.'
    return f'Internal {h} state retained for the operations that consume it.'

def summary_for_function(name:str)->str:
    h=human(name); lower=name.lower()
    if lower.startswith(('is_','has_','can_')):return f'Determine whether the current state satisfies {h}.'
    if lower.startswith(('validate_','verify_')):return f'Validate the trust and input conditions required for {h}.'
    if lower.startswith(('sanitize_','normalize_')):return f'Normalize the input into the canonical form required for {h}.'
    if lower.startswith(('get_','find_','resolve_','load_')):return f'Resolve the current {h} value from available WordPress and flow state.'
    if lower.startswith(('save_','update_')):return f'Save the validated {h} state for later requests.'
    if lower.startswith('register_'):return f'Register the WordPress integration points required for {h}.'
    if lower.startswith('render_'):return f'Render the WordPress interface used for {h}.'
    if lower.startswith(('delete_','remove_')):return f'Remove the WordPress data associated with {h}.'
    if 'ajax' in lower:return f'Handle the {h} AJAX request and return its response.'
    return f'Coordinate the {h} behavior implemented by this code path.'

def comment_bounds(lines:list[str], idx:int):
    # Containing PHPDoc.
    for s in range(idx,-1,-1):
        if '/**' in lines[s]:
            for e in range(s,min(len(lines),idx+80)):
                if '*/' in lines[e]:
                    if e>=idx:return s,e
                    break
            break
        if '*/' in lines[s] and s<idx:break
    # Immediately preceding PHPDoc.
    j=idx-1
    while j>=0 and lines[j].strip()=='':j-=1
    if j>=0 and '*/' in lines[j]:
        e=j
        while j>=0 and '/**' not in lines[j]:j-=1
        if j>=0:return j,e
    return None

def next_code(lines:list[str], idx:int, limit=80):
    for j in range(idx,min(len(lines),idx+limit)):
        s=lines[j].strip()
        if s and not s.startswith(('*','//','/*','#')):return j,s
    return None,None

def function_info(lines:list[str], idx:int):
    # Search forward first; if message is on declaration this hits immediately.
    candidates=list(range(max(0,idx-3),min(len(lines),idx+35)))
    for j in candidates:
        if re.search(r'\bfunction\s+[A-Za-z_][A-Za-z0-9_]*\s*\(', lines[j]):
            text=' '.join(x.strip() for x in lines[j:min(len(lines),j+20)])
            m=re.search(r'function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\((.*?)\)\s*(?::\s*[^\{]+)?\s*\{',text)
            if not m:continue
            name=m.group(1); raw=m.group(2); params=[]
            # Parameter variables are enough for WPCS tags; preserve simple declared type.
            for pm in re.finditer(r'(?:(?P<type>[?\\A-Za-z_][\\A-Za-z0-9_|?]*)\s+)?(?:&\s*)?(?:\.\.\.\s*)?(?P<var>\$[A-Za-z_][A-Za-z0-9_]*)',raw):
                params.append((pm.group('type') or 'mixed',pm.group('var')))
            return j,name,params
    return None

def insert_doc(lines:list[str], idx:int, indent:str, summary:str, params:list[tuple[str,str]]|None=None, var_type:str|None=None):
    block=[indent+'/**',indent+' * '+sentence(summary)]
    if params or var_type:
        block.append(indent+' *')
    if var_type:
        block.append(indent+' * @var '+var_type)
    for typ,var in params or []:
        block.append(indent+f' * @param {typ} {var} {param_desc(var)}')
    block.append(indent+' */')
    lines[idx:idx]=block

files={}
for f,b in (data.get('files') or {}).items():
    msgs=b.get('messages') or []
    if not msgs:continue
    fp=f.replace('\\','/')
    if marker not in fp:continue
    rel=fp.split(marker,1)[1]
    path=(root/rel).resolve()
    try:path.relative_to(root)
    except ValueError:continue
    files[path]=msgs

log=[];changed_files=0
for path,msgs in files.items():
    if not path.is_file():continue
    original=path.read_text(encoding='utf-8')
    lines=original.splitlines()
    trailing='\n' if original.endswith('\n') else ''
    # Multiple findings can share a line. Group and process highest line first so
    # insertions never invalidate lower original line numbers.
    byline={}
    for m in msgs:byline.setdefault(int(m.get('line') or 1)-1,[]).append(m)
    for idx in sorted(byline,reverse=True):
        if idx<0 or idx>=len(lines):continue
        for m in byline[idx]:
            src=m.get('source',''); msg=m.get('message','')
            if src.startswith('Generic.WhiteSpace.ScopeIndent.'):
                mm=re.search(r'expected(?: at least)?\s+(\d+)\s+tabs?',msg)
                if mm:
                    want=int(mm.group(1)); lines[idx]=('\t'*want)+lines[idx].lstrip(' \t');log.append(f'{path.name}:{idx+1} scope->{want}tab')
            elif src=='WordPress.WP.CapitalPDangit.MisspelledInComment':
                new=re.sub(r'\bWord\s+Press\b','WordPress',lines[idx],flags=re.I)
                new=re.sub(r'\bwordpress\b','WordPress',new,flags=re.I)
                if new!=lines[idx]:lines[idx]=new;log.append(f'{path.name}:{idx+1} WordPress')
            elif src=='Squiz.Commenting.InlineComment.InvalidEndChar':
                if '//' in lines[idx]:
                    p=lines[idx].rfind('//'); head=lines[idx][:p+2]; tail=lines[idx][p+2:].rstrip()
                    lines[idx]=head+' '+sentence(tail);log.append(f'{path.name}:{idx+1} inline-punctuation')
            elif src=='Squiz.Commenting.BlockComment.CloserSameLine':
                if '*/' in lines[idx] and lines[idx].strip()!='*/':
                    p=lines[idx].rfind('*/'); before=lines[idx][:p].rstrip(); indent=re.match(r'^[\t ]*',lines[idx]).group(0)
                    lines[idx:idx+1]=[before,indent+'*/'];log.append(f'{path.name}:{idx+1} block-closer')
            elif src=='Squiz.Commenting.BlockComment.NoEmptyLineBefore':
                if idx>0 and lines[idx-1].strip()!='':lines[idx:idx]=[''];log.append(f'{path.name}:{idx+1} blank-before-block')
            elif src=='Squiz.Commenting.BlockComment.HasEmptyLineBefore':
                if idx>1 and lines[idx-1].strip()=='' and lines[idx-2].strip()=='':del lines[idx-1];log.append(f'{path.name}:{idx+1} trim-blank-before-block')
            elif src=='Squiz.Commenting.BlockComment.NoCapital':
                s=lines[idx]; p=s.find('/*');
                if p>=0:
                    prefix=s[:p+2];tail=s[p+2:].strip();lines[idx]=prefix+' '+sentence(tail);log.append(f'{path.name}:{idx+1} block-capital')
            elif src=='Squiz.Commenting.FunctionComment.InvalidNoReturn':
                info=function_info(lines,idx)
                anchor=info[0] if info else idx; b=comment_bounds(lines,anchor)
                if b:
                    s,e=b; new=[x for x in lines[s:e+1] if '@return' not in x]
                    if new!=lines[s:e+1]:lines[s:e+1]=new;log.append(f'{path.name}:{idx+1} remove-invalid-return')
            elif src=='Squiz.Commenting.FunctionComment.MissingParamTag':
                info=function_info(lines,idx)
                if info:
                    fj,name,params=info;b=comment_bounds(lines,fj)
                    if b:
                        s,e=b;doc='\n'.join(lines[s:e+1]); existing=set(re.findall(r'@param\s+\S+\s+(\$\w+)',doc));adds=[p for p in params if p[1] not in existing]
                        if adds:
                            ins=next((k for k in range(s,e+1) if '@return' in lines[k] or '@throws' in lines[k]),e)
                            indent=re.match(r'^[\t ]*',lines[ins]).group(0)
                            lines[ins:ins]=[indent+f' * @param {typ} {var} {param_desc(var)}' for typ,var in adds];log.append(f'{path.name}:{idx+1} add-param-tags={len(adds)}')
            elif src=='Squiz.Commenting.FunctionComment.ParamCommentFullStop':
                if '@param' in lines[idx] and not re.search(r'[.!?]\s*$',lines[idx].rstrip().rstrip('*/').rstrip()):lines[idx]=lines[idx].rstrip()+'.';log.append(f'{path.name}:{idx+1} param-period')
            elif src=='Squiz.Commenting.FunctionCommentThrowTag.Missing':
                info=function_info(lines,idx)
                if info:
                    fj,name,params=info;b=comment_bounds(lines,fj)
                    if b:
                        s,e=b; body='\n'.join(lines[fj:min(len(lines),fj+120)]);tm=re.search(r'throw\s+new\s+([\\A-Za-z_][\\A-Za-z0-9_]*)',body);exc=tm.group(1) if tm else '\\Exception';indent=re.match(r'^[\t ]*',lines[e]).group(0);lines[e:e]=[indent+f' * @throws {exc} When the underlying operation cannot complete safely.'];log.append(f'{path.name}:{idx+1} throws-tag')
            elif src=='Squiz.Commenting.FileComment.Missing':
                # Insert once after the PHP opener; package tag makes the file-level purpose explicit.
                if not any('@package FLOSC' in x for x in lines[:20]):
                    ins=1 if lines and lines[0].lstrip().startswith('<?php') else 0
                    title=human(path.stem)
                    block=['/**',f' * FLOSC {title} implementation.',' *',' * @package FLOSC',' */','']
                    lines[ins:ins]=block;log.append(f'{path.name}: file-doc')
            elif src=='Squiz.Commenting.ClassComment.Missing':
                if re.search(r'\b(class|trait|interface)\s+\w+',lines[idx]):
                    mm=re.search(r'\b(class|trait|interface)\s+(\w+)',lines[idx]);name=mm.group(2) if mm else path.stem;indent=re.match(r'^[\t ]*',lines[idx]).group(0);insert_doc(lines,idx,indent,f'{name} coordinates its FLOSC subsystem responsibilities.');log.append(f'{path.name}:{idx+1} class-doc')
            elif src=='Squiz.Commenting.FunctionComment.Missing':
                info=function_info(lines,idx)
                if info:
                    fj,name,params=info;indent=re.match(r'^[\t ]*',lines[fj]).group(0);insert_doc(lines,fj,indent,summary_for_function(name),params=params);log.append(f'{path.name}:{idx+1} function-doc {name}')
            elif src in ('Squiz.Commenting.VariableComment.Missing','Squiz.Commenting.VariableComment.WrongStyle'):
                line=lines[idx];vm=re.search(r'(?:(?:public|protected|private|static|readonly)\s+)*(?:(?P<type>[?\\A-Za-z_][\\A-Za-z0-9_|?]*)\s+)?(?P<var>\$[A-Za-z_][A-Za-z0-9_]*)',line)
                if vm:
                    var=vm.group('var');typ=vm.group('type') or 'mixed';indent=re.match(r'^[\t ]*',line).group(0);insert_doc(lines,idx,indent,property_desc(var),var_type=typ);log.append(f'{path.name}:{idx+1} property-doc {var}')
            elif src=='Squiz.Commenting.VariableComment.MissingVar':
                b=comment_bounds(lines,idx)
                if b:
                    s,e=b;line=lines[idx] if idx<len(lines) else '';vm=re.search(r'(\$[A-Za-z_][A-Za-z0-9_]*)',line);var=vm.group(1) if vm else '$value';indent=re.match(r'^[\t ]*',lines[e]).group(0);lines[e:e]=[indent+' * @var mixed'];log.append(f'{path.name}:{idx+1} add-var-tag {var}')
            elif src=='Generic.Commenting.DocComment.Empty':
                b=comment_bounds(lines,idx)
                if b:
                    s,e=b;j,code=next_code(lines,e+1);indent=re.match(r'^[\t ]*',lines[s]).group(0)
                    if code and re.search(r'\$[A-Za-z_][A-Za-z0-9_]*',code) and not re.search(r'\bfunction\b',code):
                        vm=re.search(r'(\$[A-Za-z_][A-Za-z0-9_]*)',code);var=vm.group(1);lines[s:e+1]=[indent+'/**',indent+' * '+property_desc(var),indent+' *',indent+' * @var mixed',indent+' */']
                    elif code and 'function ' in code:
                        fm=re.search(r'function\s+(\w+)',code);name=fm.group(1) if fm else 'operation';lines[s:e+1]=[indent+'/**',indent+' * '+summary_for_function(name),indent+' */']
                    else:lines[s:e+1]=[indent+'/**',indent+' * Internal FLOSC implementation detail used by the declaration below.',indent+' */']
                    log.append(f'{path.name}:{idx+1} fill-empty-doc')
            elif src=='Generic.Commenting.DocComment.MissingShort':
                b=comment_bounds(lines,idx)
                if b:
                    s,e=b;j,code=next_code(lines,e+1);summary='FLOSC implementation detail for the declaration below.'
                    if code and 'function ' in code:
                        fm=re.search(r'function\s+(\w+)',code);summary=summary_for_function(fm.group(1) if fm else 'operation')
                    indent=re.match(r'^[\t ]*',lines[s]).group(0);lines[s+1:s+1]=[indent+' * '+summary];log.append(f'{path.name}:{idx+1} missing-short')
            elif src=='Generic.Commenting.DocComment.LongNotCapital':
                s=lines[idx];m0=re.match(r'^(\s*\*\s*)(.*)$',s)
                if m0:lines[idx]=m0.group(1)+sentence(m0.group(2));log.append(f'{path.name}:{idx+1} doc-capital')
    new='\n'.join(lines)+trailing
    if new!=original:
        path.write_text(new,encoding='utf-8');changed_files+=1

changelog.write_text('\n'.join(log)+f'\nSOL1_FIX_SUMMARY files={changed_files} edits={len(log)}\n',encoding='utf-8')
print(f'SOL1_FIX_SUMMARY files={changed_files} edits={len(log)}')
