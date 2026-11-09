#!/usr/bin/env python3
"""Check every shipped catalog, model constructor and the native selector hook."""
from pathlib import Path
import os
import re
import subprocess
import sys

module = Path(__file__).resolve().parents[1]
core = Path(sys.argv[1]).resolve()
version = sys.argv[2] if len(sys.argv) > 2 else '25.0.0-alpha'
models = sorted(module.glob('core/modules/*/doc/pdf_*.modules.php'))
keys = set()
for path in models:
    source = path.read_text(encoding='utf-8')
    for field in ('name', 'description'):
        match = re.search(r"\$this->"+field+r" = \$langs->trans\('([^']+)'\)", source)
        assert match, (path, field)
        keys.add(match[1])
keys.update(('JpsunDocumentModelSelectorTranslations', 'JpsunDocumentModelSelectorRequirement'))
for language in ('fr_FR', 'en_US', 'de_DE', 'es_ES', 'it_IT'):
    lines = (module/'langs'/language/'jpsun.lang').read_text(encoding='utf-8').splitlines()
    pairs = [line.split('=', 1) for line in lines if '=' in line]
    for key in keys:
        values = [value.strip() for name, value in pairs if name.strip() == key]
        assert len(values) == 1 and values[0] and values[0] != key, (language, key, values)
worker = module/'test/document_model_translations.php'
php = os.environ.get('PHP_BINARY', 'php')
for path in [None] + models:
    command = [php, str(worker), str(core), version]
    if path:
        command.append(path.relative_to(module).as_posix())
    result = subprocess.run(command, capture_output=True, text=True)
    if result.returncode:
        raise RuntimeError(f'{path}: {result.stdout}\n{result.stderr}')
print(f'{len(models)} models, 5 catalogs, native constructors and selector passed ({version})')
