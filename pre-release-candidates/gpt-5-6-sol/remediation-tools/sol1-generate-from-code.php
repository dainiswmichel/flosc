<?php
/**
 * SOL-1 code-derived PHPDoc generator.
 *
 * Adds only comments. Summaries and parameter descriptions are derived from
 * declaration names, parameter roles, and observable calls/returns in the
 * implementation. A whole-file executable-token fingerprint rejects any edit
 * that changes PHP execution.
 *
 * @package FLOSC_Remediation
 */

declare(strict_types=1);

if ($argc < 2) { fwrite(STDERR,"usage: php sol1-generate-from-code.php TARGET_ROOT\n"); exit(2); }
$root=rtrim($argv[1],'/');

function g_exec_fp(string $code): string {
    $p=[]; foreach(token_get_all($code) as $t){
        if(is_array($t)){if(in_array($t[0],[T_WHITESPACE,T_COMMENT,T_DOC_COMMENT],true))continue;$p[]=token_name($t[0]).':'.$t[1];}
        else{$p[]='C:'.$t;}
    } return hash('sha256',implode("\0",$p));
}

/** @return list<array{id:int|null,text:string,start:int,end:int}> */
function g_tokens(string $code): array {
    $o=[];$off=0;foreach(token_get_all($code) as $t){$id=is_array($t)?$t[0]:null;$x=is_array($t)?$t[1]:$t;$s=$off;$off+=strlen($x);$o[]=['id'=>$id,'text'=>$x,'start'=>$s,'end'=>$off];}return $o;
}
function g_trivia(array $t): bool{return in_array($t['id'],[T_WHITESPACE,T_COMMENT,T_DOC_COMMENT],true);}
function g_human(string $name): string {
    $name=preg_replace('/^(flosc_|ajax_|handle_|get_|set_|save_|build_|create_|delete_|remove_|register_|render_|validate_|sanitize_|resolve_|find_|load_|update_|maybe_|process_|is_|has_|can_)+/','',$name)??$name;
    $name=preg_replace('/([a-z0-9])([A-Z])/','$1 $2',$name)??$name;
    $name=str_replace(['_','-'],' ',$name);$name=preg_replace('/\s+/',' ',trim($name))??trim($name);
    return $name!==''?$name:'operation';
}
function g_sentence(string $s): string{$s=trim($s);if($s==='')return $s;$s=strtoupper($s[0]).substr($s,1);if(!preg_match('/[.!?]$/',$s))$s.='.';return $s;}

/** @param list<array{id:int|null,text:string,start:int,end:int}> $t */
function g_bounds(array $t,int $i): array {
    $n=count($t);$par=0;$br=0;$open=$i;$end=$i;
    for($j=$i;$j<$n;$j++){if(g_trivia($t[$j]))continue;$x=$t[$j]['text'];if($x==='(')$par++;elseif($x===')')$par--;elseif($x==='[')$br++;elseif($x===']')$br--;elseif($par===0&&$br===0&&($x==='{'||$x===';')){$open=$j;if($x===';')return [$open,$open];$d=1;for($k=$j+1;$k<$n;$k++){if(g_trivia($t[$k]))continue;if($t[$k]['text']==='{')$d++;elseif($t[$k]['text']==='}'){if(--$d===0)return [$open,$k];}}return[$open,$n-1];}}
    return[$open,$end];
}
/** @param list<array{id:int|null,text:string,start:int,end:int}> $t */
function g_prefix(array $t,int $i): array {
    $mods=[T_PUBLIC,T_PROTECTED,T_PRIVATE,T_STATIC,T_FINAL,T_ABSTRACT];if(defined('T_READONLY'))$mods[]=constant('T_READONLY');$start=$i;$j=$i-1;
    while($j>=0){if($t[$j]['id']===T_WHITESPACE){$j--;continue;}if($t[$j]['id']!==null&&in_array($t[$j]['id'],$mods,true)){$start=$j;$j--;continue;}break;}
    while($j>=0&&$t[$j]['id']===T_WHITESPACE)$j--;$doc=null;if($j>=0&&$t[$j]['id']===T_DOC_COMMENT)$doc=['start'=>$t[$j]['start'],'end'=>$t[$j]['end'],'text'=>$t[$j]['text']];
    return[$start,$doc];
}
function g_indent(string $code,int $off):string{$p=strrpos(substr($code,0,$off),"\n");$s=$p===false?0:$p+1;preg_match('/^[\t ]*/',substr($code,$s,$off-$s),$m);return$m[0]??'';}

