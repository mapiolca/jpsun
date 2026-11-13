#!/usr/bin/env python3
"""Native PHP calculation/table fixture and isolated DOM and availability scenarios."""
from pathlib import Path
import subprocess
import sys

module = Path(__file__).resolve().parents[1]
core = Path(sys.argv[1]).resolve()
version = sys.argv[2]
root = module / 'test/.runtime/situation-progress'
root.mkdir(parents=True, exist_ok=True)
result = root / 'table.json'
subprocess.run(['php', str(module / 'test/situation_progress.php'), str(core), version, str(result)], check=True)
subprocess.run(['node', str(module / 'test/situation_progress_dom.cjs'), str(result)], check=True)
# Availability contract only: fake the presence/absence of the future native API.
native = root / 'compta/facture/class/facture.class.php'
native.parent.mkdir(parents=True, exist_ok=True)
code = """define('DOL_DOCUMENT_ROOT', $argv[1]); define('DOL_VERSION', $argv[3]);
function isModEnabled($module) { return true; }
require $argv[2];
if (JpsunSituationProgress::isAvailable() !== ($argv[4] === '1')) { exit(1); }
"""
for method, target, expected in [(False, '20.0.0', '1'), (True, '25.0.0', '0'), (False, '26.0.0-alpha', '0')]:
    native.write_text('<?php class Facture {' + ('public function getSituationProgress() {}' if method else '') + '}\n', encoding='utf-8', newline='\n')
    subprocess.run(['php', '-r', code, str(root), str(module / 'class/jpsunsituationprogress.class.php'), target, expected], check=True)
print('OK native API and version availability guards (simulated API presence)')
