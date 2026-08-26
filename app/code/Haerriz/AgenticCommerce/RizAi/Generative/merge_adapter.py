#!/usr/bin/env python3
"""Merge a trained RizAI LoRA adapter into its base model for serving/export."""
from __future__ import annotations
import argparse
from pathlib import Path
from peft import AutoPeftModelForCausalLM
from transformers import AutoTokenizer

def main():
    ap=argparse.ArgumentParser(); ap.add_argument('--adapter',required=True); ap.add_argument('--output',required=True); args=ap.parse_args()
    out=Path(args.output); out.mkdir(parents=True,exist_ok=True)
    model=AutoPeftModelForCausalLM.from_pretrained(args.adapter,device_map='cpu',torch_dtype='auto')
    merged=model.merge_and_unload(); merged.save_pretrained(out,safe_serialization=True,max_shard_size='4GB')
    AutoTokenizer.from_pretrained(args.adapter).save_pretrained(out)
    print(f'merged model written to {out}')
if __name__=='__main__': main()