/** @param list<array{id:int|null,text:string,start:int,end:int}> $t */
function g_params(array $t,int $func,int $open):array{
    $lp=null;$depth=0;$out=[];
    for($i=$func;$i<$open;$i++){if($t[$i]['text']==='('){$lp=$i;break;}}
    if($lp===null)return[];$segment=[];
    for($i=$lp+1;$i<$open;$i++){
        $x=$t[$i]['text'];if($x==='('||$x==='[')$depth++;elseif($x===')'||$x===']'){if($depth>0)$depth--;else break;}
        if($depth===0&&$x===','){$segment=[];continue;}
        $segment[]=$t[$i];
        if($t[$i]['id']===T_VARIABLE){
            $name=$x;$type=[];foreach($segment as $q){if($q['id']===T_VARIABLE)break;if(!g_trivia($q)&&!in_array($q['text'],['&','...'],true))$type[]=$q['text'];}
            $out[]=['name'=>$name,'type'=>trim(implode('',$type))?:'mixed'];$segment=[];
        }
    }return$out;
}

function g_param_desc(string $name,string $context):string{
    $n=ltrim($name,'$');
    if(preg_match('/^(user|member)_id$/',$n))return 'WordPress user ID whose '.$context.' state is being processed.';
    if($n==='post_id')return 'WordPress post ID used to resolve the content involved in this operation.';
    if(preg_match('/flow.*id|flow_id/',$n))return 'Flow identifier used to resolve flow-scoped configuration and state.';
    if(str_contains($n,'ivr'))return 'IVR identifier or filename used to select the flow configuration.';
    if($n==='request'||str_ends_with($n,'_request'))return 'Request object carrying the input consumed by this handler.';
    if(str_contains($n,'url')||str_contains($n,'uri'))return 'URL being resolved, validated, or used by the '.$context.' operation.';
    if(str_contains($n,'path')||str_contains($n,'file'))return 'Filesystem value identifying the file used by the '.$context.' operation.';
    if(str_contains($n,'settings')||str_contains($n,'config'))return 'Configuration values used to control the '.$context.' behavior.';
    if(str_contains($n,'options')||str_contains($n,'args'))return 'Optional arguments that refine how the '.$context.' operation runs.';
    if(str_contains($n,'context'))return 'Context values used to resolve request- or flow-specific behavior.';
    if(str_contains($n,'payload')||str_contains($n,'data'))return 'Structured data consumed by the '.$context.' operation.';
    if(str_contains($n,'provider'))return 'Provider identifier or object used for the '.$context.' operation.';
    if(str_contains($n,'model'))return 'AI model identifier used for the provider request.';
    if(str_contains($n,'token'))return 'Token value used to authenticate or correlate this operation.';
    if(str_contains($n,'email'))return 'Email address used by the '.$context.' operation.';
    if(str_contains($n,'callback'))return 'Callback invoked when the '.$context.' operation reaches this extension point.';
    if(str_contains($n,'fallback')||$n==='default')return 'Fallback value returned when no more specific value is available.';
    if(str_contains($n,'id'))return 'Identifier used to select the record involved in the '.$context.' operation.';
    if(str_contains($n,'name')||str_contains($n,'key'))return 'Name or key used to select the '.$context.' value.';
    if(str_contains($n,'value'))return 'Value consumed or normalized by the '.$context.' operation.';
    return 'Input consumed by the '.$context.' operation.';
}

