#!/usr/bin/env python3
"""Translate en.json into multiple languages via OpenRouter DeepSeek-V4-Pro."""
import json, os, sys, subprocess

LANGUAGES = [
    {
        "code": "fr",
        "name": "French",
        "name_native": "Fran\u00e7ais",
        "system_prompt": "You are a professional French translator specialising in software UI localisation. Translate all the English UI strings below into natural, fluent French (Fran\u00e7ais). Rules:\n- Keep all HTML tags, placeholders like %s, %d, and code tags exactly as-is\n- Keep emoji (\u2705, \u274c, \u2014) exactly as-is\n- Keep \\n newlines in the values\n- Keep &amp; and other HTML entities\n- Use formal 'vous' form for UI text\n- For technical terms (Dashboard, Server, Login, etc.) use the standard French tech terminology\n- Output ONLY a valid JSON object with the same keys and translated values, no explanations, no markdown wrapping"
    },
    {
        "code": "es",
        "name": "Spanish",
        "name_native": "Espa\u00f1ol",
        "system_prompt": "You are a professional Spanish translator specialising in software UI localisation. Translate all the English UI strings below into natural, fluent Spanish (Espa\u00f1ol). Rules:\n- Keep all HTML tags, placeholders like %s, %d, and code tags exactly as-is\n- Keep emoji (\u2705, \u274c, \u2014) exactly as-is\n- Keep \\n newlines in the values\n- Keep &amp; and other HTML entities\n- Use formal 'usted' form for UI text\n- For technical terms (Dashboard, Server, Login, etc.) use the standard Spanish tech terminology\n- Output ONLY a valid JSON object with the same keys and translated values, no explanations, no markdown wrapping"
    },
    {
        "code": "it",
        "name": "Italian",
        "name_native": "Italiano",
        "system_prompt": "You are a professional Italian translator specialising in software UI localisation. Translate all the English UI strings below into natural, fluent Italian (Italiano). Rules:\n- Keep all HTML tags, placeholders like %s, %d, and code tags exactly as-is\n- Keep emoji (\u2705, \u274c, \u2014) exactly as-is\n- Keep \\n newlines in the values\n- Keep &amp; and other HTML entities\n- Use formal 'Lei' form for UI text\n- For technical terms (Dashboard, Server, Login, etc.) use the standard Italian tech terminology\n- Output ONLY a valid JSON object with the same keys and translated values, no explanations, no markdown wrapping"
    }
]

def flatten(data, prefix=""):
    result = {}
    for k, v in data.items():
        if k == "_meta": continue
        if isinstance(v, dict): result.update(flatten(v, prefix + k + "."))
        elif isinstance(v, str): result[prefix + k] = v
    return result

def reconstruct_nested(flat, source_en):
    """Rebuild nested structure from flat keys, using source_en as template."""
    result = {}
    for key, value in flat.items():
        parts = key.split(".")
        current = result
        for part in parts[:-1]:
            if part not in current:
                current[part] = {}
            current = current[part]
        current[parts[-1]] = value
    return result

def main():
    # Read en.json
    with open("/home/mike/Dev/skonadesk/dashboard/lang/en.json") as f:
        en = json.load(f)
    
    en_flat = flatten(en)
    en_json_str = json.dumps(en_flat, indent=2, ensure_ascii=False)
    
    api_key = os.environ.get("OPENROUTER_API_KEY", "")
    if not api_key:
        # Try sourcing from .env
        with open("/home/mike/.hermes/profiles/mike/.env") as f:
            for line in f:
                if line.startswith("OPENROUTER_API_KEY="):
                    api_key = line.split("=", 1)[1].strip()
                    break
    
    if not api_key:
        print("ERROR: No OPENROUTER_API_KEY found")
        sys.exit(1)
    
    for lang in LANGUAGES:
        print(f"\n=== Translating {lang['name']} ({lang['name_native']}) ===")
        
        payload = {
            "model": "deepseek/deepseek-v4-pro",
            "messages": [
                {"role": "system", "content": lang["system_prompt"]},
                {"role": "user", "content": f"Translate all these English UI strings into {lang['name']}. Return a flat JSON object with the same keys but with {lang['name']} values:\n\n{en_json_str}"}
            ],
            "max_tokens": 32000,
            "temperature": 0.1
        }
        
        # Call API
        import subprocess
        import tempfile
        
        with tempfile.NamedTemporaryFile(mode="w", suffix=".json", delete=False) as f:
            json.dump(payload, f, ensure_ascii=False)
            payload_path = f.name
        
        response_path = payload_path + ".response"
        
        try:
            result = subprocess.run([
                "curl", "-s", "https://openrouter.ai/api/v1/chat/completions",
                "-H", "Content-Type: application/json",
                "-H", f"Authorization: Bearer {api_key}",
                "--data", f"@{payload_path}",
                "-o", response_path
            ], capture_output=True, text=True, timeout=180)
            
            with open(response_path) as f:
                resp = json.load(f)
            
            if "error" in resp:
                print(f"  API ERROR: {resp['error']['message'][:100]}")
                continue
            
            content = resp["choices"][0]["message"]["content"]
            cost = resp.get("usage", {}).get("cost", 0)
            print(f"  Response: {len(content)} chars, cost: ${cost}")
            
            # Strip markdown code blocks if present
            content = content.strip()
            if content.startswith("```"):
                content = content.split("\n", 1)[1]
            if content.endswith("```"):
                content = content.rsplit("\n", 1)[0]
            content = content.strip()
            
            # Parse the flat JSON
            flat_result = json.loads(content)
            
            # Check for missing keys
            en_keys = set(en_flat.keys())
            result_keys = set(flat_result.keys())
            missing = en_keys - result_keys
            if missing:
                print(f"  ⚠️ {len(missing)} missing keys, filling from English")
                for k in missing:
                    flat_result[k] = en_flat[k]
            
            # Reconstruct nested structure
            nested = reconstruct_nested(flat_result, en)
            
            # Add _meta with source
            nested["_meta"] = {
                "name": lang["name"],
                "name_native": lang["name_native"],
                "source": "machine translated (DeepSeek-V4-Pro)"
            }
            
            # Validate by round-tripping
            output_path = f"/home/mike/Dev/skonadesk/dashboard/lang/{lang['code']}.json"
            with open(output_path, "w") as f:
                json.dump(nested, f, indent=2, ensure_ascii=False)
            
            # Verify
            with open(output_path) as f:
                verified = json.load(f)
            
            flat_verified = flatten(verified)
            total_keys = len(flat_verified)
            print(f"  ✅ Written to {output_path} ({total_keys} keys)")
            
            # Spot check
            checks = ["nav.dashboard", "nav.settings", "general.copy", "login.sign_in"]
            for ck in checks:
                parts = ck.split(".")
                val = verified
                for p in parts:
                    val = val.get(p, {})
                if isinstance(val, str):
                    print(f"    {ck}: {val[:50]}")
        
        finally:
            os.unlink(payload_path)
            if os.path.exists(response_path):
                os.unlink(response_path)
    
    print("\n✅ All translations complete!")

if __name__ == "__main__":
    main()
