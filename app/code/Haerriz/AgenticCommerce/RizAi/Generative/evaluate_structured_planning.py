#!/usr/bin/env python3
"""Evaluate a generative RizAI checkpoint on the held-out structured-planning seed split."""
from __future__ import annotations
import argparse, json
from pathlib import Path
import torch
from transformers import AutoModelForCausalLM, AutoTokenizer


def tool_names(content: str):
    try:
        obj=json.loads(content.strip())
    except Exception:
        return None
    candidates=obj.get('tools',[]) if isinstance(obj,dict) else []
    if not isinstance(candidates,list): return None
    out=[]
    for c in candidates:
        if not isinstance(c,dict): return None
        n=c.get('name') or c.get('tool')
        if not isinstance(n,str) or not isinstance(c.get('arguments',{}),dict): return None
        out.append(n)
    return out


def main():
    ap=argparse.ArgumentParser(); ap.add_argument('--model',required=True); ap.add_argument('--data',type=Path,default=Path(__file__).with_name('data')/'rizai-commerce-sft-v1.jsonl'); ap.add_argument('--max-new-tokens',type=int,default=192); ap.add_argument('--trust-remote-code',action='store_true'); args=ap.parse_args()
    tok=AutoTokenizer.from_pretrained(args.model,trust_remote_code=args.trust_remote_code)
    model=AutoModelForCausalLM.from_pretrained(args.model,device_map='auto',torch_dtype='auto',trust_remote_code=args.trust_remote_code); model.eval()
    rows=[json.loads(l) for l in args.data.read_text(encoding='utf-8').splitlines() if l.strip()]
    rows=[r for r in rows if r.get('meta',{}).get('split')=='validation']
    total=valid=tool_exact=0
    for row in rows:
        msgs=row['messages']; prompt_msgs=msgs[:-1]; expected=msgs[-1]['content']; expected_names=tool_names(expected)
        if hasattr(tok,'apply_chat_template') and tok.chat_template:
            prompt=tok.apply_chat_template(prompt_msgs,tokenize=False,add_generation_prompt=True)
        else:
            prompt='\n'.join(f"{m['role'].upper()}: {m['content']}" for m in prompt_msgs)+'\nASSISTANT: '
        enc=tok(prompt,return_tensors='pt').to(model.device)
        with torch.no_grad(): out=model.generate(**enc,max_new_tokens=args.max_new_tokens,do_sample=False,pad_token_id=tok.eos_token_id)
        gen=tok.decode(out[0][enc['input_ids'].shape[1]:],skip_special_tokens=True).strip()
        names=tool_names(gen); total+=1
        if names is not None: valid+=1
        if names==expected_names: tool_exact+=1
    report={'validation_examples':total,'json_valid_rate':round(valid/total,4) if total else 0,'tool_sequence_exact_match':round(tool_exact/total,4) if total else 0}
    print(json.dumps(report,indent=2))

if __name__=='__main__': main()