function g_summary(string $name,string $body,bool $method):string{
    $ctx=g_human($name);$lower=strtolower($name);$prefix=$method?'this object':'FLOSC';
    if(str_contains($body,'register_rest_route'))return g_sentence('Register the REST routes used for '.$ctx);
    if(str_contains($body,'add_action(')||str_contains($body,'add_filter('))return g_sentence('Register the WordPress hooks that connect '.$ctx.' to '.$prefix);
    if(str_contains($body,'wp_send_json_')||str_contains($lower,'ajax'))return g_sentence('Handle the '.$ctx.' AJAX request and return its JSON response');
    if(str_contains($body,'wp_remote_get')||str_contains($body,'wp_remote_post')||str_contains($body,'wp_remote_request'))return g_sentence('Send the remote request required for '.$ctx.' and normalize its result');
    if(preg_match('/update_(option|post_meta|user_meta)|add_option|set_transient/',$body))return g_sentence('Persist the '.$ctx.' state in WordPress storage');
    if(preg_match('/delete_(option|post_meta|user_meta|transient)/',$body))return g_sentence('Remove the stored state associated with '.$ctx);
    if(str_contains($body,'wp_safe_redirect')||str_contains($body,'wp_redirect'))return g_sentence('Resolve and perform the redirect required for '.$ctx);
    if(str_contains($body,'wp_mail('))return g_sentence('Prepare and send the email required for '.$ctx);
    if(str_contains($body,'echo ')||str_contains($body,'?><'))return g_sentence('Render the WordPress interface for '.$ctx);
    if(str_starts_with($lower,'is_')||str_starts_with($lower,'has_')||str_starts_with($lower,'can_'))return g_sentence('Determine whether the current state satisfies '.$ctx);
    if(str_starts_with($lower,'validate_')||str_starts_with($lower,'verify_'))return g_sentence('Validate the input and trust conditions required for '.$ctx);
    if(str_starts_with($lower,'sanitize_')||str_starts_with($lower,'normalize_'))return g_sentence('Normalize the input into the canonical form required for '.$ctx);
    if(str_starts_with($lower,'build_')||str_starts_with($lower,'compile_'))return g_sentence('Build the structured value consumed by '.$ctx);
    if(str_starts_with($lower,'get_')||str_starts_with($lower,'find_')||str_starts_with($lower,'resolve_')||str_starts_with($lower,'load_'))return g_sentence('Resolve the current '.$ctx.' value from the available WordPress and flow state');
    if(str_starts_with($lower,'save_')||str_starts_with($lower,'update_'))return g_sentence('Save the validated '.$ctx.' state for later requests');
    if(str_starts_with($lower,'create_'))return g_sentence('Create the WordPress data required for '.$ctx);
    if(str_starts_with($lower,'delete_')||str_starts_with($lower,'remove_'))return g_sentence('Remove the WordPress data associated with '.$ctx);
    return g_sentence('Coordinate the '.$ctx.' behavior implemented by this code path');
}
function g_return_tag(string $name,string $body):?string{
    if(!preg_match('/\breturn\b/',$body))return null;$ctx=g_human($name);$lower=strtolower($name);
    if(str_starts_with($lower,'is_')||str_starts_with($lower,'has_')||str_starts_with($lower,'can_')||preg_match('/return\s+(?:true|false)\s*;/',$body))return '@return bool Whether '.$ctx.' applies to the current state.';
    if(preg_match('/return\s+array\s*\(|return\s*\[/',$body))return '@return array Structured '.$ctx.' data.';
    if(preg_match('/return\s+new\s+WP_Error|is_wp_error/',$body))return '@return mixed Result of the '.$ctx.' operation, or a WP_Error when it cannot complete.';
    return '@return mixed Result produced by the '.$ctx.' operation.';
}
function g_doc_has_summary(string $doc):bool{
    $inner=preg_replace('#^/\*\*|\*/$#s','',$doc)??$doc;foreach(preg_split('/\R/',$inner)?:[] as $line){$x=trim(preg_replace('/^\s*\*\s?/','',$line)??$line);if($x===''||str_starts_with($x,'@'))continue;return true;}return false;
}
function g_existing_params(string $doc):array{$o=[];if(preg_match_all('/@param\s+\S+\s+(\$[A-Za-z_][A-Za-z0-9_]*)\s*(.*)/',$doc,$m,PREG_SET_ORDER)){foreach($m as$r)$o[$r[1]]=trim($r[2]);}return$o;}
function g_build_doc(string $summary,array $params,?string $ret,string $indent):string{
    $lines=['/**',' * '.$summary];if($params||$ret)$lines[]=' *';foreach($params as$p)$lines[]=' * @param '.$p['type'].' '.$p['name'].' '.g_param_desc($p['name'],g_human($summary));if($ret)$lines[]=' * '.$ret;$lines[]=' */';return implode("\n",array_map(fn($x)=>$indent.$x,$lines));
}
function g_merge_doc(string $doc,string $summary,array $params,?string $ret):string{
    $lines=preg_split('/\R/',$doc)?:[$doc];$existing=g_existing_params($doc);
    if(!g_doc_has_summary($doc))array_splice($lines,1,0,[' * '.$summary,' *']);
    // Replace missing/empty param descriptions, append absent tags before return/closer.
    foreach($params as$p){
        $found=false;foreach($lines as$i=>$line){if(preg_match('/@param\s+\S+\s+'.preg_quote($p['name'],'/').'\b/',$line)){$found=true;if(($existing[$p['name']]??'')==='')$lines[$i]=preg_replace('/@param\s+\S+\s+'.preg_quote($p['name'],'/').'.*/','@param '.$p['type'].' '.$p['name'].' '.g_param_desc($p['name'],g_human($summary)),$line)??$line;break;}}
        if(!$found){$pos=count($lines)-1;foreach($lines as$i=>$line){if(str_contains($line,'@return')){$pos=$i;break;}}array_splice($lines,$pos,0,[' * @param '.$p['type'].' '.$p['name'].' '.g_param_desc($p['name'],g_human($summary))]);}
    }
    if($ret&&!preg_match('/@return\b/',$doc)){array_splice($lines,count($lines)-1,0,[' * '.$ret]);}
    return implode("\n",$lines);
}

/** @return list<string> */
function g_files(string $root):array{$o=[];$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));foreach($it as$f){if($f->isFile()&&strtolower($f->getExtension())==='php'&&!str_contains($f->getPathname(),DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR)&&!str_contains($f->getPathname(),DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR))$o[]=$f->getPathname();}sort($o);return$o;}

$files=0;$changed=0;$funcDocs=0;$classDocs=0;$paramAdds=0;$commentPunct=0;
foreach(g_files($root) as$path){$files++;$code=file_get_contents($path);if($code===false)continue;$fp=g_exec_fp($code);$t=g_tokens($code);$n=count($t);$repl=[];$classStack=[];$pending=null;$depth=0;
    for($i=0;$i<$n;$i++){$id=$t[$i]['id'];$x=$t[$i]['text'];
        $classIds=[T_CLASS,T_TRAIT,T_INTERFACE];if(defined('T_ENUM'))$classIds[]=constant('T_ENUM');
        if($id!==null&&in_array($id,$classIds,true)){$prev=$i-1;while($prev>=0&&$t[$prev]['id']===T_WHITESPACE)$prev--;if($prev>=0&&$t[$prev]['id']===T_DOUBLE_COLON)continue;$j=$i+1;while($j<$n&&$t[$j]['id']!==T_STRING)$j++;if($j<$n){[$open,$end]=g_bounds($t,$i);[$ds,$doc]=g_prefix($t,$i);$name=$t[$j]['text'];$body=substr($code,$t[$open]['end'],$t[$end]['start']-$t[$open]['end']);$summary=g_sentence('Coordinate '.g_human($name).' behavior and the WordPress services used by its methods');if($doc===null){$ind=g_indent($code,$t[$ds]['start']);$repl[]=[$t[$ds]['start'],$t[$ds]['start'],$ind."/**\n".$ind.' * '.$summary."\n".$ind." */\n"];$classDocs++;}$pending=['name'=>$name,'open'=>$open];}}
        if($id===T_FUNCTION){$j=$i+1;while($j<$n&&($t[$j]['id']===T_WHITESPACE||$t[$j]['text']==='&'))$j++;if($j<$n&&$t[$j]['id']===T_STRING){[$open,$end]=g_bounds($t,$i);[$ds,$doc]=g_prefix($t,$i);$name=$t[$j]['text'];$body=substr($code,$t[$open]['end'],$t[$end]['start']-$t[$open]['end']);$params=g_params($t,$i,$open);$summary=g_summary($name,$body,!empty($classStack));$ret=g_return_tag($name,$body);$ind=g_indent($code,$t[$ds]['start']);if($doc===null){$repl[]=[$t[$ds]['start'],$t[$ds]['start'],g_build_doc($summary,$params,$ret,$ind)."\n"];$funcDocs++;$paramAdds+=count($params);}else{$new=g_merge_doc($doc['text'],$summary,$params,$ret);if($new!==$doc['text']){$repl[]=[$doc['start'],$doc['end'],$new];$paramAdds+=count($params);}}}}
        if($x==='{'){$depth++;if($pending&&$pending['open']===$i){$classStack[]=['name'=>$pending['name'],'depth'=>$depth];$pending=null;}}elseif($x==='}') {if($classStack&&$classStack[array_key_last($classStack)]['depth']===$depth)array_pop($classStack);$depth--;}
        // Safe single-line comment punctuation. Directives, separators, URLs and code fragments stay untouched.
        if($id===T_COMMENT&&str_starts_with(ltrim($x),'//')&&!str_contains($x,'phpcs:')&&!str_contains($x,'http://')&&!str_contains($x,'https://')){$trim=rtrim($x,"\r\n ");$body=trim(substr(ltrim($trim),2));if($body!==''&&!preg_match('/[.!?:;)}\]`\-]$/',$body)&&!preg_match('/^[=\-─_#*]+$/u',$body)){$new=$trim.'.'.substr($x,strlen(rtrim($x,"\r\n")));$repl[]=[$t[$i]['start'],$t[$i]['end'],$new];$commentPunct++;}}
    }
    if(!$repl)continue;$u=[];foreach($repl as$r){$u[$r[0].':'.$r[1]]=$r;}$repl=array_values($u);usort($repl,fn($a,$b)=>$b[0]<=>$a[0]);$new=$code;foreach($repl as[$a,$b,$z])$new=substr($new,0,$a).$z.substr($new,$b);if(g_exec_fp($new)!==$fp){fwrite(STDERR,"CODE_TOKEN_CHANGE_REFUSED $path\n");continue;}if($new!==$code){file_put_contents($path,$new);$changed++;echo 'SOL1_CODE_DOC_CHANGED '.substr($path,strlen($root)+1)."\n";}
}
echo "SOL1_CODE_DOC_SUMMARY files={$files} changed={$changed} function_docs={$funcDocs} class_docs={$classDocs} param_work={$paramAdds} inline_punctuation={$commentPunct}\n";
